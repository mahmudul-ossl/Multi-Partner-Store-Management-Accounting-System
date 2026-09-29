<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\ApprovalStateException;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryService;
use App\Services\Ledger\JournalEntryService;
use App\Services\OperationalNotifier;
use App\Support\ChartAccountCode;
use App\Support\Costing;
use App\Support\Money;
use App\Support\SaleMath;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

final class SaleReturnService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly JournalEntryService $journal,
        private readonly AuditLogService $audit,
        private readonly OperationalNotifier $alerts,
    ) {}

    /**
     * @param  array{sale_id: int, transaction_date: string, note?: string|null, items: list<array{sale_item_id: int, quantity: string|int}>}  $attributes
     */
    public function create(User $actor, array $attributes): SaleReturn
    {
        return DB::transaction(function () use ($actor, $attributes): SaleReturn {
            $sale = Sale::query()->with('items')->lockForUpdate()->findOrFail($attributes['sale_id']);

            if ($sale->status !== DocumentStatus::Completed) {
                throw new ApprovalStateException('Only a completed sale can be returned.');
            }

            $lines = [];
            $revenue = '0.00';
            $cogs = '0.00';

            foreach ($attributes['items'] as $row) {
                $item = $sale->items->firstWhere('id', (int) $row['sale_item_id']);

                if (! $item instanceof SaleItem || $item->unit_cost === null) {
                    throw new ApprovalStateException('That line is not on the sale.');
                }

                $quantity = Costing::quantity($row['quantity']);
                $returnedQty = '0.000';
                $returnedRevenue = '0.00';
                $returnedCogs = '0.00';

                $previous = SaleReturnItem::query()
                    ->where('sale_item_id', $item->id)
                    ->whereHas('saleReturn', fn ($query) => $query->where('status', DocumentStatus::Completed->value))
                    ->get(['quantity', 'line_total', 'cogs_amount']);

                foreach ($previous as $existing) {
                    $returnedQty = bcadd($returnedQty, Costing::quantity((string) $existing->quantity), 3);
                    $returnedRevenue = Money::of($returnedRevenue)->add((string) $existing->line_total)->amount();
                    $returnedCogs = Money::of($returnedCogs)->add((string) $existing->cogs_amount)->amount();
                }

                $remainingQty = bcsub(Costing::quantity((string) $item->quantity), $returnedQty, 3);

                if (Costing::compareQty($quantity, '0') !== 1 || Costing::compareQty($quantity, $remainingQty) === 1) {
                    throw new ApprovalStateException('The return quantity is not available on that line.');
                }

                if (Costing::compareQty($quantity, $remainingQty) === 0) {
                    $lineRevenue = Money::of((string) $item->net_amount)->sub($returnedRevenue)->amount();
                    $lineCogs = Money::of((string) $item->cogs_amount)->sub($returnedCogs)->amount();
                } else {
                    $lineRevenue = SaleMath::portion((string) $item->net_amount, $quantity, (string) $item->quantity);
                    $lineCogs = SaleMath::portion((string) $item->cogs_amount, $quantity, (string) $item->quantity);
                }

                $revenue = Money::of($revenue)->add($lineRevenue)->amount();
                $cogs = Money::of($cogs)->add($lineCogs)->amount();
                $lines[] = [
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => Costing::cost((string) $item->unit_cost),
                    'line_total' => $lineRevenue,
                    'cogs_amount' => $lineCogs,
                    'product' => $item->product,
                ];
            }

            if ($lines === [] || Money::of($revenue)->compare('0.00') !== 1) {
                throw new ApprovalStateException('A return needs at least one item.');
            }

            $return = SaleReturn::query()->create([
                'reference' => Sequence::next(SaleReturn::class, 'reference', 'SRN'),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'warehouse_id' => $sale->warehouse_id,
                'transaction_date' => $attributes['transaction_date'],
                'total' => $revenue,
                'cogs_total' => $cogs,
                'note' => $attributes['note'] ?? null,
                'status' => DocumentStatus::Completed,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $this->inventory->apply(
                    $line['product'],
                    $sale->warehouse,
                    StockMovementType::SaleReturn,
                    $line['quantity'],
                    $line['unit_cost'],
                    $return,
                    $actor,
                    $return->transaction_date->toDateString(),
                    $return->reference,
                );
                SaleReturnItem::query()->create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $line['sale_item_id'],
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                    'cogs_amount' => $line['cogs_amount'],
                ]);
            }

            $sale->due_amount = Money::of((string) $sale->due_amount)->sub($revenue)->amount();
            $sale->save();
            $this->alerts->customerDue($sale);

            $description = 'Sales return '.$return->reference;
            $journalLines = [
                [
                    'account_code' => ChartAccountCode::ProductSales,
                    'customer_id' => $sale->customer_id,
                    'debit' => $revenue,
                    'credit' => '0.00',
                    'description' => $description,
                ],
                [
                    'account_code' => ChartAccountCode::AccountsReceivable,
                    'customer_id' => $sale->customer_id,
                    'debit' => '0.00',
                    'credit' => $revenue,
                    'description' => $description,
                ],
            ];

            if (Money::of($cogs)->compare('0.00') === 1) {
                $journalLines[] = [
                    'account_code' => ChartAccountCode::Inventory,
                    'customer_id' => $sale->customer_id,
                    'debit' => $cogs,
                    'credit' => '0.00',
                    'description' => $description,
                ];
                $journalLines[] = [
                    'account_code' => ChartAccountCode::Cogs,
                    'customer_id' => $sale->customer_id,
                    'debit' => '0.00',
                    'credit' => $cogs,
                    'description' => $description,
                ];
            }

            $entry = $this->journal->post($return, $actor, $return->transaction_date->toDateString(), $description, $journalLines);
            $return->journal_entry_id = $entry->id;
            $return->save();
            $this->audit->record(AuditAction::Created, $return, null, [
                'reference' => $return->reference,
                'total' => $revenue,
            ], $actor);

            return $return->load('items');
        });
    }
}
