<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ManualJournal;
use App\Services\Accounting\AccountingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ManualJournal */
class ManualJournalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(AccountingPresenter::class)->manualJournal($this->resource);
    }
}
