<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Models\SaleReturn;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', SaleReturn::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
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
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
        ];
    }
}
