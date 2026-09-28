<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DeliverOperationalAlert;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;

/**
 * Queues one delivery job per subject. The job reads the live balance when it runs.
 */
final class OperationalNotifier
{
    public function lowStock(Product $product): void
    {
        DeliverOperationalAlert::dispatch('low_stock', (int) $product->id);
    }

    public function customerDue(Sale $sale): void
    {
        DeliverOperationalAlert::dispatch('customer_due', (int) $sale->id);
    }

    public function supplierDue(Purchase $purchase): void
    {
        DeliverOperationalAlert::dispatch('supplier_due', (int) $purchase->id);
    }
}
