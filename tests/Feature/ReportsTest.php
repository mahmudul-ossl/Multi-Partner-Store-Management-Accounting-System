<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PartnerWithdrawal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approvals\ApprovalService;
use App\Services\Inventory\CatalogService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Purchasing\SupplierPaymentService;
use App\Services\Reports\MonthlyReport;
use App\Services\Reports\StockValuation;
use App\Services\Sales\CustomerPaymentService;
use App\Services\Sales\SaleReturnService;
use App\Services\Sales\SaleService;
use App\Support\EmbeddedFont;
use App\Support\Money;
use ZipArchive;

class ReportsTest extends FinanceTestCase
{
    public function test_sales_report_total_matches_the_ledger_and_exports_download(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $reference = (string) Sale::query()->value('reference');

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => 'sales',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
                'search' => $reference,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Show')
                ->where('rows.total', 1)
                ->where('filters.status', 'completed')
                ->where('totals.0.label', 'Completed sales (ledger)')
                ->where('totals.0.amount', '3180.00')
                ->where('totals.1.label', 'Pending'));

        $excel = $this->actingAs($admin)->get(route('reports.excel', [
            'report' => 'sales',
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]));
        $excel->assertOk();
        $excel->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents((string) $path, $excel->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open((string) $path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('3180.00', $sheet);
        $zip->close();

        $pdf = $this->actingAs($admin)->get(route('reports.pdf', [
            'report' => 'sales',
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]));
        $pdf->assertOk();
        $body = $pdf->getContent();
        $glyph = EmbeddedFont::noto()->glyph(0x09F3);
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString('3180.00', $body);
        $this->assertStringContainsString('/BaseFont /NotoSansBengali-Regular', $body);
        $this->assertStringContainsString('/FontFile2', $body);
        $this->assertNotNull($glyph);
        $this->assertNotSame(0x09F3, $glyph);
        $this->assertStringContainsString('<09F3> <09F3>', $body);
        $this->assertStringContainsString('<09F3> Tj', $body);
        $this->assertStringNotContainsString('?3,180.00', $body);
        $this->assertStringNotContainsString('?1,450.00', $body);

        $pending = Sale::query()->where('status', DocumentStatus::Pending)->firstOrFail();
        $pendingTotal = '0.00';
        foreach (Sale::query()->where('status', DocumentStatus::Pending)->pluck('total') as $amount) {
            $pendingTotal = Money::of($pendingTotal)->add((string) $amount)->amount();
        }

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => 'sales',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
                'search' => $pending->reference,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.total', 0)
                ->where('totals.1.amount', $pendingTotal));

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => 'sales',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
                'status' => 'pending',
                'search' => $pending->reference,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.total', 1)
                ->where('rows.data.0.reference', $pending->reference)
                ->where('rows.data.0.status', 'Pending'));

        $this->actingAs($admin)
            ->get(route('reports.show', [
                'report' => 'sales',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
                'status' => 'all',
                'search' => $pending->reference,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rows.total', 1));
    }

    public function test_monthly_report_figures_match_the_ledger(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $report = app(MonthlyReport::class)->build('2026-01-01', '2026-12-31');
        $figures = collect($report['figures'])->keyBy('key');

        $this->assertSame('3180.00', $figures['sales']['amount']);
        $this->assertSame('1603.64', $figures['cogs']['amount']);
        $this->assertSame('1576.36', $figures['gross_profit']['amount']);
        $this->assertSame('5200.00', $figures['expenses']['amount']);
        $this->assertSame('-3623.64', $figures['net_profit']['amount']);
        $this->assertSame('2500.00', $figures['promotion']['amount']);

        $this->actingAs($admin)
            ->get(route('reports.show', ['report' => 'monthly', 'from' => '2026-01-01', 'to' => '2026-12-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totals.0.amount', '1576.36')
                ->where('totals.1.amount', '-3623.64'));

        $token = $admin->createToken('api')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/reports/monthly?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('figures.0.amount', '3180.00')
            ->assertJsonPath('figures.2.amount', '1603.64')
            ->assertJsonPath('figures.5.amount', '-3623.64');
    }

    public function test_dashboard_cards_follow_permissions_and_inventory_comes_from_stock(): void
    {
        $inventory = $this->userWithRole(RoleName::InventoryManager);
        [$partnerUser] = $this->linkedPartner();

        $this->actingAs($inventory)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.show_sales', false)
                ->where('summary.show_inventory', true)
                ->where('summary.cards', function ($cards): bool {
                    $keys = collect($cards)->pluck('key');

                    return $keys->contains('inventory_value')
                        && $keys->contains('low_stock')
                        && $keys->contains('payables')
                        && ! $keys->contains('sales')
                        && ! $keys->contains('cash')
                        && ! $keys->contains('gross_profit');
                }));

        $this->actingAs($inventory)->get(route('reports.show', 'sales'))->assertForbidden();
        $this->actingAs($inventory)->get(route('reports.index'))->assertOk();

        $this->actingAs($partnerUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.show_cash', false)
                ->where('summary.show_inventory', false)
                ->where('summary.cards', function ($cards): bool {
                    $keys = collect($cards)->pluck('key');

                    return $keys->contains('investment')
                        && $keys->contains('pending_approvals')
                        && ! $keys->contains('net_profit')
                        && ! $keys->contains('gross_profit')
                        && ! $keys->contains('cash')
                        && ! $keys->contains('sales');
                })
                ->where('summary.charts', function ($charts): bool {
                    $keys = collect($charts)->pluck('key');

                    return $keys->contains('promotion')
                        && ! $keys->contains('profit')
                        && ! $keys->contains('investment')
                        && ! $keys->contains('sales');
                }));

        $this->actingAs($partnerUser)->get(route('reports.show', 'cash'))->assertForbidden();
    }

    public function test_dashboard_inventory_value_matches_the_stock_ledger(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@mpstore.test')->firstOrFail();
        $expected = Money::of(app(StockValuation::class)->current())->formatted();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('summary.placeholders')
                ->where('summary.inventory_value', $expected)
                ->where('summary.cards', function ($cards) use ($expected): bool {
                    $card = collect($cards)->firstWhere('key', 'inventory_value');

                    return is_array($card) && $card['value'] === $expected;
                }));
    }

    public function test_low_stock_and_payment_due_notifications_are_stored(): void
    {
        [$inventory, $accountant, $product, $warehouse, $supplier] = $this->catalog();
        $admin = $this->userWithRole(RoleName::Admin);
        $sales = $this->userWithRole(RoleName::SalesManager);

        $opening = app(StockAdjustmentService::class)->create($inventory, [
            'kind' => 'opening',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => '1',
            'unit_cost' => '80.0000',
            'transaction_date' => '2026-05-01',
            'reason' => 'Opening stock.',
        ]);
        app(ApprovalService::class)->approve($opening->approvalRequest, $admin, 'Counted.');

        $this->assertTrue($inventory->fresh()->notifications()->where('data->kind', 'low_stock')->exists());
        $this->assertFalse($sales->fresh()->notifications()->where('data->kind', 'low_stock')->exists());
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::StockAdjusted)->exists());

