<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Inventory\CatalogService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Purchasing\PurchaseReturnService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Purchasing\SupplierPaymentService;
use Illuminate\Database\Seeder;

/**
 * Example leather-goods catalog, opening stock, and one purchase cycle.
 * Documents go through the same services as the screens, so stock movements
 * and journals stay in step.
 */
class InventoryExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $inventory = User::query()->where('email', 'inventory@mpstore.test')->firstOrFail();
        $accountant = User::query()->where('email', 'accountant@mpstore.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $cash = FinancialAccount::query()->where('name', 'Cash')->firstOrFail();

        $catalog = app(CatalogService::class);
        $categories = [];

        foreach ([
            'Wallets' => 'Folding wallets and purses.',
            'Bags' => 'Bags and sleeves.',
            'Belts' => 'Leather belts.',
            'Card Holders' => 'Card and ID holders.',
            'Accessories' => 'Small leather goods.',
        ] as $name => $description) {
            $categories[$name] = $catalog->createCategory($inventory, [
                'name' => $name,
                'description' => $description,
            ])->id;
        }

        $brands = [];

        foreach (['Gulshan Hide', 'Old Dhaka Leather', 'Padma Craft'] as $name) {
            $brands[$name] = $catalog->createBrand($inventory, ['name' => $name])->id;
        }

        $unitId = $catalog->createUnit($inventory, ['name' => 'Piece', 'abbreviation' => 'pc'])->id;
        $warehouseId = $catalog->createWarehouse($inventory, [
            'name' => 'Main Store',
            'address' => '12 Gulshan Avenue, Dhaka',
            'is_default' => true,
        ])->id;

        $suppliers = [];

        foreach ([
            ['Dhaka Leather House', 'Dhaka Leather House Ltd.', '01711001001', '12 Gulshan, Dhaka', 'Farzana Begum', 'Purchasing'],
            ['Chittagong Hide Co.', 'Chittagong Hide Co.', '01811001002', 'Agrabad, Chittagong', 'Hasan Mia', 'Sales'],
            ['Rajshahi Craft Supply', 'Rajshahi Craft Supply', '01911001003', 'Shaheb Bazar, Rajshahi', 'Nusrat Ara', 'Accounts'],
            ['Sylhet Belt Works', 'Sylhet Belt Works', '01611001004', 'Zindabazar, Sylhet', 'Tanvir Rahman', 'Workshop'],
            ['Khulna Tannery', 'Khulna Tannery', '01511001005', 'Khalishpur, Khulna', 'Shila Khatun', 'Dispatch'],
        ] as [$name, $company, $phone, $address, $contact, $role]) {
            $supplier = $catalog->createSupplier($inventory, [
                'name' => $name,
                'company_name' => $company,
                'phone' => $phone,
                'address' => $address,
            ]);
            $catalog->addContact($supplier, $inventory, [
                'name' => $contact,
                'phone' => $phone,
                'role' => $role,
            ]);
            $suppliers[$name] = $supplier->id;
        }

        $products = [];

        foreach ($this->products() as $row) {
            $product = $catalog->createProduct($inventory, [
                'sku' => $row['sku'],
                'barcode' => $row['barcode'],
                'name' => $row['name'],
                'category_id' => $categories[$row['category']],
                'brand_id' => $brands[$row['brand']],
                'supplier_id' => $suppliers[$row['supplier']],
                'unit_id' => $unitId,
                'purchase_price' => $row['purchase_price'],
                'selling_price' => $row['selling_price'],
                'wholesale_price' => $row['wholesale_price'],
                'minimum_stock' => $row['minimum_stock'],
                'reorder_level' => $row['reorder_level'],
                'status' => 'active',
                'description' => $row['description'],
            ]);
            $products[$row['sku']] = $product;

            $opening = app(StockAdjustmentService::class)->create($inventory, [
                'kind' => 'opening',
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'quantity' => $row['opening'],
                'unit_cost' => $row['opening_cost'],
                'transaction_date' => '2026-05-01',
                'reason' => 'Opening stock for '.$row['name'],
            ]);
            app(ApprovalService::class)->approve($opening->approvalRequest, $admin, 'Opening stock accepted.');
        }

        $approvals = app(ApprovalService::class);
        $bifold = $products['WAL-BIFOLD'];

        $purchase = app(PurchaseService::class)->create($inventory, [
            'supplier_id' => $suppliers['Dhaka Leather House'],
            'warehouse_id' => $warehouseId,
            'transaction_date' => '2026-05-10',
            'paid_amount' => '1200.00',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'note' => 'Partial payment on bifold wallets.',
            'items' => [[
                'product_id' => $bifold->id,
                'quantity' => '4',
                'unit_cost' => '800.0000',
            ]],
        ]);
        $approvals->approve($purchase->approvalRequest, $accountant, 'Goods received.');

        $return = app(PurchaseReturnService::class)->create($inventory, [
            'purchase_id' => $purchase->id,
            'transaction_date' => '2026-05-12',
            'note' => 'One wallet returned, stitching fault.',
            'items' => [[
                'purchase_item_id' => $purchase->items->first()->id,
                'quantity' => '1',
            ]],
        ]);
        $approvals->approve($return->approvalRequest, $accountant, 'Return accepted.');

        $payment = app(SupplierPaymentService::class)->create($inventory, [
            'supplier_id' => $suppliers['Dhaka Leather House'],
            'purchase_id' => $purchase->id,
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'amount' => '1200.00',
            'payment_date' => '2026-05-15',
            'note' => 'Balance after the return.',
        ]);
        $approvals->approve($payment->approvalRequest, $accountant, 'Paid the remaining due.');

        app(PurchaseService::class)->create($inventory, [
            'supplier_id' => $suppliers['Chittagong Hide Co.'],
            'warehouse_id' => $warehouseId,
            'transaction_date' => '2026-05-18',
            'paid_amount' => '0.00',
            'note' => 'Waiting for the tote delivery.',
            'items' => [[
                'product_id' => $products['BAG-TOTE']->id,
                'quantity' => '2',
                'unit_cost' => '2500.0000',
            ]],
        ]);

        $purchase->refresh();
        if ((string) $purchase->due_amount !== '0.00' || Purchase::query()->where('status', 'pending')->count() !== 1) {
            throw new \RuntimeException('The example purchase cycle did not settle as expected.');
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function products(): array
    {
        return [
            $this->row('WAL-BIFOLD', '8901000000011', 'Classic Bifold Wallet', 'Wallets', 'Gulshan Hide', 'Dhaka Leather House', '750.00', '1450.00', '1100.00', '4', '6', '8', '750.0000', 'Full-grain bifold with six card slots.'),
            $this->row('WAL-SLIM', '8901000000012', 'Slim Card Wallet', 'Wallets', 'Old Dhaka Leather', 'Dhaka Leather House', '420.00', '890.00', '650.00', '3', '4', '12', '420.0000', 'Front-pocket wallet, four cards.'),
            $this->row('WAL-COIN', '8901000000013', 'Zip Coin Purse', 'Wallets', 'Padma Craft', 'Rajshahi Craft Supply', '280.00', '620.00', '450.00', '5', '6', '15', '280.0000', 'Small zip purse for coins.'),
            $this->row('WAL-LADIES', '8901000000014', 'Long Ladies Wallet', 'Wallets', 'Gulshan Hide', 'Dhaka Leather House', '680.00', '1350.00', '980.00', '3', '4', '6', '680.0000', 'Long wallet with a zip compartment.'),
            $this->row('WAL-CLIP', '8901000000015', 'Money Clip Wallet', 'Wallets', 'Old Dhaka Leather', 'Khulna Tannery', '510.00', '1100.00', '800.00', '2', '3', '8', '510.0000', 'Clip wallet with a note pocket.'),
            $this->row('BAG-MESSENGER', '8901000000021', 'Messenger Bag', 'Bags', 'Gulshan Hide', 'Chittagong Hide Co.', '2200.00', '4200.00', '3100.00', '2', '3', '4', '2200.0000', 'Flap messenger with a padded sleeve.'),
            $this->row('BAG-TOTE', '8901000000022', 'Tote Bag', 'Bags', 'Padma Craft', 'Chittagong Hide Co.', '1800.00', '3600.00', '2600.00', '2', '3', '3', '1800.0000', 'Unlined tote with rolled handles.'),
            $this->row('BAG-SLING', '8901000000023', 'Sling Bag', 'Bags', 'Old Dhaka Leather', 'Rajshahi Craft Supply', '950.00', '1900.00', '1400.00', '2', '4', '6', '950.0000', 'Cross-body sling, one main compartment.'),
            $this->row('BAG-SLEEVE', '8901000000024', 'Laptop Sleeve 14', 'Bags', 'Gulshan Hide', 'Dhaka Leather House', '1100.00', '2200.00', '1600.00', '2', '3', '5', '1100.0000', 'Padded sleeve for a 14-inch laptop.'),
            $this->row('BAG-DOPP', '8901000000025', 'Travel Dopp Kit', 'Bags', 'Padma Craft', 'Sylhet Belt Works', '640.00', '1280.00', '900.00', '2', '3', '7', '640.0000', 'Hanging wash bag.'),
            $this->row('BLT-FORMAL', '8901000000031', 'Formal Belt 34', 'Belts', 'Old Dhaka Leather', 'Sylhet Belt Works', '620.00', '1400.00', '980.00', '3', '4', '10', '620.0000', 'Dress belt, brass buckle, size 34.'),
            $this->row('BLT-CASUAL', '8901000000032', 'Casual Belt 32', 'Belts', 'Padma Craft', 'Sylhet Belt Works', '480.00', '980.00', '720.00', '3', '5', '8', '480.0000', 'Casual belt, size 32.'),
            $this->row('BLT-BRAID', '8901000000033', 'Braided Belt', 'Belts', 'Gulshan Hide', 'Khulna Tannery', '540.00', '1150.00', '820.00', '2', '3', '6', '540.0000', 'Braided leather belt.'),
            $this->row('BLT-REVERSIBLE', '8901000000034', 'Reversible Belt', 'Belts', 'Old Dhaka Leather', 'Sylhet Belt Works', '700.00', '1500.00', '1050.00', '2', '3', '5', '700.0000', 'Black and brown reversible belt.'),
            $this->row('CRD-SLIM', '8901000000041', 'Slim Card Holder', 'Card Holders', 'Gulshan Hide', 'Dhaka Leather House', '220.00', '480.00', '340.00', '4', '6', '20', '220.0000', 'Two-pocket card holder.'),
            $this->row('CRD-ID', '8901000000042', 'ID Window Holder', 'Card Holders', 'Padma Craft', 'Rajshahi Craft Supply', '180.00', '420.00', '300.00', '4', '5', '16', '180.0000', 'Card holder with an ID window.'),
            $this->row('ACC-PASSPORT', '8901000000051', 'Passport Cover', 'Accessories', 'Old Dhaka Leather', 'Chittagong Hide Co.', '560.00', '1200.00', '850.00', '2', '3', '6', '560.0000', 'Passport cover with a card slot.'),
            $this->row('ACC-FOB', '8901000000052', 'Leather Key Fob', 'Accessories', 'Padma Craft', 'Khulna Tannery', '90.00', '220.00', '150.00', '4', '8', '2', '90.0000', 'Snap key fob. Stock is below the reorder level.'),
            $this->row('ACC-WATCH', '8901000000053', 'Watch Roll', 'Accessories', 'Gulshan Hide', 'Dhaka Leather House', '980.00', '2100.00', '1500.00', '1', '2', '4', '980.0000', 'Three-watch travel roll.'),
            $this->row('ACC-CASE', '8901000000054', 'Spectacle Case', 'Accessories', 'Old Dhaka Leather', 'Rajshahi Craft Supply', '260.00', '560.00', '400.00', '3', '4', '9', '260.0000', 'Hard spectacle case.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function row(
        string $sku,
        string $barcode,
        string $name,
        string $category,
        string $brand,
        string $supplier,
        string $purchase,
        string $selling,
        string $wholesale,
        string $minimum,
        string $reorder,
        string $opening,
        string $openingCost,
        string $description,
    ): array {
        return [
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $name,
            'category' => $category,
            'brand' => $brand,
            'supplier' => $supplier,
            'purchase_price' => $purchase,
            'selling_price' => $selling,
            'wholesale_price' => $wholesale,
            'minimum_stock' => $minimum,
            'reorder_level' => $reorder,
            'opening' => $opening,
            'opening_cost' => $openingCost,
            'description' => $description,
        ];
    }
}
