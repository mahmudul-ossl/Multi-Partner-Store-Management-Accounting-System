<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\DocumentStatus;
use App\Exceptions\ApprovalStateException;
use App\Models\Sale;

final class SaleRules
{
    public function assertCancellable(Sale $sale, ?int $exceptCancellationId = null): void
    {
        if ($sale->status !== DocumentStatus::Completed) {
            throw new ApprovalStateException('Only a completed sale can be cancelled.');
        }

        $open = [DocumentStatus::Pending->value, DocumentStatus::Approved->value];
        $cancellations = $sale->cancellations()->whereIn('status', $open);

        if ($exceptCancellationId !== null) {
            $cancellations->where('id', '!=', $exceptCancellationId);
        }

        if ($sale->returns()->exists()
            || $sale->payments()->exists()
            || $sale->refunds()->whereIn('status', $open)->exists()
            || $cancellations->exists()) {
            throw new ApprovalStateException('Return or refund this sale instead of cancelling it.');
        }
    }
}
