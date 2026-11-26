<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\StockLevel;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Gate;

/**
 * POST /api/stock-adjustments
 *
 * A single-action ("invokable") controller: one route, one job. The controller
 * only translates HTTP into a service call; the stock rules live in the service.
 */
class StockAdjustmentController extends Controller
{
    public function __invoke(StockAdjustmentRequest $request, InventoryService $inventory): StockLevelResource
    {
        Gate::authorize('adjust', StockLevel::class);

        $data = $request->validated();

        if ($data['mode'] === 'count') {
            $inventory->recount($data['product_id'], $data['warehouse_id'], $data['quantity'], $request->user(), $data['reason']);
        } else {
            $inventory->adjust($data['product_id'], $data['warehouse_id'], $data['quantity'], $request->user(), $data['reason']);
        }

        $level = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->where('product_id', $data['product_id'])
            ->where('warehouse_id', $data['warehouse_id'])
            ->firstOrFail();

        return new StockLevelResource($level);
    }
}
