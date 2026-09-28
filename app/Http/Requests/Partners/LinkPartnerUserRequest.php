<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkPartnerUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Partner && ($this->user()?->can('update', $partner) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $partner = $this->route('partner');
        $partnerId = $partner instanceof Partner ? $partner->id : null;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('partners', 'user_id')->ignore($partnerId),
            ],
        ];
    }
}
