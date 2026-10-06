<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\PaymentMethod;
use App\Models\Purchase;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = $this->isMethod('POST')
            ? Gate::inspect('create', Purchase::class)
            : Gate::inspect('update', $this->route('purchase'));

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('paid_amount') === '' || $this->input('paid_amount') === null) {
            $this->merge(['paid_amount' => '0.00']);
        }

        foreach (['payment_method', 'financial_account_id', 'note', 'warehouse_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'paid_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'items.*.unit_cost' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];
    }
}
