<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Models\FinancialAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('update', $this->route('financial_account'));

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
        $account = $this->route('financial_account');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('financial_accounts', 'name')->ignore($account instanceof FinancialAccount ? $account->id : null)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
