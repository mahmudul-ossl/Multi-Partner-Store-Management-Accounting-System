<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingDirectory;
use App\Services\Accounting\AccountingPresenter;
use App\Services\Accounting\JournalReversalService;
use App\Support\PaginatorPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function index(Request $request, AccountingDirectory $directory): Response
    {
        $this->authorize('viewAny', JournalEntry::class);
        $filters = $request->only(['search', 'status', 'from', 'to']);

        return Inertia::render('Accounting/Entries', [
            'entries' => PaginatorPayload::make(
                $directory->entries($filters),
                JournalEntryResource::class,
            ),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'from' => (string) ($filters['from'] ?? ''),
                'to' => (string) ($filters['to'] ?? ''),
            ],
            'statuses' => DocumentStatus::options(),
        ]);
    }

    public function show(JournalEntry $journalEntry, AccountingPresenter $presenter): Response
    {
        $this->authorize('view', $journalEntry);

        return Inertia::render('Accounting/EntryShow', [
            'entry' => $presenter->journalEntry($journalEntry),
            'can' => [
                'reverse' => (request()->user()?->can('reverse', $journalEntry) ?? false)
                    && $presenter->canReverseSource($journalEntry),
            ],
        ]);
    }

    public function reverse(Request $request, JournalEntry $journalEntry, JournalReversalService $reversals): RedirectResponse
    {
        $this->authorize('reverse', $journalEntry);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $reversals->reverse($journalEntry, $request->user(), $data['reason']);

        return redirect()->route('accounting.entries.show', $journalEntry)->with('success', 'Journal reversed.');
    }
}
