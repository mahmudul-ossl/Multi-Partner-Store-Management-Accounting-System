<?php

declare(strict_types=1);

namespace App\Http\Requests\Spending;

use App\Enums\PromotionPlatform;
use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', Promotion::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('ends_on') === '') {
            $this->merge(['ends_on' => null]);
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(PromotionPlatform::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'budget' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'status' => ['required', Rule::enum(PromotionStatus::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
