<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SalesIncomeSource;
use App\Models\SalesIncome;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SalesIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', SalesIncome::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::enum(SalesIncomeSource::class)],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
