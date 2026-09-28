<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AccountTransfer;
use App\Services\Accounting\AccountingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccountTransfer */
class AccountTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(AccountingPresenter::class)->accountTransfer($this->resource);
    }
}
