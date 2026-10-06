<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Exceptions\SelfApprovalException;
use App\Models\Customer;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approvals\ApprovalService;
use App\Services\Inventory\CatalogService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Sales\RefundService;
use App\Services\Sales\SaleReturnService;
use App\Services\Sales\SalesCancellationService;
use App\Services\Sales\SaleService;
use App\Services\Sales\SalesSettings;
use App\Support\ChartAccountCode;
use App\Support\Money;
use Illuminate\Support\Collection;

class SalesTest extends FinanceTestCase
{
    public function test_sale_completion_posts_a_balanced_journal_and_reduces_stock_at_weighted_average_cost(): void
    {
        [$product, $warehouse] = $this->stocked('10', '100.0000', '10', '200.0000');
        $sales = $this->userWithRole(RoleName::SalesManager);
        $inventory = app(InventoryService::class);

        $this->assertSame('20.000', $inventory->onHand($product->fresh()));
        $this->assertSame('150.0000', (string) $product->fresh()->average_cost);

        $sale = $this->sell($sales, $product, $warehouse, '4', '250.00', '0.00', '20.00', '400.00');

        $product->refresh();
        $sale->refresh()->load('items', 'journalEntry.lines.account');
        $this->assertSame(DocumentStatus::Completed, $sale->status);
        $this->assertSame('1000.00', (string) $sale->subtotal);
        $this->assertSame('1020.00', (string) $sale->total);
        $this->assertSame('400.00', (string) $sale->paid_amount);
        $this->assertSame('620.00', (string) $sale->due_amount);
        $this->assertSame('16.000', $inventory->onHand($product));
        $this->assertSame('150.0000', (string) $product->average_cost);
        $this->assertSame('150.0000', (string) $sale->items->first()->unit_cost);
        $this->assertSame('600.00', (string) $sale->items->first()->cogs_amount);

        $this->assertJournalBalances($sale);
        $lines = $sale->journalEntry->lines;
        $this->assertSame('600.00', $this->side($lines, ChartAccountCode::Cogs, 'debit'));
        $this->assertSame('600.00', $this->side($lines, ChartAccountCode::Inventory, 'credit'));
        $this->assertSame('1020.00', $this->side($lines, ChartAccountCode::ProductSales, 'credit'));
        $this->assertSame('400.00', $this->side($lines, ChartAccountCode::Cash, 'debit'));
        $this->assertSame('620.00', $this->side($lines, ChartAccountCode::AccountsReceivable, 'debit'));
    }

    public function test_sale_return_reverses_stock_revenue_and_cogs(): void
    {
        [$product, $warehouse] = $this->stocked('10', '100.0000', '10', '200.0000');
        $sales = $this->userWithRole(RoleName::SalesManager);
        $inventory = app(InventoryService::class);
        $sale = $this->sell($sales, $product, $warehouse, '4', '250.00', '0.00', '20.00', '400.00');

        $return = app(SaleReturnService::class)->create($sales, [
            'sale_id' => $sale->id,
            'transaction_date' => '2026-06-03',
            'note' => 'Customer brought two back.',
            'items' => [[
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '2',
            ]],
        ]);

        $product->refresh();
        $sale->refresh();
        $return->refresh()->load('journalEntry.lines.account');
        $this->assertSame(DocumentStatus::Completed, $return->status);
        $this->assertSame('510.00', (string) $return->total);
        $this->assertSame('300.00', (string) $return->cogs_total);
        $this->assertSame('18.000', $inventory->onHand($product));
        $this->assertSame('150.0000', (string) $product->average_cost);
        $this->assertSame('110.00', (string) $sale->due_amount);
        $this->assertJournalBalances($return);

        $lines = $return->journalEntry->lines;
        $this->assertSame('510.00', $this->side($lines, ChartAccountCode::ProductSales, 'debit'));
        $this->assertSame('510.00', $this->side($lines, ChartAccountCode::AccountsReceivable, 'credit'));
        $this->assertSame('300.00', $this->side($lines, ChartAccountCode::Inventory, 'debit'));
        $this->assertSame('300.00', $this->side($lines, ChartAccountCode::Cogs, 'credit'));
    }

