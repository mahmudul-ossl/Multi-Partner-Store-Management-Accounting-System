<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Support\RolePermissionMap;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionTest extends FeatureTestCase
{
    public function test_the_full_permission_catalogue_and_roles_are_seeded(): void
    {
        $this->assertSame(count(PermissionName::cases()), Permission::query()->count());
        $this->assertSame(count(RoleName::cases()), Role::query()->count());

        foreach ([
            'partner.view',
            'partner.create',
            'partner.update',
            'partner.investment.create',
            'partner.investment.approve',
            'partner.withdrawal.create',
            'partner.withdrawal.approve',
            'promotion.create',
            'promotion.approve',
            'expense.create',
            'expense.approve',
            'purchase.create',
            'purchase.approve',
            'stock.adjust',
            'stock.adjust.approve',
            'report.view',
            'accounting.view',
            'balance_sheet.view',
            'profit_loss.view',
            'user.manage',
            'role.manage',
            'settings.manage',
        ] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }
    }

    public function test_super_admin_holds_every_permission_and_operational_roles_stay_narrow(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $inventory = $this->userWithRole(RoleName::InventoryManager);
        $partner = $this->userWithRole(RoleName::Partner);
        $viewer = $this->userWithRole(RoleName::Viewer);

        foreach (PermissionName::cases() as $permission) {
            $this->assertTrue($superAdmin->can($permission->value), $permission->value);
        }

        $this->assertTrue($inventory->can('stock.adjust'));
        $this->assertFalse($inventory->can('stock.adjust.approve'));
        $this->assertFalse($inventory->can('purchase.approve'));
        $this->assertFalse($partner->can('partner.create'));
        $this->assertFalse($partner->can('user.manage'));
        $this->assertTrue($partner->can('partner.investment.create'));
        $this->assertFalse($partner->can('partner.investment.approve'));
        $this->assertFalse($viewer->can('partner.update'));
        $this->assertTrue($viewer->can('report.view'));
        $this->assertFalse($this->userWithRole(RoleName::Admin)->can('role.manage'));
    }

    public function test_only_super_admin_can_open_the_role_matrix(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('roles.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Roles/Index')
                ->has('roles', count(RoleName::cases()))
                ->has('permissions', count(PermissionName::cases())));
    }

    public function test_role_map_only_references_known_permissions(): void
    {
        foreach (RoleName::cases() as $role) {
            foreach (RolePermissionMap::for($role) as $permission) {
                $this->assertInstanceOf(PermissionName::class, $permission);
            }
        }
    }
}
