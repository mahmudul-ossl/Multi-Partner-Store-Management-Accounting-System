<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Support\RolePermissionMap;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = array_map(function (RoleName $role): array {
            return [
                'name' => $role->value,
                'permissions' => array_map(
                    fn (PermissionName $permission): string => $permission->value,
                    RolePermissionMap::for($role),
                ),
            ];
        }, RoleName::cases());

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => array_map(
                fn (PermissionName $permission): string => $permission->value,
                PermissionName::cases(),
            ),
        ]);
    }
}
