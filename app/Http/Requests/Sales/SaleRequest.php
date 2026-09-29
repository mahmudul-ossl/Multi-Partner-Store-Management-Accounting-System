<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', Sale::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['discount', 'delivery', 'paid_amount'] as $field) {
            if ($this->input($field) === '' || $this->input($field) === null) {
                $this->merge([$field => '0.00']);
            }
        }

        foreach (['payment_method', 'financial_account_id', 'note'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (is_int($this->input('discount'))) {
            $this->merge(['discount' => (string) $this->input('discount')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'discount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'delivery' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paid_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'items.*.unit_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
