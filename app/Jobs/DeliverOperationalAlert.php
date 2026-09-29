<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PermissionName;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\OperationalAlert;
use App\Services\Inventory\InventoryService;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes one operational alert per user and subject from the balance at delivery time.
 */
class DeliverOperationalAlert implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $kind,
        public int $subjectId,
    ) {
        $this->afterCommit();
    }

    public function handle(InventoryService $inventory): void
    {
        $state = $this->state($inventory);

        DB::transaction(function () use ($state): void {
            if ($state === null) {
                DatabaseNotification::query()
                    ->where('alert_key', $this->key())
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);

                return;
            }

            $users = User::permission($state['permission'])->where('is_active', true)->get();

            foreach ($users as $user) {
                $this->upsert($user, $state);
            }
        });
    }

    /**
     * @param  array{permission: string, title: string, message: string, url: string}  $state
     */
    private function upsert(User $user, array $state): void
    {
        $existing = $user->notifications()
            ->where('alert_key', $this->key())
            ->lockForUpdate()
            ->first();

        $data = [
            'kind' => $this->kind,
            'subject_id' => $this->subjectId,
            'title' => $state['title'],
            'message' => $state['message'],
            'url' => $state['url'],
        ];

        if ($existing instanceof DatabaseNotification) {
            if (($existing->data['message'] ?? '') === $state['message']) {
                return;
            }

            $existing->data = $data;
            $existing->read_at = null;
            $existing->save();

            return;
        }

        try {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => OperationalAlert::class,
                'data' => $data,
                'alert_key' => $this->key(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $row = $user->notifications()->where('alert_key', $this->key())->first();
            if ($row instanceof DatabaseNotification) {
                $row->data = $data;
                $row->read_at = null;
                $row->save();
            }
        }
    }

    /**
     * @return array{permission: string, title: string, message: string, url: string}|null
     */
    private function state(InventoryService $inventory): ?array
    {
        if ($this->kind === 'customer_due') {
            $sale = Sale::query()->with('customer')->find($this->subjectId);
            if ($sale === null || Money::of((string) $sale->due_amount)->compare('0.00') !== 1) {
                return null;
            }

            return [
                'permission' => PermissionName::SaleView->value,
                'title' => 'Customer payment due',
                'message' => ($sale->customer?->name ?? 'A customer').' still owes '.Money::of((string) $sale->due_amount)->formatted().' on '.$sale->reference.'.',
                'url' => '/sales/orders/'.$sale->id,
            ];
        }

        if ($this->kind === 'supplier_due') {
            $purchase = Purchase::query()->with('supplier')->find($this->subjectId);
            if ($purchase === null || Money::of((string) $purchase->due_amount)->compare('0.00') !== 1) {
                return null;
            }

            return [
                'permission' => PermissionName::PurchaseView->value,
                'title' => 'Supplier payment due',
                'message' => ($purchase->supplier?->name ?? 'A supplier').' is owed '.Money::of((string) $purchase->due_amount)->formatted().' on '.$purchase->reference.'.',
                'url' => '/inventory/purchases/'.$purchase->id,
            ];
        }

        $product = Product::query()->find($this->subjectId);
        if ($product === null || ! $inventory->isLow($product)) {
            return null;
        }

        return [
            'permission' => PermissionName::StockView->value,
            'title' => 'Low stock',
            'message' => $product->name.' is at '.$inventory->onHand($product).' against a reorder level of '.$product->reorder_level.'.',
            'url' => '/inventory/stock/low',
        ];
    }

    private function key(): string
    {
        return $this->kind.':'.$this->subjectId;
    }
}
