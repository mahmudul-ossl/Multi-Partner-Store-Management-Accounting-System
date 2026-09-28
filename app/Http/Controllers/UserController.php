<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\RoleName;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserDirectory;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request, UserDirectory $directory): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'is_active', 'sort', 'direction']);

        return Inertia::render('Users/Index', [
            'users' => PaginatorPayload::make($directory->paginate($filters), UserResource::class),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'role' => (string) ($filters['role'] ?? ''),
                'is_active' => (string) ($filters['is_active'] ?? ''),
                'sort' => (string) ($filters['sort'] ?? 'name'),
                'direction' => (string) ($filters['direction'] ?? 'asc'),
            ],
            'roles' => array_map(
                fn (RoleName $role): array => ['value' => $role->value, 'label' => $role->label()],
                RoleName::cases(),
            ),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $action): RedirectResponse
    {
        $action->execute($user, $request->validated());

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user, DeleteUser $action): RedirectResponse
    {
        $this->authorize('delete', $user);

        $action->execute($user, $request->user());

        return redirect()->route('users.index')->with('success', 'User removed.');
    }
}
