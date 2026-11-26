<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /** Sortable columns the client may ask for (never pass user input straight to orderBy). */
    private const SORTS = ['name', 'sku', 'sell_price', 'total_quantity'];

    /**
     * GET /api/products?search=&category_id=&supplier_id=&stock=attention|out&sort=name&direction=asc&page=1&deleted=1
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'stock' => ['nullable', Rule::in(['attention', 'out'])],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'deleted' => ['nullable', 'boolean'],
        ]);
        $deleted = (bool) ($filters['deleted'] ?? false);

        // Only those who could restore a product may see the deleted ones
        if ($deleted) {
            Gate::authorize('restore', Product::class);
        }

        $products = Product::query()
            // onlyTrashed: deleted products *instead of* the normal ones
            ->when($deleted, fn ($q) => $q->onlyTrashed())
            ->with(['category:id,name', 'defaultSupplier:id,name'])
            // Adds total_quantity and attention_count columns using subqueries
            ->withSum('stockLevels as total_quantity', 'quantity')
            ->withCount(['stockLevels as attention_count' => fn ($q) => $q->needsAttention()])
            ->search($filters['search'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->where('default_supplier_id', $id))
            ->when(($filters['stock'] ?? null) === 'attention', fn ($q) => $q->whereHas('stockLevels', fn ($l) => $l->needsAttention()))
            ->when(($filters['stock'] ?? null) === 'out', fn ($q) => $q->whereDoesntHave('stockLevels', fn ($l) => $l->where('quantity', '>', 0)))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->orderBy('id') // tie-breaker so pages never overlap
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /** GET /api/products/{product}: everything the product page needs. */
    public function show(Product $product): ProductResource
    {
        $product->load([
            'category:id,name',
            'defaultSupplier:id,name',
            'stockLevels' => fn ($q) => $q->with('warehouse')->orderBy('warehouse_id'),
        ]);
        $product->loadSum('stockLevels as total_quantity', 'quantity');

        return new ProductResource($product);
    }

    public function store(ProductRequest $request, InventoryService $inventory): ProductResource
    {
        Gate::authorize('create', Product::class);

        $data = $request->validated();

        $product = DB::transaction(function () use ($data, $inventory) {
            $product = Product::create(collect($data)->except(['reorder_point', 'reorder_qty'])->all());
            $inventory->createLevelsForProduct($product, $data['reorder_point'] ?? 0, $data['reorder_qty'] ?? 0);

            return $product;
        });

        return $this->show($product);
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        $product->update($request->validated());

        return $this->show($product);
    }

    /**
     * Soft delete (history keeps pointing at it). Only allowed once the stock is
     * gone, otherwise those units would silently vanish from every report.
     */
    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        $onHand = (int) $product->stockLevels()->sum('quantity');

        if ($onHand > 0) {
            throw ValidationException::withMessages([
                'product' => "{$product->sku} still has {$onHand} units in stock. Adjust or transfer them to zero first.",
            ]);
        }

        $product->delete();

        return response()->noContent();
    }

    /**
     * POST /api/products/{product}/restore: undo a delete. Nothing else needs
     * fixing: soft delete kept the row, its stock rows (all 0) and its history,
     * and its SKU stayed reserved, so it can't clash with a newer product.
     */
    public function restore(Product $product): ProductResource
    {
        Gate::authorize('restore', $product);

        $product->restore();

        return $this->show($product);
    }
}
