<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Jobs\DeliverOperationalAlert;
use App\Models\PartnerWithdrawal;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\ApprovalActivity;
use App\Notifications\OperationalAlert;
use App\Services\Approvals\ApprovalService;
use App\Support\Money;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationsTest extends FinanceTestCase
{
    public function test_database_queue_delivers_one_current_due_alert_per_subject(): void
    {
        config(['queue.default' => 'database']);

        $this->seed();

        $sale = Sale::query()->where('reference', 'SAL-00002')->firstOrFail();
        $purchase = Purchase::query()->where('reference', 'PUR-00001')->firstOrFail();
        $this->assertSame('830.00', (string) $sale->due_amount);
        $this->assertSame('0.00', (string) $purchase->due_amount);

        $queued = $this->queuedOperationalAlerts();
        $customerJobs = $queued->filter(fn (DeliverOperationalAlert $job): bool => $job->kind === 'customer_due' && $job->subjectId === $sale->id);
        $supplierJobs = $queued->filter(fn (DeliverOperationalAlert $job): bool => $job->kind === 'supplier_due' && $job->subjectId === $purchase->id);

        $this->assertGreaterThan(1, $customerJobs->count());
        $this->assertGreaterThan(1, $supplierJobs->count());

        $exit = Artisan::call('queue:work', [
            'connection' => 'database',
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertSame(0, DB::table('jobs')->count(), Artisan::output());
        $this->assertSame(0, DB::table('failed_jobs')->count());

        $eligible = User::permission(PermissionName::SaleView->value)->where('is_active', true)->count();
        $alerts = DatabaseNotification::query()->where('alert_key', 'customer_due:'.$sale->id)->get();
        $amount = Money::of('830.00')->formatted();

        $this->assertGreaterThan(0, $eligible);
        $this->assertCount($eligible, $alerts);
        $this->assertSame($eligible, $alerts->unique('notifiable_id')->count());

        foreach ($alerts as $alert) {
            $this->assertNull($alert->read_at);
            $this->assertSame('customer_due', $alert->data['kind']);
            $this->assertStringContainsString($amount, (string) $alert->data['message']);
            $this->assertStringContainsString('SAL-00002', (string) $alert->data['message']);
        }

        $this->assertSame(0, DatabaseNotification::query()->where('alert_key', 'supplier_due:'.$purchase->id)->count());
        $this->assertFalse(
            DatabaseNotification::query()
                ->where('data->kind', 'supplier_due')
                ->where('data->message', 'like', '%PUR-00001%')
                ->exists()
        );
    }

    public function test_resolving_an_approval_marks_the_request_read_and_leaves_the_decision_unread(): void
    {
        [$partnerUser, $partner] = $this->linkedPartner('Rahim Notify');
        $accountant = $this->userWithRole(RoleName::Accountant);

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '4000.00'))
            ->assertRedirect();

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        $required = $accountant->fresh()->unreadNotifications()
            ->where('data->kind', 'approval_required')
            ->where('data->approval_request_id', $withdrawal->approvalRequest->id)
            ->first();

        $this->assertNotNull($required);

        app(ApprovalService::class)->approve($withdrawal->approvalRequest, $accountant, 'Paid.');

        $this->assertNotNull($required->fresh()->read_at);
        $this->assertFalse(
            $accountant->fresh()->unreadNotifications()
                ->where('data->kind', 'approval_required')
                ->where('data->approval_request_id', $withdrawal->approvalRequest->id)
                ->exists()
        );

        $decision = $partnerUser->fresh()->unreadNotifications()->where('data->kind', 'approval_decided')->first();
        $this->assertNotNull($decision);
        $this->assertNull($decision->read_at);

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '2500.00'))
            ->assertRedirect();

        $second = PartnerWithdrawal::query()->whereKeyNot($withdrawal->id)->firstOrFail();
        $pending = $accountant->fresh()->unreadNotifications()
            ->where('data->kind', 'approval_required')
            ->where('data->approval_request_id', $second->approvalRequest->id)
            ->first();
        $this->assertNotNull($pending);

        app(ApprovalService::class)->reject($second->approvalRequest, $accountant, 'Not this month.');

        $this->assertNotNull($pending->fresh()->read_at);
        $this->assertSame(2, $partnerUser->fresh()->unreadNotifications()->where('data->kind', 'approval_decided')->count());
    }

    public function test_a_queued_approval_notice_is_skipped_once_the_request_is_resolved(): void
    {
        config(['queue.default' => 'database']);

        [$partnerUser, $partner] = $this->linkedPartner('Karim Queue');
        $accountant = $this->userWithRole(RoleName::Accountant);

        $this->actingAs($partnerUser)
            ->post(route('withdrawals.store'), $this->withdrawalPayload($partner, '1500.00'))
            ->assertRedirect();

        $this->assertGreaterThan(0, DB::table('jobs')->count());

        $withdrawal = PartnerWithdrawal::query()->firstOrFail();
        app(ApprovalService::class)->approve($withdrawal->approvalRequest, $accountant, 'Paid.');

        $exit = Artisan::call('queue:work', [
            'connection' => 'database',
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(
            0,
            $accountant->fresh()->unreadNotifications()->where('data->kind', 'approval_required')->count()
        );
        $this->assertNotNull(
            $partnerUser->fresh()->unreadNotifications()->where('data->kind', 'approval_decided')->first()
        );
    }

    public function test_the_bell_keeps_operational_alerts_beside_a_capped_approval_list(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $message = 'Jamal Uddin still owes '.Money::of('830.00')->formatted().' on SAL-00002.';

        $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => OperationalAlert::class,
            'data' => [
                'kind' => 'customer_due',
                'subject_id' => 2,
                'title' => 'Customer payment due',
                'message' => $message,
                'url' => '/sales/orders/2',
            ],
            'alert_key' => 'customer_due:2',
            'created_at' => now()->subHour(),
        ]);

        for ($index = 0; $index < 8; $index++) {
            $admin->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => ApprovalActivity::class,
                'data' => [
                    'kind' => 'approval_required',
                    'approval_request_id' => 500 + $index,
                    'title' => 'New approval required',
                    'message' => 'Approval '.$index,
                    'url' => '/approvals/1',
                ],
                'created_at' => now()->subMinutes(8 - $index),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread', 9)
                ->has('notifications.operational', 1)
                ->where('notifications.operational.0.message', $message)
                ->has('notifications.approvals', 5)
                ->where('notifications.approvals.0.message', 'Approval 7'));
    }

    public function test_the_notification_list_is_paginated(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        for ($index = 0; $index < 16; $index++) {
            $admin->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => OperationalAlert::class,
                'data' => [
                    'kind' => 'low_stock',
                    'subject_id' => $index + 1,
                    'title' => 'Low stock',
                    'message' => 'Item '.$index,
                    'url' => '/inventory/stock/low',
                ],
                'created_at' => now()->subMinutes(16 - $index),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->where('notifications.unread', 16)
                ->has('notifications.operational', 8)
                ->where('notifications.operational.0.message', 'Item 15')
                ->has('notifications.approvals', 0)
                ->missing('notifications.per_page')
                ->missing('notifications.data')
                ->where('notificationList.per_page', 15)
                ->where('notificationList.total', 16)
                ->where('notificationList.current_page', 1)
                ->where('notificationList.last_page', 2)
                ->where('notificationList.from', 1)
                ->where('notificationList.to', 15)
                ->has('notificationList.data', 15)
                ->where('notificationList.data.0.message', 'Item 15'));

        $this->actingAs($admin)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread', 16)
                ->has('notifications.operational', 8)
                ->where('notificationList.current_page', 2)
                ->where('notificationList.from', 16)
                ->where('notificationList.to', 16)
                ->has('notificationList.data', 1)
                ->where('notificationList.data.0.message', 'Item 0'));
    }

    /**
     * @return Collection<int, DeliverOperationalAlert>
     */
    private function queuedOperationalAlerts(): Collection
    {
        return DB::table('jobs')->pluck('payload')
            ->map(function (string $payload): ?DeliverOperationalAlert {
                $decoded = json_decode($payload, true);
                $command = $decoded['data']['command'] ?? null;

                if (! is_string($command) || ! str_contains($command, DeliverOperationalAlert::class)) {
                    return null;
                }

                $job = unserialize($command, ['allowed_classes' => true]);

                return $job instanceof DeliverOperationalAlert ? $job : null;
            })
            ->filter()
            ->values();
    }
}
