<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Enums\PartnerStatus;
use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PartnerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'partner_code' => $this->filled('partner_code') ? $this->input('partner_code') : null,
            'phone' => $this->filled('phone') ? $this->input('phone') : null,
            'email' => $this->filled('email') ? $this->input('email') : null,
            'address' => $this->filled('address') ? $this->input('address') : null,
            'user_id' => $this->filled('user_id') ? $this->input('user_id') : null,
            'notes' => $this->filled('notes') ? $this->input('notes') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $partner = $this->route('partner');
        $partnerId = $partner instanceof Partner ? $partner->id : null;

        return [
            'partner_code' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/',
                Rule::unique('partners', 'partner_code')->ignore($partnerId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('partners', 'email')->ignore($partnerId),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'joining_date' => ['required', 'date'],
            'ownership_percentage' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'investment_percentage' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'status' => ['required', Rule::enum(PartnerStatus::class)],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('partners', 'user_id')->ignore($partnerId),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ownership_percentage.between' => 'Ownership percentage must be between 0 and 100.',
            'investment_percentage.between' => 'Investment percentage must be between 0 and 100.',
        ];
    }
}
