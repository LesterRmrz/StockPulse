<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** GET /api/products/{product}/movements: the ledger for one product, newest first. */
class ProductMovementController extends Controller
{
    public function __invoke(Request $request, Product $product): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(MovementType::class)],
        ]);

        $movements = $product->stockMovements()
            ->with(['warehouse:id,code', 'user:id,name', 'reference'])
            ->when($filters['warehouse_id'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return StockMovementResource::collection($movements);
    }
}
