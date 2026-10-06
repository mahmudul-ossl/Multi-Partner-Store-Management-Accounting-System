<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $response = $this->isMethod('POST')
            ? Gate::inspect('create', Product::class)
            : Gate::inspect('update', $this->route('product'));

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['brand_id', 'supplier_id', 'description'] as $field) {
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
        $product = $this->route('product');
        $ignore = $product instanceof Product ? $product->id : null;

        return [
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($ignore)],
            'barcode' => ['required', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($ignore)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'purchase_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'selling_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'wholesale_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'minimum_stock' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'reorder_level' => ['required', 'regex:/^\d+(\.\d{1,3})?$/'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
