<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\ChartOfAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChartAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = $this->isMethod('POST')
            ? Gate::inspect('create', ChartOfAccount::class)
            : Gate::inspect('update', $this->route('chart_of_account'));

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }

        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->route('chart_of_account');
        $ignore = $account instanceof ChartOfAccount ? $account->id : null;

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/', Rule::unique('chart_of_accounts', 'code')->ignore($ignore)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'normal_balance' => ['required', Rule::enum(NormalBalance::class)],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