    public function test_refund_needs_approval_and_the_requester_cannot_self_approve(): void
    {
        [$product, $warehouse] = $this->stocked('5', '100.0000');
        $sales = $this->userWithRole(RoleName::SalesManager);
        $admin = $this->userWithRole(RoleName::Admin);
        $cash = $this->cashAccount();
        $before = (string) $cash->current_balance;

        $sale = $this->sell($sales, $product, $warehouse, '1', '250.00', '0.00', '0.00', '250.00');
        app(SaleReturnService::class)->create($sales, [
            'sale_id' => $sale->id,
            'transaction_date' => '2026-06-04',
            'items' => [[
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ]],
        ]);

        $sale->refresh();
        $this->assertSame('-250.00', (string) $sale->due_amount);

        $refund = app(RefundService::class)->create($sales, [
            'sale_id' => $sale->id,
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'amount' => '250.00',
            'transaction_date' => '2026-06-05',
            'note' => 'Cash back to the customer.',
        ]);

        $this->assertSame(DocumentStatus::Pending, $refund->status);
        $this->assertNull($refund->journal_entry_id);

        try {
            app(ApprovalService::class)->approve($refund->approvalRequest, $sales, 'Approving my own refund.');
            $this->fail('Self-approval should have been refused.');
        } catch (SelfApprovalException $exception) {
            $this->assertSame('You cannot approve your own transaction.', $exception->getMessage());
        }

        $cash->refresh();
        $refund->refresh();
        $sale->refresh();
        $this->assertSame(DocumentStatus::Pending, $refund->status);
        $this->assertNull($refund->journal_entry_id);
        $this->assertSame('-250.00', (string) $sale->due_amount);
        $this->assertSame(Money::of($before)->add('250.00')->amount(), (string) $cash->current_balance);

        app(ApprovalService::class)->approve($refund->approvalRequest, $admin, 'Refund paid.');

        $cash->refresh();
        $refund->refresh()->load('journalEntry.lines.account');
        $sale->refresh();
        $this->assertSame(DocumentStatus::Approved, $refund->status);
        $this->assertSame('0.00', (string) $sale->due_amount);
        $this->assertSame('0.00', (string) $sale->paid_amount);
        $this->assertSame($before, (string) $cash->current_balance);
        $this->assertJournalBalances($refund);
        $lines = $refund->journalEntry->lines;
        $this->assertSame('250.00', $this->side($lines, ChartAccountCode::AccountsReceivable, 'debit'));
        $this->assertSame('250.00', $this->side($lines, ChartAccountCode::Cash, 'credit'));
    }

    public function test_large_discount_stays_pending_until_another_user_approves(): void
    {
        [$product, $warehouse] = $this->stocked('5', '80.0000');
        $sales = $this->userWithRole(RoleName::SalesManager);
        $admin = $this->userWithRole(RoleName::Admin);
        $inventory = app(InventoryService::class);

        $this->assertSame('1000.00', app(SalesSettings::class)->threshold());

        $sale = $this->sell($sales, $product, $warehouse, '2', '1000.00', '1500.00', '0.00', '0.00');

        $this->assertSame(DocumentStatus::Pending, $sale->status);
        $this->assertNull($sale->journal_entry_id);
        $this->assertSame('5.000', $inventory->onHand($product->fresh()));
        $this->assertSame('500.00', (string) $sale->total);

        try {
            app(ApprovalService::class)->approve($sale->approvalRequest, $sales, 'Approving my own discount.');
            $this->fail('Self-approval should have been refused.');
        } catch (SelfApprovalException $exception) {
            $this->assertSame('You cannot approve your own transaction.', $exception->getMessage());
        }

        $this->assertSame('5.000', $inventory->onHand($product->fresh()));

        app(ApprovalService::class)->approve($sale->approvalRequest, $admin, 'Discount allowed.');

        $product->refresh();
        $sale->refresh()->load('journalEntry.lines.account');
        $this->assertSame(DocumentStatus::Completed, $sale->status);
        $this->assertSame('3.000', $inventory->onHand($product));
        $this->assertSame('160.00', (string) $sale->items()->first()->cogs_amount);
        $this->assertJournalBalances($sale);
        $lines = $sale->journalEntry->lines;
        $this->assertSame('500.00', $this->side($lines, ChartAccountCode::ProductSales, 'credit'));
        $this->assertSame('500.00', $this->side($lines, ChartAccountCode::AccountsReceivable, 'debit'));
        $this->assertSame('160.00', $this->side($lines, ChartAccountCode::Cogs, 'debit'));
        $this->assertSame('160.00', $this->side($lines, ChartAccountCode::Inventory, 'credit'));
    }

