<?php

declare(strict_types=1);

namespace App\Http\Requests\Spending;

use App\Enums\FundingSource;
use App\Enums\PaymentMethod;
use App\Models\PromotionPartnerExpense;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PromotionContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', PromotionPartnerExpense::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['payment_method', 'financial_account_id', 'note'] as $field) {
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
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'funded_by' => ['required', Rule::enum(FundingSource::class)],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_date' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
