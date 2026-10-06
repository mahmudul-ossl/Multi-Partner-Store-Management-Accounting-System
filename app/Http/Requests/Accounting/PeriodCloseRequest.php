<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\PermissionName;
use App\Services\Accounting\PeriodGuard;
use App\Support\Format;
use Closure;
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
            'closed_through' => ['required', 'date', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    return;
                }

                $closed = app(PeriodGuard::class)->closedThrough();
                $day = substr($value, 0, 10);

                if ($closed !== null && $day <= $closed) {
                    $fail('The books are already closed through '.Format::date($closed).'.');
                }
            }],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