    public function test_discount_at_the_threshold_completes_immediately_and_the_threshold_is_configurable(): void
    {
        [$product, $warehouse] = $this->stocked('4', '40.0000');
        $sales = $this->userWithRole(RoleName::SalesManager);
        $admin = $this->userWithRole(RoleName::Admin);

        $sale = $this->sell($sales, $product, $warehouse, '2', '1000.00', '1000.00', '0.00', '0.00');

        $this->assertSame(DocumentStatus::Completed, $sale->status);
        $this->assertNotNull($sale->journal_entry_id);
        $this->assertSame('2.000', app(InventoryService::class)->onHand($product->fresh()));

        $this->actingAs($admin)
            ->put(route('settings.approvals.discount'), ['large_discount_threshold' => '250.00'])
            ->assertRedirect(route('settings.approvals.index'));

        $this->assertSame('250.00', app(SalesSettings::class)->threshold());
    }

    public function test_rejected_large_discount_does_not_move_stock_or_post_a_journal(): void
    {
        [$product, $warehouse] = $this->stocked('3', '50.0000');
        $sales = $this->userWithRole(RoleName::SalesManager);
        $admin = $this->userWithRole(RoleName::Admin);
        $sale = $this->sell($sales, $product, $warehouse, '1', '2000.00', '1500.00', '0.00', '0.00');

        app(ApprovalService::class)->reject($sale->approvalRequest, $admin, 'Too steep.');

        $sale->refresh();
        $this->assertSame(DocumentStatus::Rejected, $sale->status);
        $this->assertNull($sale->journal_entry_id);
        $this->assertSame('3.000', app(InventoryService::class)->onHand($product->fresh()));
    }

    public function test_cancellation_needs_approval_then_restores_stock_and_nets_the_journal_to_zero(): void
    {
        [$product, $warehouse] = $this->stocked('4', '50.0000');
        $sales = $this->userWithRole(RoleName::SalesManager);
        $admin = $this->userWithRole(RoleName::Admin);
        $cash = $this->cashAccount();
        $before = (string) $cash->current_balance;
        $inventory = app(InventoryService::class);

        $sale = $this->sell($sales, $product, $warehouse, '1', '80.00', '0.00', '0.00', '80.00');
        $this->assertSame('3.000', $inventory->onHand($product->fresh()));

        $cancellation = app(SalesCancellationService::class)->create($sales, [
            'sale_id' => $sale->id,
            'transaction_date' => '2026-06-06',
            'reason' => 'Customer cancelled before collection.',
        ]);

        $this->assertSame(DocumentStatus::Pending, $cancellation->status);
        $this->assertSame(DocumentStatus::Completed, $sale->fresh()->status);
        $this->assertSame('3.000', $inventory->onHand($product->fresh()));

        try {
            app(ApprovalService::class)->approve($cancellation->approvalRequest, $sales, 'Approving my own cancellation.');
            $this->fail('Self-approval should have been refused.');
        } catch (SelfApprovalException $exception) {
            $this->assertSame('You cannot approve your own transaction.', $exception->getMessage());
        }

        $this->assertSame('3.000', $inventory->onHand($product->fresh()));
        $this->assertSame(DocumentStatus::Completed, $sale->fresh()->status);

        app(ApprovalService::class)->approve($cancellation->approvalRequest, $admin, 'Cancelled.');

        $product->refresh();
        $sale->refresh();
        $cash->refresh();
        $this->assertSame(DocumentStatus::Cancelled, $sale->status);
        $this->assertSame('0.00', (string) $sale->paid_amount);
        $this->assertSame('0.00', (string) $sale->due_amount);
        $this->assertSame('4.000', $inventory->onHand($product));
        $this->assertSame('50.0000', (string) $product->average_cost);
        $this->assertSame($before, (string) $cash->current_balance);

        $lines = JournalEntryLine::query()
            ->with('account')
            ->whereHas('entry', fn ($query) => $query
                ->where('source_type', $sale->getMorphClass())
                ->where('source_id', $sale->id))
            ->whereHas('account', fn ($query) => $query->where('code', ChartAccountCode::Cash))
            ->get();

        $this->assertSame($this->side($lines, ChartAccountCode::Cash, 'debit'), $this->side($lines, ChartAccountCode::Cash, 'credit'));
        $this->assertSame('80.00', $this->side($lines, ChartAccountCode::Cash, 'debit'));
    }

