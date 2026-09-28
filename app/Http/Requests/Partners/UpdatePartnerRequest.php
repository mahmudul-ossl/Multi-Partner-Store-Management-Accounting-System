<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Models\Partner;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends PartnerRequest
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
        return [
            ...parent::rules(),
            'partner_code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/',
                Rule::unique('partners', 'partner_code')->ignore($this->route('partner')),
            ],
        ];
    }
}
