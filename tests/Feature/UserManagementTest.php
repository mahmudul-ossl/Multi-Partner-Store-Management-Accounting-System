<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;

class UserManagementTest extends FeatureTestCase
{
    private const PASSWORD = 'SuperAdmin#2026';

    public function test_admin_can_list_users(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data')
                ->has('roles', 7));
    }

    public function test_viewer_cannot_manage_users(): void
    {
        $viewer = $this->userWithRole(RoleName::Viewer);

        $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('users.store'), $this->validUser())->assertForbidden();
    }

    public function test_admin_can_create_a_user_and_the_change_is_audited(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('users.store'), $this->validUser())
            ->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'new.user@mpstore.test')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole(RoleName::Accountant->value));
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $created->id,
            'notifiable_type' => User::class,
        ]);

        $log = AuditLog::query()->where('action', AuditAction::Created)->where('model_id', $created->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode($log->new_values));
    }

    public function test_user_creation_rejects_a_weak_password(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->post(route('users.store'), $this->validUser(['password' => 'password', 'password_confirmation' => 'password']))
            ->assertSessionHasErrors('password');
    }

    public function test_admin_can_update_a_user_and_password_changes_are_redacted(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $user = $this->userWithRole(RoleName::Viewer, ['email' => 'viewer.one@mpstore.test']);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Updated Viewer',
                'email' => 'viewer.one@mpstore.test',
                'phone' => '01719999999',
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
                'role' => RoleName::SalesManager->value,
                'is_active' => true,
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Updated Viewer', $user->name);
        $this->assertTrue($user->hasRole(RoleName::SalesManager->value));

        $log = AuditLog::query()->where('action', AuditAction::Updated)->where('model_id', $user->id)->first();
        $this->assertSame('[redacted]', $log?->new_values['password'] ?? null);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode($log?->new_values));
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertForbidden();

        $this->assertNotSoftDeleted($admin);
    }

    public function test_the_last_super_admin_cannot_be_demoted_or_deactivated(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->put(route('users.update', $superAdmin), [
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'phone' => null,
                'role' => RoleName::Admin->value,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->put(route('users.update', $superAdmin), [
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'phone' => null,
                'role' => RoleName::SuperAdmin->value,
                'is_active' => false,
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->delete(route('users.destroy', $superAdmin))
            ->assertSessionHasErrors('role');

        $this->assertTrue($superAdmin->fresh()->hasRole(RoleName::SuperAdmin->value));
        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        $admin = $this->userWithRole(RoleName::Admin, ['name' => 'Filter Admin']);
        $this->userWithRole(RoleName::Viewer, ['name' => 'Filter Viewer']);

        $this->actingAs($admin)
            ->get(route('users.index', ['role' => RoleName::Viewer->value]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Filter Viewer'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUser(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Accountant',
            'email' => 'new.user@mpstore.test',
            'phone' => '01810000000',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => RoleName::Accountant->value,
            'is_active' => true,
        ], $overrides);
    }
}