    public function test_sales_permissions_gate_the_screens(): void
    {
        [$product, $warehouse] = $this->stocked('2', '40.0000');
        $viewer = $this->userWithRole(RoleName::Viewer);
        $partner = $this->userWithRole(RoleName::Partner);
        $sales = $this->userWithRole(RoleName::SalesManager);
        $customer = $this->customer();

        $this->actingAs($viewer)->get(route('sales.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Sales/Orders'));

        $this->actingAs($viewer)->get(route('sales.returns.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Sales/Returns'));

        $this->actingAs($viewer)->get(route('sales.customers.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('sales.orders.store'), $this->orderPayload($customer, $warehouse, $product))->assertForbidden();
        $this->actingAs($partner)->get(route('sales.orders.index'))->assertForbidden();

        $this->actingAs($sales)
            ->post(route('sales.orders.store'), $this->orderPayload($customer, $warehouse, $product))
            ->assertRedirect()
            ->assertSessionHas('success', 'Sale completed.');

        $this->assertDatabaseHas('sales', [
            'customer_id' => $customer->id,
            'status' => DocumentStatus::Completed->value,
        ]);
    }

    /**
     * @return array{0: Product, 1: Warehouse}
     */
    private function stocked(string $firstQty, string $firstCost, ?string $secondQty = null, ?string $secondCost = null): array
    {
        $inventory = $this->userWithRole(RoleName::InventoryManager);
        $admin = $this->userWithRole(RoleName::Admin);
        $catalog = app(CatalogService::class);
        $category = $catalog->createCategory($inventory, ['name' => 'Wallets']);
        $unit = $catalog->createUnit($inventory, ['name' => 'Piece', 'abbreviation' => 'pc']);
        $warehouse = $catalog->createWarehouse($inventory, ['name' => 'Main Store', 'address' => 'Dhaka']);
        $product = $catalog->createProduct($inventory, [
            'sku' => 'WAL-SALE',
            'barcode' => '8901000000101',
            'name' => 'Sale Wallet',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => '100.00',
            'selling_price' => '250.00',
            'wholesale_price' => '180.00',
            'minimum_stock' => '1',
            'reorder_level' => '2',
            'status' => 'active',
        ]);

        $this->opening($inventory, $admin, $product, $warehouse, $firstQty, $firstCost, '2026-05-01');

        if ($secondQty !== null && $secondCost !== null) {
            $this->opening($inventory, $admin, $product, $warehouse, $secondQty, $secondCost, '2026-05-02');
        }

        return [$product->fresh(), $warehouse];
    }

    private function opening(User $inventory, User $admin, Product $product, Warehouse $warehouse, string $quantity, string $unitCost, string $date): void
    {
        $adjustment = app(StockAdjustmentService::class)->create($inventory, [
            'kind' => 'opening',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'transaction_date' => $date,
            'reason' => 'Opening stock.',
        ]);

        app(ApprovalService::class)->approve($adjustment->approvalRequest, $admin, 'Counted.');
    }

    private function sell(
        User $actor,
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        string $price,
        string $discount,
        string $delivery,
        string $paid,
    ): Sale {
        return app(SaleService::class)->create($actor, [
            'customer_id' => $this->customer()->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-02',
            'discount' => $discount,
            'delivery' => $delivery,
            'paid_amount' => $paid,
            'payment_method' => Money::of($paid)->isZero() ? null : 'cash',
            'financial_account_id' => Money::of($paid)->isZero() ? null : $this->cashAccount()->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $price,
            ]],
        ]);
    }

    private function customer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Test Customer',
            'phone' => '01700000000',
            'status' => CustomerStatus::Active,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Customer $customer, Warehouse $warehouse, Product $product): array
    {
        return [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-01',
            'discount' => '0.00',
            'delivery' => '0.00',
            'paid_amount' => '180.00',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount()->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '180.00',
            ]],
        ];
    }

    private function assertJournalBalances(Sale|SaleReturn|Refund $document): void
    {
        $debits = '0.00';
        $credits = '0.00';

        foreach ($document->journalEntry->lines as $line) {
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
}
