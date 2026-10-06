<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\AccountTransfer;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AccountTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', AccountTransfer::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_int($this->input('amount'))) {
            $this->merge(['amount' => (string) $this->input('amount')]);
        }

        if ($this->input('note') === '') {
            $this->merge(['note' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id', 'different:to_financial_account_id'],
            'to_financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
