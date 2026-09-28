<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Partner;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Partner */
class PartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_code' => $this->partner_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'joining_date' => $this->joining_date?->toDateString(),
            'joining_date_formatted' => Format::date($this->joining_date),
            'ownership_percentage' => $this->ownership_percentage,
            'ownership_percentage_display' => Format::percent($this->ownership_percentage),
            'investment_percentage' => $this->investment_percentage,
            'investment_percentage_display' => Format::percent($this->investment_percentage),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'tone' => $this->status->tone(),
            ],
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'notes' => $this->notes,
            'created_at' => Format::dateTime($this->created_at),
        ];
    }
}