        $purchase = app(PurchaseService::class)->create($inventory, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-05-10',
            'paid_amount' => '0.00',
            'payment_method' => null,
            'financial_account_id' => null,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '6',
                'unit_cost' => '100.0000',
            ]],
        ]);
        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');

        $this->assertTrue($accountant->fresh()->notifications()->where('data->kind', 'supplier_due')->exists());
        $this->assertFalse($sales->fresh()->notifications()->where('data->kind', 'supplier_due')->exists());

        $customer = Customer::query()->create([
            'name' => 'Due Customer',
            'phone' => '01711111111',
            'status' => CustomerStatus::Active,
        ]);
        app(SaleService::class)->create($sales, [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-02',
            'discount' => '0.00',
            'delivery' => '0.00',
            'paid_amount' => '0.00',
            'payment_method' => null,
            'financial_account_id' => null,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '180.00',
            ]],
        ]);

        $this->assertTrue($sales->fresh()->notifications()->where('data->kind', 'customer_due')->exists());
        $this->assertFalse($inventory->fresh()->notifications()->where('data->kind', 'customer_due')->exists());
    }

    public function test_due_notifications_refresh_when_payment_or_return_changes_the_balance(): void
    {
        [$inventory, $accountant, $product, $warehouse, $supplier] = $this->catalog();
        $sales = $this->userWithRole(RoleName::SalesManager);
        $cash = $this->cashAccount();

        $purchase = app(PurchaseService::class)->create($inventory, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-05-10',
            'paid_amount' => '0.00',
            'payment_method' => null,
            'financial_account_id' => null,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '6',
                'unit_cost' => '100.0000',
            ]],
        ]);
        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');
        $purchase->refresh();

        $this->assertSame(
            $supplier->name.' is owed '.Money::of('600.00')->formatted().' on '.$purchase->reference.'.',
            $this->dueMessage($accountant, 'supplier_due'),
        );

        $part = app(SupplierPaymentService::class)->create($inventory, [
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'amount' => '200.00',
            'payment_date' => '2026-05-20',
            'note' => 'Part payment.',
        ]);
        app(ApprovalService::class)->approve($part->approvalRequest, $accountant, 'Part paid.');

        $this->assertSame(
            $supplier->name.' is owed '.Money::of('400.00')->formatted().' on '.$purchase->reference.'.',
            $this->dueMessage($accountant, 'supplier_due'),
        );

        $rest = app(SupplierPaymentService::class)->create($inventory, [
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'amount' => '400.00',
            'payment_date' => '2026-05-21',
            'note' => 'Balance.',
        ]);
        app(ApprovalService::class)->approve($rest->approvalRequest, $accountant, 'Paid.');

        $this->assertFalse($accountant->fresh()->unreadNotifications()->where('data->kind', 'supplier_due')->exists());

        $customer = Customer::query()->create([
            'name' => 'Due Customer',
            'phone' => '01711111111',
            'status' => CustomerStatus::Active,
        ]);
        $sale = app(SaleService::class)->create($sales, [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-02',
            'discount' => '0.00',
            'delivery' => '0.00',
            'paid_amount' => '0.00',
            'payment_method' => null,
            'financial_account_id' => null,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '180.00',
            ]],
        ]);

        $this->assertSame(
            'Due Customer still owes '.Money::of('180.00')->formatted().' on '.$sale->reference.'.',
            $this->dueMessage($sales, 'customer_due'),
        );

        app(CustomerPaymentService::class)->create($sales, [
            'sale_id' => $sale->id,
            'financial_account_id' => $cash->id,
            'payment_method' => 'cash',
            'amount' => '50.00',
            'payment_date' => '2026-06-03',
            'note' => 'Instalment.',
        ]);

        $this->assertSame('130.00', (string) $sale->fresh()->due_amount);
        $this->assertSame(
            'Due Customer still owes '.Money::of('130.00')->formatted().' on '.$sale->reference.'.',
            $this->dueMessage($sales, 'customer_due'),
        );

        app(SaleReturnService::class)->create($sales, [
            'sale_id' => $sale->id,
            'transaction_date' => '2026-06-04',
            'note' => 'Returned the wallet.',
            'items' => [[
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => '1',
            ]],
        ]);

        $this->assertFalse($sales->fresh()->unreadNotifications()->where('data->kind', 'customer_due')->exists());

        $before = $sales->fresh()->notifications()->where('data->kind', 'customer_due')->count();
        app(SaleService::class)->create($sales, [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-06-05',
            'discount' => '0.00',
            'delivery' => '0.00',
            'paid_amount' => '180.00',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '180.00',
            ]],
        ]);

        $this->assertSame($before, $sales->fresh()->notifications()->where('data->kind', 'customer_due')->count());
    }

    public function test_approve_and_reject_write_audit_entries(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner();
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);

        $this->actingAs($admin)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '1000.00'))
            ->assertRedirect();

        $first = PartnerWithdrawal::query()->firstOrFail();
        $this->actingAs($accountant)
            ->post(route('approvals.approve', $first->approvalRequest), ['comment' => 'Paid'])
            ->assertRedirect();

        $this->assertTrue(AuditLog::query()->where('action', AuditAction::Approved)->exists());

        $this->actingAs($admin)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '800.00'))
            ->assertRedirect();

        $second = PartnerWithdrawal::query()->where('amount', '800.00')->firstOrFail();
        $this->actingAs($accountant)
            ->post(route('approvals.reject', $second->approvalRequest), ['comment' => 'Not now'])
            ->assertRedirect();

        $this->assertTrue(AuditLog::query()->where('action', AuditAction::Rejected)->exists());
        $this->assertSame(DocumentStatus::Rejected, $second->fresh()->status);
        $this->assertNull($second->fresh()->journal_entry_id);
    }

    public function test_api_auth_policies_and_self_approval(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $viewer = $this->userWithRole(RoleName::Viewer);
        [, $partner] = $this->linkedPartner();

        $this->getJson('/api/v1/partners')->assertUnauthorized();

        $token = $this->postJson('/api/v1/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->assertIsString($token);
        $this->withToken($token)->getJson('/api/v1/partners')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($viewer->createToken('api')->plainTextToken)
            ->postJson('/api/v1/partners', ['name' => 'Blocked Partner'])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $created = $this->withToken($token)
            ->postJson('/api/v1/withdrawals', $this->withdrawalPayload($partner, '500.00'))
            ->assertCreated();

        $approvalId = $created->json('approval_id');
        $this->assertNotNull($approvalId);

        $this->withToken($token)
            ->postJson('/api/v1/approvals/'.$approvalId.'/approve', ['comment' => 'I approve myself'])
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot approve your own transaction.');

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        $this->assertSame(DocumentStatus::Pending, $withdrawal->status);
        $this->assertNull($withdrawal->journal_entry_id);
    }

    private function dueMessage(User $user, string $kind): string
    {
        $notification = $user->fresh()->unreadNotifications()->where('data->kind', $kind)->first();
        $this->assertNotNull($notification);

        return (string) $notification->data['message'];
    }

    /**
     * @return array{0: User, 1: User, 2: Product, 3: Warehouse, 4: Supplier}
     */
    private function catalog(): array
    {
        $inventory = $this->userWithRole(RoleName::InventoryManager);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $catalog = app(CatalogService::class);
        $category = $catalog->createCategory($inventory, ['name' => 'Wallets']);
        $unit = $catalog->createUnit($inventory, ['name' => 'Piece', 'abbreviation' => 'pc']);
        $warehouse = $catalog->createWarehouse($inventory, ['name' => 'Main Store', 'address' => 'Dhaka']);
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
            'reorder_level' => '5',
            'status' => 'active',
            'description' => 'Test product',
        ]);

        return [$inventory, $accountant, $product, $warehouse, $supplier];
    }
}
