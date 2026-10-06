<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\PaymentMethod;
use App\Models\SupplierPayment;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', SupplierPayment::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('purchase_id') === '') {
            $this->merge(['purchase_id' => null]);
        }

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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_date' => ['required', 'date', new OpenAccountingPeriod],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
