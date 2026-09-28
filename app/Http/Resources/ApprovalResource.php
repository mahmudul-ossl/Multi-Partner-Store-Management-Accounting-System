<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApprovalRequest;
use App\Services\Finance\FinancePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApprovalRequest */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(FinancePresenter::class)->approval($this->resource, $request->user());
    }
}
