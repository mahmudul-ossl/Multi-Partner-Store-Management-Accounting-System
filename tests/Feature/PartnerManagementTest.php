<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\PartnerStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\User;

class PartnerManagementTest extends FeatureTestCase
{
    public function test_admin_can_create_update_and_soft_delete_a_partner(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('partners.store'), $this->payload())
            ->assertRedirect();

        $partner = Partner::query()->where('partner_code', 'P-0100')->first();
        $this->assertNotNull($partner);
        $this->assertSame('18.5000', $partner->ownership_percentage);
        $this->assertSame('22.2500', $partner->investment_percentage);
        $this->assertNotSame($partner->ownership_percentage, $partner->investment_percentage);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Created->value,
            'model_type' => Partner::class,
            'model_id' => $partner->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('partners.update', $partner), $this->payload([
                'name' => 'Rahim Uddin Updated',
                'ownership_percentage' => '19.0000',
            ]))
            ->assertRedirect(route('partners.show', $partner));

        $this->assertSame('Rahim Uddin Updated', $partner->fresh()->name);
        $this->assertSame('19.0000', $partner->fresh()->ownership_percentage);
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::Updated)->where('model_id', $partner->id)->exists());

        $this->actingAs($admin)
            ->delete(route('partners.destroy', $partner))
            ->assertRedirect(route('partners.index'));

        $this->assertSoftDeleted($partner);
        $this->assertNotNull(Partner::withTrashed()->find($partner->id));
    }

    public function test_partner_code_is_generated_when_omitted(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        Partner::factory()->create(['partner_code' => 'P-0003']);

        $this->actingAs($admin)->post(route('partners.store'), $this->payload([
            'partner_code' => null,
            'email' => 'generated@mpstore.test',
        ]))->assertRedirect();

        $this->assertDatabaseHas('partners', [
            'email' => 'generated@mpstore.test',
            'partner_code' => 'P-0004',
        ]);
    }

    public function test_validation_rejects_invalid_partner_data(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('partners.store'), $this->payload([
                'name' => '',
                'ownership_percentage' => '140',
                'investment_percentage' => '-1',
                'status' => 'archived',
                'email' => 'not-an-email',
            ]))
            ->assertSessionHasErrors(['name', 'ownership_percentage', 'investment_percentage', 'status', 'email']);
    }

    public function test_a_user_cannot_be_linked_to_two_partners(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $account = User::factory()->create();
        $first = Partner::factory()->create(['user_id' => $account->id]);
        $second = Partner::factory()->create();

        $this->actingAs($admin)
            ->put(route('partners.user.update', $second), ['user_id' => $account->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame($account->id, $first->fresh()->user_id);
        $this->assertNull($second->fresh()->user_id);
    }

    public function test_admin_can_link_and_unlink_a_partner_user(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $account = $this->userWithRole(RoleName::Partner);
        $partner = Partner::factory()->create();

        $this->actingAs($admin)
            ->put(route('partners.user.update', $partner), ['user_id' => $account->id])
            ->assertRedirect(route('partners.show', $partner));

        $this->assertSame($account->id, $partner->fresh()->user_id);

        $this->actingAs($admin)
            ->delete(route('partners.user.destroy', $partner))
            ->assertRedirect(route('partners.show', $partner));

        $this->assertNull($partner->fresh()->user_id);
    }

    public function test_partner_role_can_view_only_their_own_record(): void
    {
        $account = $this->userWithRole(RoleName::Partner);
        $own = Partner::factory()->create(['user_id' => $account->id, 'name' => 'Own Partner']);
        $other = Partner::factory()->create(['name' => 'Other Partner']);

        $this->actingAs($account)
            ->get(route('partners.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('partners.data', 1)
                ->where('partners.data.0.name', 'Own Partner'));

        $this->actingAs($account)->get(route('partners.show', $own))->assertOk();
        $this->actingAs($account)->get(route('partners.show', $other))->assertForbidden();
        $this->actingAs($account)->post(route('partners.store'), $this->payload())->assertForbidden();
        $this->actingAs($account)->put(route('partners.update', $own), $this->payload())->assertForbidden();
    }

    public function test_viewer_can_view_partners_but_cannot_change_them(): void
    {
        $viewer = $this->userWithRole(RoleName::Viewer);
        $partner = Partner::factory()->create();

        $this->actingAs($viewer)->get(route('partners.index'))->assertOk();
        $this->actingAs($viewer)->get(route('partners.show', $partner))->assertOk();
        $this->actingAs($viewer)->delete(route('partners.destroy', $partner))->assertForbidden();
    }

    public function test_partner_list_can_be_searched_and_filtered(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        Partner::factory()->create(['name' => 'Fatema Akter', 'status' => PartnerStatus::Active, 'partner_code' => 'P-2001']);
        Partner::factory()->create(['name' => 'Tanvir Ahmed', 'status' => PartnerStatus::Suspended, 'partner_code' => 'P-2002']);

        $this->actingAs($admin)
            ->get(route('partners.index', ['search' => 'Fatema', 'status' => 'active']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('partners.data', 1)
                ->where('partners.data.0.partner_code', 'P-2001'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'partner_code' => 'P-0100',
            'name' => 'Rahim Uddin',
            'phone' => '01711111101',
            'email' => 'rahim@mpstore.test',
            'address' => 'Dhanmondi, Dhaka',
            'joining_date' => '2024-01-15',
            'ownership_percentage' => '18.5',
            'investment_percentage' => '22.25',
            'status' => PartnerStatus::Active->value,
            'user_id' => null,
            'notes' => 'Separate ownership and investment shares.',
        ], $overrides);
    }
}
