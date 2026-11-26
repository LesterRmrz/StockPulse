<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Queries\WarehouseStockSummary;
use App\Services\InventoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** No destroy(): warehouses with history are deactivated (is_active = false) instead. */
class WarehouseController extends Controller
{
    /** GET /api/warehouses: all warehouses with stock totals. */
    public function index(WarehouseStockSummary $summary): AnonymousResourceCollection
    {
        $totals = $summary->get();

        $warehouses = Warehouse::query()
            ->orderBy('code')
            ->get()
            ->each(fn (Warehouse $warehouse) => $warehouse->setAttribute('summary', $totals->get($warehouse->id)));

        return WarehouseResource::collection($warehouses);
    }

    public function store(WarehouseRequest $request, InventoryService $inventory): WarehouseResource
    {
        Gate::authorize('create', Warehouse::class);

        $warehouse = DB::transaction(function () use ($request, $inventory) {
            $warehouse = Warehouse::create($request->validated());
            $inventory->createLevelsForWarehouse($warehouse);

            return $warehouse;
        });

        return new WarehouseResource($warehouse);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        Gate::authorize('update', $warehouse);

        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse);
    }
}
