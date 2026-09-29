<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PartnerInvestment;
use App\Services\Finance\FinancePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PartnerInvestment */
class InvestmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(FinancePresenter::class)->investment($this->resource);
    }
}
