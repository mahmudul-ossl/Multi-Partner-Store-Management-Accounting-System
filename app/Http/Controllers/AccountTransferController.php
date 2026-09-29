<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\Accounting\AccountTransferRequest;
use App\Http\Resources\AccountTransferResource;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Services\Accounting\AccountingDirectory;
use App\Services\Accounting\AccountingPresenter;
use App\Services\Accounting\AccountTransferService;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountTransferController extends Controller
{
    public function index(Request $request, AccountingDirectory $directory): Response
    {
        $this->authorize('viewAny', AccountTransfer::class);
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Accounting/Transfers', [
            'transfers' => PaginatorPayload::make(
                $directory->transfers($filters),
                AccountTransferResource::class,
            ),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'statuses' => DocumentStatus::options(),
            'accounts' => $this->accounts(),
        ]);
    }

    public function store(AccountTransferRequest $request, AccountTransferService $transfers): RedirectResponse
    {
        $transfer = $transfers->create($request->user(), $request->validated());

        return redirect()->route('accounting.transfers.show', $transfer)->with('success', 'Transfer submitted for approval.');
    }

    public function show(AccountTransfer $accountTransfer, AccountingPresenter $presenter): Response
    {
        $this->authorize('view', $accountTransfer);

        return Inertia::render('Accounting/TransferShow', [
            'transfer' => $presenter->accountTransfer($accountTransfer),
            'can' => [
                'cancel' => request()->user()?->can('cancel', $accountTransfer) ?? false,
            ],
        ]);
    }

    public function cancel(AccountTransfer $accountTransfer, AccountTransferService $transfers): RedirectResponse
    {
        $this->authorize('cancel', $accountTransfer);
        $transfers->cancel($accountTransfer, request()->user());

        return redirect()->route('accounting.transfers.show', $accountTransfer)->with('success', 'Transfer cancelled.');
    }

    /**
     * @return list<array{id: int, name: string, type: string}>
     */
    private function accounts(): array
    {
        return FinancialAccount::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (FinancialAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->label(),
            ])->all();
    }
}
