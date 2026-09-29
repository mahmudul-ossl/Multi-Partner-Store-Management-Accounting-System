<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Models\CustomerPayment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', CustomerPayment::class);

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

        if (is_int($this->input('amount'))) {
            $this->merge(['amount' => (string) $this->input('amount')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
