<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\ChartOfAccount;
use App\Models\FinancialAccount;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\FinancialAccountService;
use App\Services\Approvals\ApprovalService;
use App\Services\Inventory\CatalogService;
use App\Services\Purchasing\PurchaseReturnService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Purchasing\SupplierPaymentService;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Support\Collection;

class InventoryTest extends FinanceTestCase
{
    public function test_purchase_posts_cogs_without_stock_movements(): void
    {
        [$inventory, $accountant, $product, $supplier] = $this->catalog();

        $purchase = $this->purchase($inventory, $supplier, $product, '6', '100.0000', '0.00');

        $this->assertSame(0, StockMovement::query()->count());
        $this->assertNull($purchase->journal_entry_id);

        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');

        $purchase->refresh()->load('journalEntry.lines.account');
        $this->assertSame(DocumentStatus::Approved, $purchase->status);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertNotNull($purchase->journal_entry_id);
        $this->assertSame('600.00', $this->side($purchase->journalEntry->lines, ChartAccountCode::Cogs, 'debit'));
        $this->assertSame('600.00', $this->side($purchase->journalEntry->lines, ChartAccountCode::AccountsPayable, 'credit'));
    }

    public function test_super_admin_can_edit_an_approved_purchase_and_repost_the_journal(): void
    {
        [$inventory, $accountant, $product, $supplier] = $this->catalog();
        $super = $this->userWithRole(RoleName::SuperAdmin);

        $purchase = $this->purchase($inventory, $supplier, $product, '6', '100.0000', '0.00');
        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');

        $payload = [
            'supplier_id' => $supplier->id,
            'transaction_date' => '2026-05-10',
            'paid_amount' => '0.00',
            'note' => 'Corrected quantity',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '10',
                'unit_cost' => '100.0000',
            ]],
        ];

        $this->actingAs($inventory)
            ->put(route('inventory.purchases.update', $purchase), $payload)
            ->assertForbidden();

        $this->actingAs($super)
            ->put(route('inventory.purchases.update', $purchase), $payload)
            ->assertRedirect(route('inventory.purchases.show', $purchase));

        $purchase->refresh()->load('items', 'journalEntry.lines.account');
        $this->assertSame(DocumentStatus::Approved, $purchase->status);
        $this->assertSame('1000.00', (string) $purchase->total);
        $this->assertSame('10.000', (string) $purchase->items->first()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertNotNull($purchase->journal_entry_id);
        $this->assertSame('Corrected quantity', $purchase->note);
        $this->assertSame('1000.00', $this->side($purchase->journalEntry->lines, ChartAccountCode::Cogs, 'debit'));
    }

    public function test_purchase_journal_is_balanced_for_partial_payment(): void
    {
        [$inventory, $accountant, $product, $supplier] = $this->catalog();
        $drawer = $this->drawer();

        $purchase = $this->purchase($inventory, $supplier, $product, '4', '250.0000', '400.00', $drawer);
        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');

        $purchase->refresh()->load('journalEntry.lines.account');
        $this->assertJournalBalances($purchase);
        $this->assertSame('1000.00', (string) $purchase->total);
        $this->assertSame('400.00', (string) $purchase->paid_amount);
        $this->assertSame('600.00', (string) $purchase->due_amount);

        $lines = $purchase->journalEntry->lines;
        $this->assertSame('1000.00', $this->side($lines, ChartAccountCode::Cogs, 'debit'));
        $this->assertSame('400.00', $this->side($lines, '1000', 'credit'));
        $this->assertSame('600.00', $this->side($lines, ChartAccountCode::AccountsPayable, 'credit'));
        $this->assertSame('0.00', $this->side($lines, ChartAccountCode::Inventory, 'debit'));
    }

    public function test_supplier_payment_reduces_due_payable_and_cash(): void
    {
        [$inventory, $accountant, $product, $supplier] = $this->catalog();
        $drawer = $this->drawer();
        $before = (string) $drawer->current_balance;

        $purchase = $this->purchase($inventory, $supplier, $product, '4', '250.0000', '400.00', $drawer);
        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');

        $payment = app(SupplierPaymentService::class)->create($inventory, [
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'financial_account_id' => $drawer->id,
            'payment_method' => 'cash',
            'amount' => '250.00',
            'payment_date' => '2026-05-20',
            'note' => 'Part of the due.',
        ]);

        $purchase->refresh();
        $this->assertSame('600.00', (string) $purchase->due_amount);

        app(ApprovalService::class)->approve($payment->approvalRequest, $accountant, 'Paid.');

        $purchase->refresh();
        $drawer->refresh();
        $this->assertSame('350.00', (string) $purchase->due_amount);
        $this->assertSame('650.00', (string) $purchase->paid_amount);
        $this->assertSame(Money::of($before)->sub('650.00')->amount(), (string) $drawer->current_balance);
        $this->assertSame('350.00', $this->payableBalance($supplier->id));
    }

    public function test_purchase_return_reverses_cogs_and_payable_without_stock(): void
    {
        [$inventory, $accountant, $product, $supplier] = $this->catalog();

        $purchase = $this->purchase($inventory, $supplier, $product, '10', '200.0000', '0.00');
        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');

        $return = app(PurchaseReturnService::class)->create($inventory, [
            'purchase_id' => $purchase->id,
            'transaction_date' => '2026-05-12',
            'note' => 'Return the lot.',
            'items' => [[
                'purchase_item_id' => $purchase->items->first()->id,
                'quantity' => '10',
            ]],
        ]);

        $this->assertSame(0, StockMovement::query()->count());

        app(ApprovalService::class)->approve($return->approvalRequest, $accountant, 'Returned.');

        $purchase->refresh();
        $return->refresh()->load('journalEntry.lines.account');
        $this->assertSame('0.00', (string) $purchase->due_amount);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame('2000.00', $this->side($return->journalEntry->lines, ChartAccountCode::Cogs, 'credit'));
        $this->assertSame('2000.00', $this->side($return->journalEntry->lines, ChartAccountCode::AccountsPayable, 'debit'));
    }

    public function test_rejected_purchase_does_not_change_the_ledger(): void
    {
        [$inventory, $accountant, $product, $supplier] = $this->catalog();

        $purchase = $this->purchase($inventory, $supplier, $product, '2', '80.0000', '0.00');
        app(ApprovalService::class)->reject($purchase->approvalRequest, $accountant, 'Wrong goods.');

        $purchase->refresh();
        $this->assertSame(DocumentStatus::Rejected, $purchase->status);
        $this->assertNull($purchase->journal_entry_id);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_inventory_permissions_gate_the_screens(): void
    {
        [$inventory, , $product] = $this->catalog();
        $viewer = $this->userWithRole(RoleName::Viewer);
        $partner = $this->userWithRole(RoleName::Partner);

        $this->actingAs($viewer)->get(route('inventory.products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Inventory/Products'));

        $this->actingAs($viewer)->get(route('inventory.suppliers.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.products.store'), $this->productPayload($product))->assertForbidden();
        $this->actingAs($partner)->get(route('inventory.products.index'))->assertForbidden();

        $this->actingAs($inventory)->post(route('inventory.products.store'), [
            'sku' => 'WAL-NEW',
            'barcode' => '8901000099999',
            'name' => 'New Wallet',
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'purchase_price' => '100.00',
            'selling_price' => '180.00',
            'wholesale_price' => '140.00',
            'minimum_stock' => '1',
            'reorder_level' => '2',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['sku' => 'WAL-NEW']);
    }

    /**
     * @return array{0: User, 1: User, 2: Product, 3: Supplier}
     */
    private function catalog(): array
    {
        $inventory = $this->userWithRole(RoleName::InventoryManager);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $catalog = app(CatalogService::class);
        $category = $catalog->createCategory($inventory, ['name' => 'Wallets']);
        $unit = $catalog->createUnit($inventory, ['name' => 'Piece', 'abbreviation' => 'pc']);
        $supplier = $catalog->createSupplier($inventory, ['name' => 'Hide Co']);
        $product = $catalog->createProduct($inventory, [
            'sku' => 'WAL-TEST',
            'barcode' => '8901000000001',
            'name' => 'Test Wallet',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => '100.00',
            'selling_price' => '180.00',
            'wholesale_price' => '140.00',
            'minimum_stock' => '1',
            'reorder_level' => '2',
            'status' => 'active',
            'description' => 'Test product',
        ]);

        return [$inventory, $accountant, $product, $supplier];
    }

    private function purchase(
        User $actor,
        Supplier $supplier,
        Product $product,
        string $quantity,
        string $unitCost,
        string $paid,
        ?FinancialAccount $account = null,
    ): Purchase {
        return app(PurchaseService::class)->create($actor, [
            'supplier_id' => $supplier->id,
            'transaction_date' => '2026-05-10',
            'paid_amount' => $paid,
            'payment_method' => Money::of($paid)->isZero() ? null : 'cash',
            'financial_account_id' => Money::of($paid)->isZero() ? null : ($account ?? $this->cashAccount())->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ]],
        ]);
    }

    private function drawer(): FinancialAccount
    {
        $admin = $this->userWithRole(RoleName::Admin);

        return app(FinancialAccountService::class)->create($admin, [
            'name' => 'Drawer',
            'type' => 'cash',
            'chart_of_account_id' => ChartOfAccount::query()->where('code', ChartAccountCode::Cash)->value('id'),
            'opening_balance' => '5000.00',
            'opening_date' => '2026-01-01',
        ]);
    }

    private function assertJournalBalances(Purchase $purchase): void
    {
        $debits = '0.00';
        $credits = '0.00';

        foreach ($purchase->journalEntry->lines as $line) {
            $debits = Money::of($debits)->add((string) $line->debit)->amount();
            $credits = Money::of($credits)->add((string) $line->credit)->amount();
        }

        $this->assertSame($debits, $credits);
        $this->assertNotSame('0.00', $debits);
    }

    /**
     * @param  Collection<int, JournalEntryLine>  $lines
     */
    private function side($lines, string $code, string $column): string
    {
        $total = '0.00';

        foreach ($lines as $line) {
            if ($line->account?->code !== $code) {
                continue;
            }

            $total = Money::of($total)->add((string) $line->{$column})->amount();
        }

        return $total;
    }

    private function payableBalance(int $supplierId): string
    {
        $balance = '0.00';

        $lines = JournalEntryLine::query()
            ->where('supplier_id', $supplierId)
            ->whereHas('account', fn ($query) => $query->where('code', ChartAccountCode::AccountsPayable))
            ->get();

        foreach ($lines as $line) {
            $balance = Money::of($balance)->add((string) $line->credit)->sub((string) $line->debit)->amount();
        }

        return $balance;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product): array
    {
        return [
            'sku' => 'WAL-DENIED',
            'barcode' => '8901000000002',
            'name' => 'Denied',
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'purchase_price' => '10.00',
            'selling_price' => '20.00',
            'wholesale_price' => '15.00',
            'minimum_stock' => '0',
            'reorder_level' => '0',
            'status' => 'active',
        ];
    }
}
