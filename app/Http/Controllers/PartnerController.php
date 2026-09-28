<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Partners\CreatePartner;
use App\Actions\Partners\DeletePartner;
use App\Actions\Partners\UpdatePartner;
use App\Enums\PartnerStatus;
use App\Http\Requests\Partners\StorePartnerRequest;
use App\Http\Requests\Partners\UpdatePartnerRequest;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerDirectory;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function index(Request $request, PartnerDirectory $directory): Response
    {
        $this->authorize('viewAny', Partner::class);

        $filters = $request->only(['search', 'status', 'sort', 'direction']);

        return Inertia::render('Partners/Index', [
            'partners' => PaginatorPayload::make(
                $directory->paginate($request->user(), $filters),
                PartnerResource::class,
            ),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'sort' => (string) ($filters['sort'] ?? 'name'),
                'direction' => (string) ($filters['direction'] ?? 'asc'),
            ],
            'statuses' => PartnerStatus::options(),
            'linkableUsers' => $this->linkableUsers(),
        ]);
    }

    public function store(StorePartnerRequest $request, CreatePartner $action): RedirectResponse
    {
        $partner = $action->execute($request->validated());

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', 'Partner created.');
    }

    public function show(Partner $partner): Response
    {
        $this->authorize('view', $partner);

        $partner->load('user:id,name,email');

        return Inertia::render('Partners/Show', [
            'partner' => (new PartnerResource($partner))->resolve(),
            'statuses' => PartnerStatus::options(),
            'linkableUsers' => $this->linkableUsers($partner),
            'can' => [
                'update' => request()->user()?->can('update', $partner) ?? false,
                'delete' => request()->user()?->can('delete', $partner) ?? false,
            ],
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner, UpdatePartner $action): RedirectResponse
    {
        $action->execute($partner, $request->validated());

        return redirect()
            ->route('partners.show', $partner)
            ->with('success', 'Partner updated.');
    }

    public function destroy(Partner $partner, DeletePartner $action): RedirectResponse
    {
        $this->authorize('delete', $partner);

        $action->execute($partner);

        return redirect()->route('partners.index')->with('success', 'Partner archived.');
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function linkableUsers(?Partner $partner = null): array
    {
        return User::query()
            ->where(function ($query) use ($partner): void {
                $query->whereDoesntHave('partner');

                if ($partner?->user_id) {
                    $query->orWhere('id', $partner->user_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }
}
