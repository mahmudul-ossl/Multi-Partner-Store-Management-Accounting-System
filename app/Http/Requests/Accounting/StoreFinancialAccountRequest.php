<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', FinancialAccount::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_int($this->input('opening_balance'))) {
            $this->merge(['opening_balance' => (string) $this->input('opening_balance')]);
        }

        if ($this->input('opening_balance') === '' || $this->input('opening_balance') === null) {
            $this->merge(['opening_balance' => '0.00']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:financial_accounts,name'],
            'type' => ['required', Rule::enum(FinancialAccountType::class)],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'opening_balance' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'opening_date' => ['nullable', 'date', 'required_unless:opening_balance,0,0.0,0.00', new OpenAccountingPeriod],
        ];
    }
}
