<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sales\CustomerPaymentService;
use App\Services\Sales\CustomerService;
use App\Services\Sales\SaleService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Example customers and sales go through the same services as the screens.
 * A discount above the configured threshold stays pending and does not move stock.
 */
class SalesExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (! SalesSetting::query()->exists()) {
            SalesSetting::query()->create(['large_discount_threshold' => '1000.00']);
        }

        if (Customer::query()->exists()) {
            return;
        }

        $seller = User::query()->where('email', 'sales@mpstore.test')->firstOrFail();
        $cash = FinancialAccount::query()->where('name', 'Cash')->firstOrFail();
        $warehouse = Warehouse::query()->where('name', 'Main Store')->firstOrFail();
        $customers = app(CustomerService::class);

        $rina = $customers->create($seller, [
            'name' => 'Rina Akter',
            'phone' => '01712001001',
            'address' => 'Dhanmondi, Dhaka',
        ]);
        $jamal = $customers->create($seller, [
            'name' => 'Jamal Uddin',
            'phone' => '01812001002',
            'address' => 'Mirpur, Dhaka',
        ]);
        $shahnaz = $customers->create($seller, [
            'name' => 'Shahnaz Parvin',
            'phone' => '01912001003',
            'address' => 'Uttara, Dhaka',
        ]);
        $customers->create($seller, [
            'name' => 'Kamal Hossain',
            'phone' => '01612001004',
            'address' => 'Mohammadpur, Dhaka',
            'notes' => 'Asks for wholesale prices on belts.',
        ]);

        $bifold = Product::query()->where('sku', 'WAL-BIFOLD')->firstOrFail();
        $slim = Product::query()->where('sku', 'WAL-SLIM')->firstOrFail();
        $messenger = Product::query()->where('sku', 'BAG-MESSENGER')->firstOrFail();
        $sales = app(SaleService::class);

        $sales->create($seller, [
            'customer_id' => $rina->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-02',
            'discount' => '0.00',
            'delivery' => '0.00',
            'paid_amount' => (string) $bifold->selling_price,
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Counter sale, paid in cash.',
            'items' => [[
                'product_id' => $bifold->id,
                'quantity' => '1',
                'unit_price' => (string) $bifold->selling_price,
            ]],
        ]);

        $partial = $sales->create($seller, [
            'customer_id' => $jamal->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-04',
            'discount' => '100.00',
            'delivery' => '50.00',
            'paid_amount' => '500.00',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Two slim wallets, delivery to Mirpur.',
            'items' => [[
                'product_id' => $slim->id,
                'quantity' => '2',
                'unit_price' => (string) $slim->selling_price,
            ]],
        ]);

        app(CustomerPaymentService::class)->create($seller, [
            'sale_id' => $partial->id,
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'amount' => '400.00',
            'payment_date' => '2026-06-08',
            'note' => 'Second instalment.',
        ]);

        $pending = $sales->create($seller, [
            'customer_id' => $shahnaz->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-10',
            'discount' => '1500.00',
            'delivery' => '0.00',
            'paid_amount' => '0.00',
            'note' => 'Staff discount above the threshold. Waiting for approval.',
            'items' => [[
                'product_id' => $messenger->id,
                'quantity' => '1',
                'unit_price' => (string) $messenger->selling_price,
            ]],
        ]);

        $partial->refresh();
        $pending->refresh();

        if ((string) $partial->due_amount !== '830.00' || $partial->status !== DocumentStatus::Completed) {
            throw new RuntimeException('The example partial sale did not settle as expected.');
        }

        if ($pending->status !== DocumentStatus::Pending || $pending->journal_entry_id !== null || Sale::query()->where('status', DocumentStatus::Completed)->count() !== 2) {
            throw new RuntimeException('The large-discount sale should stay pending with no journal.');
        }
    }
}
