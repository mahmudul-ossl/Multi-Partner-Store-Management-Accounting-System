<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $model = class_basename((string) $this->model_type);

        return [
            'id' => $this->id,
            'action' => [
                'value' => $this->action->value,
                'label' => $this->action->label(),
                'tone' => $this->action->tone(),
            ],
            'model' => $this->model_type === null ? null : $model.' #'.$this->model_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at' => Format::dateTime($this->created_at),
        ];
    }
}
