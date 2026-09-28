<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\OperationalAlert;
use App\Support\Money;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Writes one unread database notification per user and subject.
 */
final class OperationalNotifier
{
    public function lowStock(Product $product, string $onHand): void
    {
        $this->notify(PermissionName::StockView, 'low_stock', (int) $product->id, 'Low stock', $product->name.' is at '.$onHand.' against a reorder level of '.$product->reorder_level.'.', '/inventory/stock/low');
    }

    public function customerDue(Sale $sale): void
    {
        $sale->loadMissing('customer');
        $this->syncDue(
            PermissionName::SaleView,
            'customer_due',
            (int) $sale->id,
            'Customer payment due',
            ($sale->customer?->name ?? 'A customer').' still owes '.Money::of((string) $sale->due_amount)->formatted().' on '.$sale->reference.'.',
            '/sales/orders/'.$sale->id,
            (string) $sale->due_amount,
        );
    }

    public function supplierDue(Purchase $purchase): void
    {
        $purchase->loadMissing('supplier');
        $this->syncDue(
            PermissionName::PurchaseView,
            'supplier_due',
            (int) $purchase->id,
            'Supplier payment due',
            ($purchase->supplier?->name ?? 'A supplier').' is owed '.Money::of((string) $purchase->due_amount)->formatted().' on '.$purchase->reference.'.',
            '/inventory/purchases/'.$purchase->id,
            (string) $purchase->due_amount,
        );
    }

    private function syncDue(PermissionName $permission, string $kind, int $subjectId, string $title, string $message, string $url, string $due): void
    {
        $existing = DatabaseNotification::query()
            ->where('data->kind', $kind)
            ->where('data->subject_id', $subjectId)
            ->get();

        if (Money::of($due)->compare('0.00') !== 1) {
            foreach ($existing as $notification) {
                if ($notification->read_at === null) {
                    $notification->markAsRead();
                }
            }

            return;
        }

        if ($existing->isEmpty()) {
            $this->notify($permission, $kind, $subjectId, $title, $message, $url);

            return;
        }

        foreach ($existing as $notification) {
            $data = $notification->data;
            if (($data['message'] ?? '') === $message) {
                continue;
            }

            $data['message'] = $message;
            $data['title'] = $title;
            $notification->data = $data;
            $notification->read_at = null;
            $notification->save();
        }
    }

    private function notify(PermissionName $permission, string $kind, int $subjectId, string $title, string $message, string $url): void
    {
        $alert = new OperationalAlert($kind, $subjectId, $title, $message, $url);

        foreach (User::permission($permission->value)->where('is_active', true)->get() as $user) {
            $exists = $user->notifications()
                ->where('data->kind', $kind)
                ->where('data->subject_id', $subjectId)
                ->exists();

            if ($exists) {
                continue;
            }

            $user->notify($alert);
        }
    }
}
