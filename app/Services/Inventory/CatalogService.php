<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\ProductStatus;
use App\Enums\SupplierStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Support\Costing;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class CatalogService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{name: string, description?: string|null}  $attributes
     */
    public function createCategory(User $actor, array $attributes): Category
    {
        $category = Category::query()->create([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'is_active' => true,
        ]);
        $this->audit->record(AuditAction::Created, $category, null, ['name' => $category->name], $actor);

        return $category;
    }

    /**
     * @param  array{name: string}  $attributes
     */
    public function createBrand(User $actor, array $attributes): Brand
    {
        $brand = Brand::query()->create(['name' => $attributes['name'], 'is_active' => true]);
        $this->audit->record(AuditAction::Created, $brand, null, ['name' => $brand->name], $actor);

        return $brand;
    }

    /**
     * @param  array{name: string, abbreviation: string}  $attributes
     */
    public function createUnit(User $actor, array $attributes): Unit
    {
        $unit = Unit::query()->create([
            'name' => $attributes['name'],
            'abbreviation' => $attributes['abbreviation'],
            'is_active' => true,
        ]);
        $this->audit->record(AuditAction::Created, $unit, null, ['name' => $unit->name], $actor);

        return $unit;
    }

    /**
     * @param  array{name: string, address?: string|null, is_default?: bool}  $attributes
     */
    public function createWarehouse(User $actor, array $attributes): Warehouse
    {
        return DB::transaction(function () use ($actor, $attributes): Warehouse {
            $isDefault = (bool) ($attributes['is_default'] ?? false);

            if ($isDefault) {
                Warehouse::query()->update(['is_default' => false]);
            }

            $warehouse = Warehouse::query()->create([
                'name' => $attributes['name'],
                'address' => $attributes['address'] ?? null,
                'is_default' => $isDefault || ! Warehouse::query()->exists(),
                'is_active' => true,
            ]);
            $this->audit->record(AuditAction::Created, $warehouse, null, ['name' => $warehouse->name], $actor);

            return $warehouse;
        });
    }

    /**
     * @param  array{name: string, company_name?: string|null, phone?: string|null, email?: string|null, address?: string|null, tax_id?: string|null, notes?: string|null}  $attributes
     */
    public function createSupplier(User $actor, array $attributes): Supplier
    {
        $supplier = Supplier::query()->create([
            'name' => $attributes['name'],
            'company_name' => $attributes['company_name'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'email' => $attributes['email'] ?? null,
            'address' => $attributes['address'] ?? null,
            'tax_id' => $attributes['tax_id'] ?? null,
            'status' => SupplierStatus::Active,
            'notes' => $attributes['notes'] ?? null,
        ]);
        $this->audit->record(AuditAction::Created, $supplier, null, ['name' => $supplier->name], $actor);

        return $supplier;
    }

    /**
     * @param  array{name: string, phone?: string|null, email?: string|null, role?: string|null}  $attributes
     */
    public function addContact(Supplier $supplier, User $actor, array $attributes): SupplierContact
    {
        $contact = $supplier->contacts()->create([
            'name' => $attributes['name'],
            'phone' => $attributes['phone'] ?? null,
            'email' => $attributes['email'] ?? null,
            'role' => $attributes['role'] ?? null,
        ]);
        $this->audit->record(AuditAction::Created, $contact, null, ['name' => $contact->name, 'supplier_id' => $supplier->id], $actor);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createProduct(User $actor, array $attributes, ?UploadedFile $image = null): Product
    {
        $product = Product::query()->create($this->productAttributes($attributes, $image));
        $this->audit->record(AuditAction::Created, $product, null, ['sku' => $product->sku, 'name' => $product->name], $actor);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProduct(Product $product, User $actor, array $attributes, ?UploadedFile $image = null): Product
    {
        $before = ['name' => $product->name, 'sku' => $product->sku];
        $product->fill($this->productAttributes($attributes, $image, $product));
        $product->save();
        $this->audit->record(AuditAction::Updated, $product, $before, ['name' => $product->name, 'sku' => $product->sku], $actor);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function productAttributes(array $attributes, ?UploadedFile $image, ?Product $existing = null): array
    {
        $payload = [
            'sku' => $attributes['sku'],
            'barcode' => $attributes['barcode'],
            'name' => $attributes['name'],
            'category_id' => $attributes['category_id'],
            'brand_id' => $attributes['brand_id'] ?? null,
            'supplier_id' => $attributes['supplier_id'] ?? null,
            'unit_id' => $attributes['unit_id'],
            'purchase_price' => Money::of($attributes['purchase_price'])->amount(),
            'selling_price' => Money::of($attributes['selling_price'])->amount(),
            'wholesale_price' => Money::of($attributes['wholesale_price'])->amount(),
            'minimum_stock' => Costing::quantity($attributes['minimum_stock'] ?? '0'),
            'reorder_level' => Costing::quantity($attributes['reorder_level'] ?? '0'),
            'status' => ProductStatus::from($attributes['status'] ?? ProductStatus::Active->value),
            'description' => $attributes['description'] ?? null,
        ];

        if ($existing === null) {
            $payload['average_cost'] = '0.0000';
        }

        if ($image instanceof UploadedFile) {
            if ($existing?->image_path) {
                Storage::disk('public')->delete($existing->image_path);
            }

            $payload['image_path'] = $image->store('products', 'public');
        }

        return $payload;
    }
}
