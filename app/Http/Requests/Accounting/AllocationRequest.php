<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\AllocationMethod;
use App\Models\ProfitAllocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', ProfitAllocation::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('note') === '') {
            $this->merge(['note' => null]);
        }

        if ($this->input('lines') === '' || $this->input('lines') === null) {
            $this->merge(['lines' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => ['required', 'date'],
            'method' => ['required', Rule::enum(AllocationMethod::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required_if:method,custom', 'array'],
            'lines.*.partner_id' => ['required', 'integer', 'distinct', 'exists:partners,id'],
            'lines.*.percentage' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];
    }
}
