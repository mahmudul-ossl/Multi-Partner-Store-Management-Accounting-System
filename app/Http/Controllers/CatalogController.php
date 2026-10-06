<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Inventory\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Product::class);

        return Inertia::render('Inventory/Catalog', [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'description']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'can' => ['manage' => request()->user()?->can('create', Product::class) ?? false],
        ]);
    }

    public function storeCategory(Request $request, CatalogService $catalog): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:categories,name'], 'description' => ['nullable', 'string', 'max:2000']]);
        $catalog->createCategory($request->user(), $data);

        return back()->with('success', 'Category added.');
    }

    public function storeBrand(Request $request, CatalogService $catalog): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:brands,name']]);
        $catalog->createBrand($request->user(), $data);

        return back()->with('success', 'Brand added.');
    }

    public function storeUnit(Request $request, CatalogService $catalog): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20', 'unique:units,abbreviation'],
        ]);
        $catalog->createUnit($request->user(), $data);

        return back()->with('success', 'Unit added.');
    }

    public function storeWarehouse(Request $request, CatalogService $catalog): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:warehouses,name'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $catalog->createWarehouse($request->user(), $data);

        return back()->with('success', 'Warehouse added.');
    }
}
