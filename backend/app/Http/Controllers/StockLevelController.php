<?php

namespace App\Http\Controllers;

use App\Enums\StockStatus;
use App\Http\Requests\ReorderSettingsRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The Inventory page: one row per product per warehouse. */
class StockLevelController extends Controller
{
    /**
     * GET /api/stock-levels?search=&warehouse_id=&category_id=&status=low&sort=urgency&page=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([...array_column(StockStatus::cases(), 'value'), 'attention'])],
            'sort' => ['nullable', Rule::in(['urgency', 'name', 'quantity'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $status = $filters['status'] ?? null;

        $levels = StockLevel::query()
            ->with(['product:id,sku,name,category_id,is_active', 'product.category:id,name', 'warehouse:id,code,name,is_active'])
            // whereHas skips soft-deleted products automatically
            ->whereHas('product', fn ($q) => $q
                ->search($filters['search'] ?? null)
                ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id)))
            ->when($filters['warehouse_id'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($status === 'attention', fn ($q) => $q->needsAttention())
            ->when($status && $status !== 'attention', fn ($q) => $q->withStatus(StockStatus::from($status)))
            ->when(
                ($filters['sort'] ?? 'urgency') === 'urgency',
                // Lowest "quantity per reorder point" first: the most urgent rows on top
                fn ($q) => $q->orderByRaw('quantity / GREATEST(reorder_point, 1)'),
            )
            ->when(($filters['sort'] ?? null) === 'quantity', fn ($q) => $q->orderBy('quantity'))
            ->when(($filters['sort'] ?? null) === 'name', fn ($q) => $q->orderBy(
                Product::select('name')->whereColumn('products.id', 'stock_levels.product_id')
            ))
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return StockLevelResource::collection($levels);
    }

    /** PATCH /api/stock-levels/{stockLevel}: reorder settings only. Quantity goes through InventoryService. */
    public function update(ReorderSettingsRequest $request, StockLevel $stockLevel): StockLevelResource
    {
        Gate::authorize('update', $stockLevel);

        $stockLevel->update($request->validated());

        return new StockLevelResource($stockLevel->load(['product', 'warehouse']));
    }
}
