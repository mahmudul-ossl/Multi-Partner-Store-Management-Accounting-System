<?php

declare(strict_types=1);

namespace App\Http\Requests\Spending;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', Expense::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['partner_id', 'payment_method', 'financial_account_id'] as $field) {
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
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
        ];
    }
}
