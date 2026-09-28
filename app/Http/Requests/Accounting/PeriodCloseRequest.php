<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class PeriodCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::AccountingManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
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
            'closed_through' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
