<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\StockAdjustmentKind;
use App\Models\StockAdjustment;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', StockAdjustment::class);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('unit_cost') === '') {
            $this->merge(['unit_cost' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(StockAdjustmentKind::class)],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'direction' => ['nullable', Rule::in(['increase', 'decrease'])],
            'unit_cost' => ['nullable', 'regex:/^\d+(\.\d{1,4})?$/'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
