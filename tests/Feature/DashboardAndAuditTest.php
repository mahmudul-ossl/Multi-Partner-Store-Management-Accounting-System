<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Partner;
use RuntimeException;

class DashboardAndAuditTest extends FeatureTestCase
{
    public function test_dashboard_shows_real_counts_and_labelled_placeholders(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        Partner::factory()->count(3)->create();
        Partner::factory()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->where('summary.partners_total', 4)
                ->where('summary.partners_inactive', 1)
                ->where('summary.users_total', 1)
                ->where('summary.pending_approvals', 0)
                ->where('summary.placeholders.0.key', 'inventory_value')
                ->where('summary.placeholders.0.note', 'Coming in a later phase')
                ->where('summary.placeholders.1.key', 'sales'));
    }

    public function test_a_partner_dashboard_counts_only_their_own_record(): void
    {
        $account = $this->userWithRole(RoleName::Partner);
        Partner::factory()->create(['user_id' => $account->id]);
        Partner::factory()->count(2)->create();

        $this->actingAs($account)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.partners_total', 1)
                ->where('summary.show_user_counts', false));
    }

    public function test_audit_log_page_is_permission_gated_and_rows_are_immutable(): void
    {
        $viewer = $this->userWithRole(RoleName::Viewer);
        $accountant = $this->userWithRole(RoleName::Accountant);

        $this->actingAs($viewer)->get(route('audit-logs.index'))->assertForbidden();

        $log = AuditLog::query()->create([
            'user_id' => $accountant->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($accountant)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AuditLogs/Index')->has('logs.data', 1));

        $this->expectException(RuntimeException::class);
        $log->update(['ip_address' => '10.0.0.1']);
    }

    public function test_database_seeder_creates_the_demo_partnership(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'superadmin@mpstore.test']);
        $this->assertDatabaseHas('partners', [
            'partner_code' => 'P-0001',
            'name' => 'Rahim Uddin',
            'ownership_percentage' => '18.0000',
            'investment_percentage' => '20.0000',
        ]);
        $this->assertDatabaseCount('partners', 8);
        $this->assertSame(5, Partner::query()->whereNotNull('user_id')->count());
    }
}
