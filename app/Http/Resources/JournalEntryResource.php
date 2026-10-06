<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\JournalEntry;
use App\Services\Accounting\AccountingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntry */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(AccountingPresenter::class)->journalEntry($this->resource, false);
    }
}
