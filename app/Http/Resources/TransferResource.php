<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PartnerTransfer;
use App\Services\Finance\FinancePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PartnerTransfer */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(FinancePresenter::class)->transfer($this->resource);
    }
}
