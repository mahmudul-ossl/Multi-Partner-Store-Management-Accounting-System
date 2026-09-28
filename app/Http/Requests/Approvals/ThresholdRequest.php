<?php

declare(strict_types=1);

namespace App\Http\Requests\Approvals;

use App\Enums\ApprovalRequestType;
use App\Models\ApprovalThreshold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThresholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', ApprovalThreshold::class) ?? false;
        }

        $threshold = $this->route('threshold');

        return $threshold instanceof ApprovalThreshold && ($this->user()?->can('update', $threshold) ?? false);
    }

    protected function prepareForValidation(): void
    {
        foreach (['min_amount', 'max_amount'] as $field) {
            if (is_int($this->input($field))) {
                $this->merge([$field => (string) $this->input($field)]);
            }
        }

        if ($this->input('max_amount') === '') {
            $this->merge(['max_amount' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_type' => ['required', Rule::enum(ApprovalRequestType::class)],
            'min_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'max_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'required_approvals' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
