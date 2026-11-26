<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StockTransferController extends Controller
{
    /** GET /api/stock-transfers?warehouse_id= (matches either side) */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['warehouse_id' => ['nullable', 'integer']]);

        $transfers = StockTransfer::query()
            ->with($this->details())
            ->when($filters['warehouse_id'] ?? null, fn ($q, $id) => $q->where(fn (Builder $q) => $q
                ->where('from_warehouse_id', $id)
                ->orWhere('to_warehouse_id', $id)))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return StockTransferResource::collection($transfers);
    }

    public function show(StockTransfer $stockTransfer): StockTransferResource
    {
        return new StockTransferResource($stockTransfer->load($this->details()));
    }

    public function store(StockTransferRequest $request, InventoryService $inventory): StockTransferResource
    {
        Gate::authorize('create', StockTransfer::class);

        $data = $request->validated();

        $transfer = $inventory->transfer(
            $data['from_warehouse_id'],
            $data['to_warehouse_id'],
            $data['items'],
            $request->user(),
            $data['note'] ?? null,
        );

        return $this->show($transfer);
    }

    /** Relations every transfer response needs (eager loaded to avoid N+1 queries). */
    private function details(): array
    {
        return [
            'fromWarehouse:id,code',
            'toWarehouse:id,code',
            'user:id,name',
            // withTrashed: a product deleted later still shows in old transfers
            'items.product' => fn ($q) => $q->withTrashed()->select('id', 'sku', 'name'),
        ];
    }
}
