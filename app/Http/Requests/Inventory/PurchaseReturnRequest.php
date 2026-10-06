<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Models\PurchaseReturn;
use App\Rules\OpenAccountingPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = Gate::inspect('create', PurchaseReturn::class);

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
            'purchase_id' => ['required', 'integer', 'exists:purchases,id'],
            'transaction_date' => ['required', 'date', new OpenAccountingPeriod],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
        ];
    }
}
