<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMethod;
use App\Models\PartnerWithdrawal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = $this->isMethod('POST')
            ? Gate::inspect('create', PartnerWithdrawal::class)
            : Gate::inspect('update', $this->route('withdrawal'));

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
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'transaction_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
