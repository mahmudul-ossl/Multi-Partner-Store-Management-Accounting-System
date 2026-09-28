<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Http\Requests\Inventory\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Inventory\CatalogService;
use App\Services\Inventory\InventoryService;
use App\Support\Costing;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);
        $search = (string) $request->string('search');

        $products = Product::query()
            ->with('category', 'brand', 'unit')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $term = '%'.$search.'%';
                $inner->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('barcode', 'like', $term);
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Products', [
            'products' => [
                'data' => $products->getCollection()->map(fn (Product $product): array => $this->summary($product))->all(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
            ],
            'filters' => ['search' => $search],
            'options' => $this->options(),
            'statuses' => ProductStatus::options(),
        ]);
    }

    public function store(ProductRequest $request, CatalogService $catalog): RedirectResponse
    {
        $product = $catalog->createProduct($request->user(), $request->safe()->except('image'), $request->file('image'));

        return redirect()->route('inventory.products.show', $product)->with('success', 'Product saved.');
    }

    public function show(Product $product, InventoryService $inventory): Response
    {
        $this->authorize('view', $product);
        $product->load('category', 'brand', 'supplier', 'unit');

        return Inertia::render('Inventory/ProductShow', [
            'product' => [
                ...$this->summary($product),
                'description' => $product->description,
                'supplier' => $product->supplier?->name,
                'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
                'on_hand' => $inventory->onHand($product),
                'average_cost' => Costing::cost((string) $product->average_cost),
                'low' => $inventory->isLow($product),
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'supplier_id' => $product->supplier_id,
                'unit_id' => $product->unit_id,
                'status_value' => $product->status->value,
                'minimum_stock' => Costing::quantity((string) $product->minimum_stock),
                'reorder_level' => Costing::quantity((string) $product->reorder_level),
                'purchase_price' => (string) $product->purchase_price,
                'selling_price' => (string) $product->selling_price,
                'wholesale_price' => (string) $product->wholesale_price,
            ],
            'options' => $this->options(),
            'statuses' => ProductStatus::options(),
            'can' => ['update' => request()->user()?->can('update', $product) ?? false],
        ]);
    }

    public function update(ProductRequest $request, Product $product, CatalogService $catalog): RedirectResponse
    {
        $catalog->updateProduct($product, $request->user(), $request->safe()->except('image'), $request->file('image'));

        return redirect()->route('inventory.products.show', $product)->with('success', 'Product updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Product $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'category' => $product->category?->name,
            'brand' => $product->brand?->name,
            'unit' => $product->unit?->abbreviation,
            'purchase_price_formatted' => Money::of((string) $product->purchase_price)->formatted(),
            'selling_price_formatted' => Money::of((string) $product->selling_price)->formatted(),
            'status' => $product->status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
