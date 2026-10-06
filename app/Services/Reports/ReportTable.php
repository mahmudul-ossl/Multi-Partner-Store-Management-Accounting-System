<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\DocumentStatus;
use App\Enums\FinancialAccountType;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\JournalEntryLine;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerWithdrawal;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\LedgerReportService;
use App\Services\Finance\PartnerStatementService;
use App\Services\Inventory\InventoryService;
use App\Services\PartnerDirectory;
use App\Support\ChartAccountCode;
use App\Support\Costing;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Tabular reports share one filter, sort, and export shape.
 * Statement totals come from FinancialStatementService.
 */
final class ReportTable
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly LedgerReportService $ledgerReports,
        private readonly LedgerSlice $ledger,
        private readonly MonthlyReport $monthly,
        private readonly PartnerStatementService $partnerStatements,
        private readonly PartnerDirectory $partners,
        private readonly InventoryService $inventory,
        private readonly StockValuation $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    public function build(User $actor, string $key, array $filters): array
    {
        $built = match ($key) {
            'sales' => $this->sales($filters),
            'purchases' => $this->purchases($filters),
            'product-sales' => $this->productSales($filters),
            'stock' => $this->stock($filters),
            'stock-movements' => $this->movements($filters),
            'investments' => $this->investments($actor, $filters),
            'withdrawals' => $this->withdrawals($actor, $filters),
            'partner-statement' => $this->partnerStatement($actor, $filters),
            'partner-balances' => $this->partnerBalances($actor),
            'promotions' => $this->promotions(),
            'expenses' => $this->expenses($filters),
            'cash' => $this->accounts(FinancialAccountType::Cash, $filters),
            'bank' => $this->accounts(FinancialAccountType::Bank, $filters),
            'receivables' => $this->receivables($filters),
            'payables' => $this->payables($filters),
            'profit-loss' => $this->profitAndLoss($filters),
            'balance-sheet' => $this->balanceSheet($filters),
            'trial-balance' => $this->trialBalance($filters),
            'general-ledger' => $this->generalLedger($filters),
            'monthly' => $this->monthlyTable($filters),
            default => ['columns' => [], 'rows' => [], 'totals' => []],
        };

        return $this->present($built['columns'], $built['rows'], $filters, $built['totals']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function sales(array $filters): array
    {
        $status = $this->saleStatus($filters);
        $query = Sale::query()->with('customer')->orderByDesc('transaction_date');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $rows = $query->get()->map(fn (Sale $sale): array => $this->row([
            'date' => Format::date($sale->transaction_date),
            'reference' => $sale->reference,
            'customer' => $sale->customer?->name ?? '—',
            'total' => Money::of((string) $sale->total)->formatted(),
            'due' => Money::of((string) $sale->due_amount)->formatted(),
            'status' => $sale->status->label(),
        ], $sale->transaction_date->toDateString(), [
            'total' => (string) $sale->total,
            'due' => (string) $sale->due_amount,
        ]))->all();

        $pending = '0.00';
        $pendingSales = Sale::query()->where('status', DocumentStatus::Pending->value);
        $from = $this->from($filters);
        $to = $this->to($filters);

        if ($from !== null) {
            $pendingSales->whereDate('transaction_date', '>=', $from);
        }

        if ($to !== null) {
            $pendingSales->whereDate('transaction_date', '<=', $to);
        }

        foreach ($pendingSales->pluck('total') as $amount) {
            $pending = Money::of($pending)->add((string) $amount)->amount();
        }

        return [
            'columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'customer' => 'Customer', 'total' => 'Total', 'due' => 'Due', 'status' => 'Status']),
            'rows' => $rows,
            'totals' => [
                $this->total('Completed sales (ledger)', $this->ledger->net(ChartAccountCode::ProductSales, $from, $to, true)),
                $this->total('Pending', $pending),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function purchases(array $filters): array
    {
        $rows = Purchase::query()->with('supplier')->orderByDesc('transaction_date')->get()->map(fn (Purchase $purchase): array => $this->row([
            'date' => Format::date($purchase->transaction_date),
            'reference' => $purchase->reference,
            'supplier' => $purchase->supplier?->name ?? '—',
            'total' => Money::of((string) $purchase->total)->formatted(),
            'due' => Money::of((string) $purchase->due_amount)->formatted(),
            'status' => $purchase->status->label(),
        ], $purchase->transaction_date->toDateString(), [
            'total' => (string) $purchase->total,
        ]))->all();

        return [
            'columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'supplier' => 'Supplier', 'total' => 'Total', 'due' => 'Due', 'status' => 'Status']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger purchases', $this->ledger->sourceNet(ChartAccountCode::Inventory, [Purchase::class, PurchaseReturn::class], $this->from($filters), $this->to($filters), false))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function productSales(array $filters): array
    {
        $from = $this->from($filters);
        $to = $this->to($filters);
        $grouped = [];

        $items = SaleItem::query()->with('product', 'sale')->whereHas('sale', function ($query) use ($from, $to): void {
            $query->where('status', DocumentStatus::Completed->value);
            if ($from !== null) {
                $query->whereDate('transaction_date', '>=', $from);
            }
            if ($to !== null) {
                $query->whereDate('transaction_date', '<=', $to);
            }
        })->get();

        foreach ($items as $item) {
            $id = (int) $item->product_id;
            if (! isset($grouped[$id])) {
                $grouped[$id] = ['name' => $item->product?->name ?? '—', 'sku' => $item->product?->sku ?? '—', 'quantity' => '0.000', 'amount' => '0.00'];
            }
            $grouped[$id]['quantity'] = bcadd($grouped[$id]['quantity'], Costing::quantity((string) $item->quantity), 3);
            $grouped[$id]['amount'] = Money::of($grouped[$id]['amount'])->add((string) $item->net_amount)->amount();
        }

        $rows = [];
        foreach ($grouped as $row) {
            $rows[] = $this->row([
                'sku' => $row['sku'],
                'product' => $row['name'],
                'quantity' => $row['quantity'],
                'amount' => Money::of($row['amount'])->formatted(),
            ], null, ['quantity' => $row['quantity'], 'amount' => $row['amount']]);
        }

        return [
            'columns' => $this->columns(['sku' => 'SKU', 'product' => 'Product', 'quantity' => 'Quantity', 'amount' => 'Net amount']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger product sales', $this->ledger->net(ChartAccountCode::ProductSales, $from, $to, true))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function stock(array $filters): array
    {
        $asOf = $this->to($filters);
        $rows = [];
        $total = '0.00';

        foreach (Product::query()->orderBy('name')->get() as $product) {
            if ($asOf === null) {
                $quantity = $this->inventory->onHand($product);
                $average = Costing::cost((string) $product->average_cost);
            } else {
                $position = $this->stock->position($product, $asOf);
                $quantity = $position['quantity'];
                $average = $position['average'];
            }
            $value = Costing::lineTotal($quantity, $average);
            $total = Money::of($total)->add($value)->amount();
            $rows[] = $this->row([
                'sku' => $product->sku,
                'product' => $product->name,
                'on_hand' => $quantity,
                'average_cost' => $average,
                'value' => Money::of($value)->formatted(),
            ], null, ['on_hand' => $quantity, 'value' => $value]);
        }

        return [
            'columns' => $this->columns(['sku' => 'SKU', 'product' => 'Product', 'on_hand' => 'On hand', 'average_cost' => 'Average cost', 'value' => 'Value']),
            'rows' => $rows,
            'totals' => [$this->total('Stock value', $total)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function movements(array $filters): array
    {
        $rows = StockMovement::query()->with('product', 'warehouse')->orderByDesc('moved_on')->orderByDesc('id')->get()
            ->map(fn (StockMovement $movement): array => $this->row([
                'date' => Format::date($movement->moved_on),
                'product' => $movement->product?->name ?? '—',
                'warehouse' => $movement->warehouse?->name ?? '—',
                'type' => $movement->movement_type->label(),
                'quantity' => Costing::quantity((string) $movement->quantity),
                'unit_cost' => Costing::cost((string) $movement->unit_cost),
            ], $movement->moved_on->toDateString(), [
                'quantity' => Costing::quantity((string) $movement->quantity),
            ]))->all();

        return [
            'columns' => $this->columns(['date' => 'Date', 'product' => 'Product', 'warehouse' => 'Warehouse', 'type' => 'Type', 'quantity' => 'Quantity', 'unit_cost' => 'Unit cost']),
            'rows' => $rows,
            'totals' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function investments(User $actor, array $filters): array
    {
        $query = PartnerInvestment::query()->with('partner');
        $this->limitPartner($actor, $query);

        $rows = $query->orderByDesc('transaction_date')->get()->map(fn (PartnerInvestment $row): array => $this->row([
            'date' => Format::date($row->transaction_date),
            'reference' => $row->reference,
            'partner' => $row->partner?->name ?? '—',
            'amount' => Money::of((string) $row->amount)->formatted(),
            'status' => $row->status->label(),
        ], $row->transaction_date->toDateString(), ['amount' => (string) $row->amount]))->all();

        return [
            'columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'partner' => 'Partner', 'amount' => 'Amount', 'status' => 'Status']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger investment', $this->ledger->sourceNet(ChartAccountCode::PartnerCapital, [PartnerInvestment::class], $this->from($filters), $this->to($filters), true))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function withdrawals(User $actor, array $filters): array
    {
        $query = PartnerWithdrawal::query()->with('partner');
        $this->limitPartner($actor, $query);
        $rows = $query->orderByDesc('transaction_date')->get()->map(fn (PartnerWithdrawal $row): array => $this->row([
            'date' => Format::date($row->transaction_date),
            'reference' => $row->reference,
            'partner' => $row->partner?->name ?? '—',
            'amount' => Money::of((string) $row->amount)->formatted(),
            'status' => $row->status->label(),
        ], $row->transaction_date->toDateString(), ['amount' => (string) $row->amount]))->all();

        return [
            'columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'partner' => 'Partner', 'amount' => 'Amount', 'status' => 'Status']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger withdrawals', $this->ledger->sourceNet(ChartAccountCode::PartnerWithdrawals, [PartnerWithdrawal::class], $this->from($filters), $this->to($filters), false))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function partnerStatement(User $actor, array $filters): array
    {
        $partner = $this->statementPartner($actor, $filters);
        if (! $partner instanceof Partner) {
            return ['columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'description' => 'Description', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance']), 'rows' => [], 'totals' => []];
        }

        $rows = [];
        foreach ($this->partnerStatements->statement($partner) as $line) {
            $raw = Carbon::createFromFormat((string) config('mpstore.date_format', 'd-M-Y'), (string) $line['date']);
            $rows[] = $this->row([
                'date' => $line['date'],
                'reference' => $line['reference'],
                'description' => $line['description'],
                'debit' => $line['debit_formatted'],
                'credit' => $line['credit_formatted'],
                'balance' => $line['running_balance_formatted'],
            ], $raw ? $raw->toDateString() : null, [
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'balance' => $line['running_balance'],
            ]);
        }

        return [
            'columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'description' => 'Description', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance']),
            'rows' => $rows,
            'totals' => [$this->total('Current capital', $this->partnerStatements->capitalBalance($partner))],
        ];
    }

    /**
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function partnerBalances(User $actor): array
    {
        $rows = [];
        $total = '0.00';
        $partners = $this->partners->restrictsToOwnRecord($actor)
            ? Partner::query()->whereKey($actor->partner?->id)->get()
            : Partner::query()->orderBy('name')->get();

        foreach ($partners as $partner) {
            $position = $this->partnerStatements->position($partner);
            $capital = $position['current_capital']['amount'];
            $total = Money::of($total)->add($capital)->amount();
            $rows[] = $this->row([
                'code' => $partner->partner_code,
                'partner' => $partner->name,
                'investment' => $position['investment']['formatted'],
                'withdrawal' => $position['withdrawal']['formatted'],
                'profit_share' => $position['profit_share']['formatted'],
                'capital' => $position['current_capital']['formatted'],
            ], null, ['capital' => $capital]);
        }

        return [
            'columns' => $this->columns(['code' => 'Code', 'partner' => 'Partner', 'investment' => 'Investment', 'withdrawal' => 'Withdrawal', 'profit_share' => 'Profit share', 'capital' => 'Capital']),
            'rows' => $rows,
            'totals' => [$this->total('Capital', $total)],
        ];
    }

    /**
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function promotions(): array
    {
        $rows = Promotion::query()->orderBy('name')->get()->map(fn (Promotion $promotion): array => $this->row([
            'name' => $promotion->name,
            'platform' => $promotion->platform->label(),
            'budget' => Money::of((string) $promotion->budget)->formatted(),
            'actual' => Money::of((string) $promotion->actual_amount)->formatted(),
            'status' => $promotion->status->label(),
        ], $promotion->starts_on?->toDateString(), [
            'budget' => (string) $promotion->budget,
            'actual' => (string) $promotion->actual_amount,
        ]))->all();

        return [
            'columns' => $this->columns(['name' => 'Name', 'platform' => 'Platform', 'budget' => 'Budget', 'actual' => 'Actual', 'status' => 'Status']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger promotion expense', $this->ledger->net(ChartAccountCode::PromotionExpense, null, null, false))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function expenses(array $filters): array
    {
        $rows = Expense::query()->with('partner')->orderByDesc('transaction_date')->get()->map(fn (Expense $expense): array => $this->row([
            'date' => Format::date($expense->transaction_date),
            'reference' => $expense->reference,
            'category' => $expense->category->label(),
            'partner' => $expense->partner?->name ?? 'Business',
            'amount' => Money::of((string) $expense->amount)->formatted(),
            'status' => $expense->status->label(),
        ], $expense->transaction_date->toDateString(), ['amount' => (string) $expense->amount]))->all();
        $profit = $this->statements->profitAndLoss($this->from($filters) ?? '2000-01-01', $this->to($filters) ?? now()->toDateString());

        return [
            'columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'category' => 'Category', 'partner' => 'Paid by', 'amount' => 'Amount', 'status' => 'Status']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger expenses', $profit['total_expenses']['amount'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function accounts(FinancialAccountType $type, array $filters): array
    {
        $asOf = $this->to($filters) ?? now()->toDateString();
        $rows = [];
        $total = '0.00';

        foreach (FinancialAccount::query()->where('type', $type)->orderBy('name')->get() as $account) {
            $balance = '0.00';
            $lines = JournalEntryLine::query()
                ->where('financial_account_id', $account->id)
                ->whereHas('entry', function ($query) use ($asOf): void {
                    $query->whereIn('status', [DocumentStatus::Completed->value, DocumentStatus::Reversed->value])
                        ->whereDate('entry_date', '<=', $asOf);
                })->get(['debit', 'credit']);

            foreach ($lines as $line) {
                $balance = Money::of($balance)->add((string) $line->debit)->sub((string) $line->credit)->amount();
            }

            $total = Money::of($total)->add($balance)->amount();
            $rows[] = $this->row([
                'account' => $account->name,
                'balance' => Money::of($balance)->formatted(),
            ], null, ['balance' => $balance]);
        }

        return [
            'columns' => $this->columns(['account' => 'Account', 'balance' => 'Ledger balance']),
            'rows' => $rows,
            'totals' => [$this->total('Total', $total)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function receivables(array $filters): array
    {
        $rows = [];
        foreach (Customer::query()->orderBy('name')->get() as $customer) {
            $due = '0.00';
            $sales = Sale::query()->where('customer_id', $customer->id)->where('status', DocumentStatus::Completed);
            if ($this->to($filters) !== null) {
                $sales->whereDate('transaction_date', '<=', $this->to($filters));
            }
            foreach ($sales->pluck('due_amount') as $amount) {
                $due = Money::of($due)->add((string) $amount)->amount();
            }
            if (Money::of($due)->isZero()) {
                continue;
            }
            $rows[] = $this->row([
                'customer' => $customer->name,
                'due' => Money::of($due)->formatted(),
            ], null, ['due' => $due]);
        }

        return [
            'columns' => $this->columns(['customer' => 'Customer', 'due' => 'Due']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger receivables', $this->ledger->balanceAsOf(ChartAccountCode::AccountsReceivable, $this->to($filters) ?? now()->toDateString(), true))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function payables(array $filters): array
    {
        $rows = [];
        foreach (Supplier::query()->orderBy('name')->get() as $supplier) {
            $due = '0.00';
            $purchases = Purchase::query()->where('supplier_id', $supplier->id)->where('status', DocumentStatus::Approved);
            if ($this->to($filters) !== null) {
                $purchases->whereDate('transaction_date', '<=', $this->to($filters));
            }
            foreach ($purchases->pluck('due_amount') as $amount) {
                $due = Money::of($due)->add((string) $amount)->amount();
            }
            if (Money::of($due)->isZero()) {
                continue;
            }
            $rows[] = $this->row([
                'supplier' => $supplier->name,
                'due' => Money::of($due)->formatted(),
            ], null, ['due' => $due]);
        }

        return [
            'columns' => $this->columns(['supplier' => 'Supplier', 'due' => 'Due']),
            'rows' => $rows,
            'totals' => [$this->total('Ledger payables', $this->ledger->balanceAsOf(ChartAccountCode::AccountsPayable, $this->to($filters) ?? now()->toDateString(), false))],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function profitAndLoss(array $filters): array
    {
        $report = $this->statements->profitAndLoss($this->from($filters) ?? '2000-01-01', $this->to($filters) ?? now()->toDateString());
        $rows = [];
        foreach ($report['revenue'] as $line) {
            $rows[] = $this->moneyRow('Revenue', $line);
        }
        $rows[] = $this->moneyRow('Revenue', ['code' => '', 'name' => 'Total revenue', 'amount' => $report['total_revenue']['amount'], 'formatted' => $report['total_revenue']['formatted']]);
        foreach ($report['cogs'] as $line) {
            $rows[] = $this->moneyRow('COGS', $line);
        }
        $rows[] = $this->moneyRow('Profit', ['code' => '', 'name' => 'Gross profit', 'amount' => $report['gross_profit']['amount'], 'formatted' => $report['gross_profit']['formatted']]);
        foreach ($report['expenses'] as $line) {
            $rows[] = $this->moneyRow('Expense', $line);
        }
        $rows[] = $this->moneyRow('Profit', ['code' => '', 'name' => 'Net profit', 'amount' => $report['net_profit']['amount'], 'formatted' => $report['net_profit']['formatted']]);

        return [
            'columns' => $this->columns(['section' => 'Section', 'account' => 'Account', 'amount' => 'Amount']),
            'rows' => $rows,
            'totals' => [
                $this->total('Gross profit', $report['gross_profit']['amount']),
                $this->total('Net profit', $report['net_profit']['amount']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function balanceSheet(array $filters): array
    {
        $report = $this->statements->balanceSheet($this->to($filters) ?? now()->toDateString());
        $rows = [];
        foreach (['Assets' => $report['assets']['lines'], 'Liabilities' => $report['liabilities']['lines'], 'Equity' => $report['equity']['lines']] as $section => $lines) {
            foreach ($lines as $line) {
                $rows[] = $this->moneyRow($section, $line);
            }
        }
        $rows[] = $this->moneyRow('Equity', ['code' => '', 'name' => 'Current earnings', 'amount' => $report['equity']['current_earnings']['amount'], 'formatted' => $report['equity']['current_earnings']['formatted']]);

        return [
            'columns' => $this->columns(['section' => 'Section', 'account' => 'Account', 'amount' => 'Amount']),
            'rows' => $rows,
            'totals' => [
                $this->total('Assets', $report['assets']['total']['amount']),
                $this->total('Liabilities and equity', $report['liabilities_and_equity']['amount']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function trialBalance(array $filters): array
    {
        $report = $this->statements->trialBalance($this->to($filters) ?? now()->toDateString());
        $rows = [];
        foreach ($report['rows'] as $row) {
            $rows[] = $this->row([
                'account' => $row['code'].' '.$row['name'],
                'debit' => $row['debit']['formatted'],
                'credit' => $row['credit']['formatted'],
            ], null, [
                'debit' => $row['debit']['amount'],
                'credit' => $row['credit']['amount'],
            ]);
        }

        return [
            'columns' => $this->columns(['account' => 'Account', 'debit' => 'Debit', 'credit' => 'Credit']),
            'rows' => $rows,
            'totals' => [
                $this->total('Debit', $report['debit_total']['amount']),
                $this->total('Credit', $report['credit_total']['amount']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function generalLedger(array $filters): array
    {
        $account = ChartOfAccount::query()->find((int) ($filters['account'] ?? 0));
        if (! $account instanceof ChartOfAccount) {
            return ['columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'description' => 'Description', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance']), 'rows' => [], 'totals' => []];
        }

        $report = $this->ledgerReports->generalLedger($account, $this->from($filters), $this->to($filters));
        $rows = [];
        foreach ($report['lines'] as $row) {
            $rows[] = $this->row([
                'date' => (string) $row['date'],
                'reference' => (string) $row['reference'],
                'description' => (string) $row['description'],
                'debit' => $row['debit']['formatted'],
                'credit' => $row['credit']['formatted'],
                'balance' => $row['running_balance']['formatted'],
            ], null, [
                'debit' => $row['debit']['amount'],
                'credit' => $row['credit']['amount'],
            ]);
        }

        return [
            'columns' => $this->columns(['date' => 'Date', 'reference' => 'Reference', 'description' => 'Description', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance']),
            'rows' => $rows,
            'totals' => [$this->total('Closing', $report['closing_balance']['amount'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function monthlyTable(array $filters): array
    {
        $report = $this->monthly->build($this->from($filters) ?? now()->startOfMonth()->toDateString(), $this->to($filters) ?? now()->endOfMonth()->toDateString());
        $rows = [];
        foreach ($report['figures'] as $figure) {
            $rows[] = $this->row([
                'metric' => $figure['label'],
                'amount' => $figure['formatted'],
            ], null, ['amount' => $figure['amount']]);
        }
        foreach ($report['expenses'] as $expense) {
            $rows[] = $this->row([
                'metric' => 'Expense · '.$expense['name'],
                'amount' => $expense['formatted'],
            ], null, ['amount' => $expense['amount']]);
        }

        return [
            'columns' => $this->columns(['metric' => 'Metric', 'amount' => 'Amount']),
            'rows' => $rows,
            'totals' => [
                $this->total('Gross profit', $report['gross_profit']['amount']),
                $this->total('Net profit', $report['net_profit']['amount']),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string}>
     */
    private function columns(array $labels): array
    {
        $columns = [];
        foreach ($labels as $key => $label) {
            $columns[] = ['key' => $key, 'label' => $label];
        }

        return $columns;
    }

    /**
     * @param  array<string, string>  $display
     * @param  array<string, string>  $sort
     * @return array<string, mixed>
     */
    private function row(array $display, ?string $date, array $sort = []): array
    {
        return $display + ['_date' => $date, '_sort' => $display + $sort];
    }

    /**
     * @param  array{code: string, name: string, amount: string, formatted: string}  $line
     * @return array<string, mixed>
     */
    private function moneyRow(string $section, array $line): array
    {
        $account = trim($line['code'].' '.$line['name']);

        return $this->row([
            'section' => $section,
            'account' => $account,
            'amount' => $line['formatted'],
        ], null, ['amount' => $line['amount']]);
    }

    /**
     * @return array{label: string, amount: string, formatted: string}
     */
    private function total(string $label, string $amount): array
    {
        return ['label' => $label, 'amount' => $amount, 'formatted' => Money::of($amount)->formatted()];
    }

    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @param  list<array{label: string, amount: string, formatted: string}>  $totals
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, totals: list<array{label: string, amount: string, formatted: string}>}
     */
    private function present(array $columns, array $rows, array $filters, array $totals): array
    {
        $from = $this->from($filters);
        $to = $this->to($filters);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $keys = array_column($columns, 'key');
        $sort = (string) ($filters['sort'] ?? ($keys[0] ?? ''));
        if (! in_array($sort, $keys, true)) {
            $sort = $keys[0] ?? '';
        }
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $rows = array_values(array_filter($rows, function (array $row) use ($from, $to, $search, $keys): bool {
            $date = $row['_date'] ?? null;
            if (is_string($date) && $date !== '') {
                if ($from !== null && $date < $from) {
                    return false;
                }
                if ($to !== null && $date > $to) {
                    return false;
                }
            }
            if ($search === '') {
                return true;
            }
            $haystack = '';
            foreach ($keys as $key) {
                $haystack .= ' '.mb_strtolower((string) ($row[$key] ?? ''));
            }

            return str_contains($haystack, $search);
        }));

        usort($rows, function (array $left, array $right) use ($sort, $direction): int {
            $result = strnatcasecmp((string) ($left['_sort'][$sort] ?? ''), (string) ($right['_sort'][$sort] ?? ''));

            return $direction === 'desc' ? -$result : $result;
        });

        $clean = [];
        foreach ($rows as $row) {
            unset($row['_date'], $row['_sort']);
            $clean[] = $row;
        }

        return ['columns' => $columns, 'rows' => $clean, 'totals' => $totals];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<PartnerInvestment>|\\Illuminate\Database\Eloquent\Builder<PartnerWithdrawal>  $query
     */
    private function limitPartner(User $actor, $query): void
    {
        if ($this->partners->restrictsToOwnRecord($actor)) {
            $query->where('partner_id', $actor->partner?->id);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function statementPartner(User $actor, array $filters): ?Partner
    {
        if ($this->partners->restrictsToOwnRecord($actor)) {
            return $actor->partner;
        }

        $id = (int) ($filters['partner'] ?? 0);

        return $id > 0 ? Partner::query()->find($id) : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function from(array $filters): ?string
    {
        $from = (string) ($filters['from'] ?? '');

        return $from !== '' ? $from : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function to(array $filters): ?string
    {
        $to = (string) ($filters['to'] ?? '');

        return $to !== '' ? $to : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function saleStatus(array $filters): string
    {
        $status = (string) ($filters['status'] ?? '');
        $allowed = ['completed', 'pending', 'cancelled', 'all'];

        return in_array($status, $allowed, true) ? $status : DocumentStatus::Completed->value;
    }
}
