<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Partner;
use App\Services\Accounting\PeriodService;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\InvestmentService;
use App\Services\Inventory\CatalogService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Sales\SaleService;
use App\Services\Spending\PromotionService;
use App\Support\ChartAccountCode;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

class ClosedPeriodValidationTest extends FinanceTestCase
{
    public function test_closed_period_dates_fail_on_the_field_and_are_not_logged(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $catalog = app(CatalogService::class);
        $category = $catalog->createCategory($admin, ['name' => 'Wallets']);
        $unit = $catalog->createUnit($admin, ['name' => 'Piece', 'abbreviation' => 'pc']);
        $warehouse = $catalog->createWarehouse($admin, ['name' => 'Main Store', 'address' => 'Dhaka']);
        $product = $catalog->createProduct($admin, [
            'sku' => 'WAL-CLOSE',
            'barcode' => '8901000000991',
            'name' => 'Closed Period Wallet',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => '100.00',
            'selling_price' => '180.00',
            'wholesale_price' => '140.00',
            'minimum_stock' => '1',
            'reorder_level' => '2',
            'status' => 'active',
        ]);
        $supplier = $catalog->createSupplier($admin, ['name' => 'City Paper', 'phone' => '01711111111']);
        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'phone' => '01700000000',
            'status' => CustomerStatus::Active,
        ]);
        $inventory = $this->userWithRole(RoleName::InventoryManager);
        $opening = app(StockAdjustmentService::class)->create($inventory, [
            'kind' => 'opening',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => '5',
            'unit_cost' => '100.0000',
            'transaction_date' => '2026-07-01',
            'reason' => 'Opening stock.',
        ]);
        app(ApprovalService::class)->approve($opening->approvalRequest, $admin, 'Counted.');

        $sale = app(SaleService::class)->create($admin, [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-07-02',
            'discount' => '0.00',
            'delivery' => '0.00',
            'paid_amount' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '180.00',
            ]],
        ]);
        $purchase = app(PurchaseService::class)->create($admin, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'transaction_date' => '2026-07-02',
            'paid_amount' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_cost' => '80.00',
            ]],
        ]);
        app(ApprovalService::class)->approve($purchase->approvalRequest, $accountant, 'Received.');
        $promotion = app(PromotionService::class)->create($admin, [
            'name' => 'Boishakh ads',
            'platform' => 'facebook',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-31',
            'budget' => '1000.00',
            'status' => 'active',
        ]);

        app(PeriodService::class)->close($admin, '2026-06-30', 'June close');
        $sale->load('items');
        $purchase->load('items');

        $partner = Partner::factory()->create(['ownership_percentage' => '100.0000', 'investment_percentage' => '100.0000']);
        $other = Partner::factory()->create(['ownership_percentage' => '0.0000', 'investment_percentage' => '0.0000']);
        $cash = $this->cashAccount();
        $bank = FinancialAccount::query()->where('name', 'DBBL Bank')->firstOrFail();
        $chart = ChartOfAccount::query()->where('code', ChartAccountCode::Cash)->firstOrFail();
        $closed = '2026-06-15';
        $message = 'That date is in a closed period.';
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->level === 'error') {
                $logged[] = $event->message;
            }
        });

        $cases = [
            ['expenses.store', ['category' => 'rent', 'amount' => '100.00', 'transaction_date' => $closed, 'description' => 'Rent', 'partner_id' => '', 'payment_method' => 'cash', 'financial_account_id' => $cash->id], 'transaction_date'],
            ['investments.store', ['partner_id' => $partner->id, 'amount' => '100.00', 'transaction_date' => $closed, 'payment_method' => 'cash', 'financial_account_id' => $cash->id], 'transaction_date'],
            ['withdrawals.store', ['partner_id' => $partner->id, 'amount' => '50.00', 'transaction_date' => $closed, 'reason' => 'Drawing', 'payment_method' => 'cash', 'financial_account_id' => $cash->id], 'transaction_date'],
            ['transfers.store', ['from_partner_id' => $partner->id, 'to_partner_id' => $other->id, 'amount' => '25.00', 'transaction_date' => $closed], 'transaction_date'],
            ['accounting.transfers.store', ['from_financial_account_id' => $cash->id, 'to_financial_account_id' => $bank->id, 'amount' => '10.00', 'transaction_date' => $closed], 'transaction_date'],
            ['accounting.allocations.store', ['amount' => '20.00', 'transaction_date' => $closed, 'method' => 'ownership'], 'transaction_date'],
            ['accounting.manual-journals.store', [
                'entry_date' => $closed,
                'description' => 'Closed rent',
                'lines' => [
                    ['account_code' => ChartAccountCode::Rent, 'debit' => '10.00', 'credit' => '0.00'],
                    ['account_code' => ChartAccountCode::OpeningBalanceEquity, 'debit' => '0.00', 'credit' => '10.00'],
                ],
            ], 'entry_date'],
            ['accounting.accounts.store', ['name' => 'Side drawer', 'type' => 'cash', 'chart_of_account_id' => $chart->id, 'opening_balance' => '15.00', 'opening_date' => $closed], 'opening_date'],
            ['inventory.adjustments.store', ['kind' => 'opening', 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1', 'unit_cost' => '10.0000', 'transaction_date' => $closed, 'reason' => 'Late count'], 'transaction_date'],
            ['inventory.purchases.store', ['supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'transaction_date' => $closed, 'paid_amount' => '0.00', 'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '40.00']]], 'transaction_date'],
            ['inventory.returns.store', ['purchase_id' => $purchase->id, 'transaction_date' => $closed, 'items' => [['purchase_item_id' => $purchase->items->first()->id, 'quantity' => '1']]], 'transaction_date'],
            ['inventory.supplier-payments.store', ['supplier_id' => $supplier->id, 'financial_account_id' => $cash->id, 'payment_method' => 'cash', 'amount' => '10.00', 'payment_date' => $closed], 'payment_date'],
            ['sales.orders.store', ['customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'transaction_date' => $closed, 'discount' => '0.00', 'delivery' => '0.00', 'paid_amount' => '0.00', 'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '180.00']]], 'transaction_date'],
            ['sales.payments.store', ['sale_id' => $sale->id, 'financial_account_id' => $cash->id, 'payment_method' => 'cash', 'amount' => '10.00', 'payment_date' => $closed], 'payment_date'],
            ['sales.refunds.store', ['sale_id' => $sale->id, 'financial_account_id' => $cash->id, 'payment_method' => 'cash', 'amount' => '10.00', 'transaction_date' => $closed], 'transaction_date'],
            ['sales.cancellations.store', ['sale_id' => $sale->id, 'transaction_date' => $closed, 'reason' => 'Wrong day'], 'transaction_date'],
            ['sales.returns.store', ['sale_id' => $sale->id, 'transaction_date' => $closed, 'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => '1']]], 'transaction_date'],
            ['promotions.contributions.store', ['promotion' => $promotion, 'partner_id' => $partner->id, 'funded_by' => 'partner', 'amount' => '50.00', 'transaction_date' => $closed], 'transaction_date'],
        ];

        foreach ($cases as [$route, $payload, $field]) {
            $response = $this->actingAs($admin)
                ->from(route('dashboard'))
                ->post(route($route, $route === 'promotions.contributions.store' ? $promotion : []), $payload);
            $this->assertTrue(
                $response->isRedirect(route('dashboard')),
                $route.' status '.$response->status(),
            );
            $response->assertSessionHasErrors([$field => $message]);
        }

        $this->actingAs($admin)
            ->from(route('accounting.periods.index'))
            ->post(route('accounting.periods.store'), ['closed_through' => '2026-06-20', 'note' => 'Again'])
            ->assertRedirect(route('accounting.periods.index'))
            ->assertSessionHasErrors(['closed_through' => 'The books are already closed through 30-Jun-2026.']);

        $this->actingAs($admin)
            ->from(route('accounting.manual-journals.index'))
            ->post(route('accounting.manual-journals.store'), [
                'entry_date' => '2026-07-20',
                'description' => 'Out of balance',
                'lines' => [
                    ['account_code' => ChartAccountCode::Rent, 'debit' => '10.00', 'credit' => '0.00'],
                    ['account_code' => ChartAccountCode::OpeningBalanceEquity, 'debit' => '0.00', 'credit' => '4.00'],
                ],
            ])
            ->assertRedirect(route('accounting.manual-journals.index'))
            ->assertSessionHasErrors(['lines' => 'Debits 10.00 do not equal credits 4.00.']);

        $this->actingAs($admin)->post(route('expenses.store'), [
            'category' => 'rent',
            'amount' => '80.00',
            'transaction_date' => '2026-07-20',
            'description' => 'July rent',
            'partner_id' => '',
            'payment_method' => 'cash',
            'financial_account_id' => $cash->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', ['description' => 'July rent']);
        $this->assertSame([], $logged, implode(' | ', $logged));
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_approving_into_a_closed_period_is_a_flash_and_is_not_logged(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $accountant = $this->userWithRole(RoleName::Accountant);
        $partner = Partner::factory()->create();
        $investment = app(InvestmentService::class)->create($accountant, [
            'partner_id' => $partner->id,
            'amount' => '100.00',
            'transaction_date' => '2026-07-10',
            'payment_method' => 'cash',
            'financial_account_id' => $this->cashAccount()->id,
        ]);
        app(PeriodService::class)->close($admin, '2026-07-31', 'July close');

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->level === 'error') {
                $logged[] = $event->message;
            }
        });

        $this->actingAs($admin)
            ->from(route('approvals.show', $investment->approvalRequest))
            ->post(route('approvals.approve', $investment->approvalRequest), ['comment' => 'Too late.'])
            ->assertRedirect(route('approvals.show', $investment->approvalRequest))
            ->assertSessionHas('error', 'That date is in a closed period.');

        $investment->refresh();
        $this->assertSame(DocumentStatus::Pending, $investment->status);
        $this->assertNull($investment->journal_entry_id);
        $this->assertSame([], $logged);
    }

    public function test_a_bad_statement_period_is_a_flash_and_is_not_logged(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->level === 'error') {
                $logged[] = $event->message;
            }
        });

        $this->actingAs($admin)
            ->from(route('accounting.reports.profit-loss'))
            ->get(route('accounting.reports.profit-loss', ['period' => 'nope']))
            ->assertRedirect(route('accounting.reports.profit-loss'))
            ->assertSessionHas('error', 'Choose a profit and loss period.');

        $this->actingAs($admin)
            ->from(route('accounting.reports.balance-sheet'))
            ->get(route('accounting.reports.balance-sheet', ['as_of' => 'yesterday']))
            ->assertRedirect(route('accounting.reports.balance-sheet'))
            ->assertSessionHas('error', 'Choose a statement date.');

        $this->assertSame([], $logged);
    }
}
