<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\TransferStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only code allowed to change stock quantities (docs/PLAN.md, rule 1).
 *
 * Every change follows the same three steps inside one database transaction:
 *   1. Lock the stock_levels row (SELECT ... FOR UPDATE), so no other request
 *      can change it until we commit.
 *   2. Check the new quantity isn't negative.
 *   3. Update the level AND append a stock_movements row.
 *
 * Because both writes share a transaction, the ledger rule
 * "stock_levels.quantity = SUM(stock_movements.quantity_change)" always holds:
 * either both rows are written, or neither is.
 */
class InventoryService
{
    /**
     * Generic entry point: add (+) or remove (-) stock for any reason.
     * M3 uses this for sales, purchase order receipts and returns.
     */
    public function record(
        int $productId,
        int $warehouseId,
        int $change,
        MovementType $type,
        ?User $user = null,
        ?string $note = null,
        ?Model $reference = null,
        ?string $unitCost = null,
    ): StockMovement {
        return DB::transaction(function () use ($productId, $warehouseId, $change, $type, $user, $note, $reference, $unitCost) {
            $level = $this->lock($productId, $warehouseId);

            return $this->apply($level, $change, $type, $user, $note, $reference, $unitCost);
        });
    }

    /** Manual correction by a signed amount: "+5 found", "-2 damaged". */
    public function adjust(int $productId, int $warehouseId, int $change, User $user, string $reason): StockMovement
    {
        return $this->record($productId, $warehouseId, $change, MovementType::Adjustment, $user, $reason);
    }

    /**
     * Set the level to a physically counted number. The difference is
     * calculated *after* locking the row, so a sale that happens while the
     * form is open can't make the correction wrong.
     *
     * Returns null when the count already matches (nothing to record).
     */
    public function recount(int $productId, int $warehouseId, int $counted, User $user, string $reason): ?StockMovement
    {
        return DB::transaction(function () use ($productId, $warehouseId, $counted, $user, $reason) {
            $level = $this->lock($productId, $warehouseId);
            $change = $counted - $level->quantity;

            return $change === 0
                ? null
                : $this->apply($level, $change, MovementType::Adjustment, $user, $reason);
        });
    }

    /**
     * Move stock between warehouses. Writes one transfer_out and one
     * transfer_in movement per product. If any product is short, the whole
     * transfer is rolled back: nothing moves.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function transfer(int $fromId, int $toId, array $items, User $user, ?string $note = null): StockTransfer
    {
        return DB::transaction(function () use ($fromId, $toId, $items, $user, $note) {
            $transfer = StockTransfer::create([
                // Real number needs the id, so set a unique placeholder first.
                'transfer_number' => 'TMP-'.Str::random(16),
                'from_warehouse_id' => $fromId,
                'to_warehouse_id' => $toId,
                'status' => TransferStatus::Completed,
                'user_id' => $user->id,
                'note' => $note,
                'completed_at' => now(),
            ]);
            $transfer->update(['transfer_number' => 'TR-'.(1000 + $transfer->id)]);

            // product_id => total quantity (merges duplicate lines)
            $quantities = collect($items)
                ->groupBy('product_id')
                ->map(fn ($lines) => (int) $lines->sum('quantity'))
                ->sortKeys();

            // Lock every row first, always in the same order (product id, then
            // warehouse id). Two transfers running at the same time then wait
            // for each other instead of deadlocking.
            $warehouseIds = collect([$fromId, $toId])->sort()->values();
            $levels = [];
            foreach ($quantities->keys() as $productId) {
                foreach ($warehouseIds as $warehouseId) {
                    $levels[$productId][$warehouseId] = $this->lock($productId, $warehouseId);
                }
            }

            foreach ($quantities as $productId => $quantity) {
                $transfer->items()->create(['product_id' => $productId, 'quantity' => $quantity]);

                $this->apply($levels[$productId][$fromId], -$quantity, MovementType::TransferOut, $user, $note, $transfer);
                $this->apply($levels[$productId][$toId], $quantity, MovementType::TransferIn, $user, $note, $transfer);
            }

            return $transfer;
        });
    }

    /**
     * Give a new product an (empty) stock row in every warehouse. Quantity
     * starts at 0, so no ledger row is needed: the sum of zero movements is 0.
     */
    public function createLevelsForProduct(Product $product, int $reorderPoint = 0, int $reorderQty = 0): void
    {
        $rows = Warehouse::query()->pluck('id')->map(fn ($warehouseId) => [
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'reorder_point' => $reorderPoint,
            'reorder_qty' => $reorderQty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        StockLevel::query()->insertOrIgnore($rows->all());
    }

    /** Give a new warehouse an (empty) stock row for every product. */
    public function createLevelsForWarehouse(Warehouse $warehouse): void
    {
        $rows = Product::query()->pluck('id')->map(fn ($productId) => [
            'product_id' => $productId,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'reorder_point' => 0,
            'reorder_qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        StockLevel::query()->insertOrIgnore($rows->all());
    }

    /**
     * SELECT ... FOR UPDATE on one stock row. Other transactions that want the
     * same row wait here until we commit or roll back. Must run inside a
     * transaction, or the lock is released immediately.
     */
    private function lock(int $productId, int $warehouseId): StockLevel
    {
        $find = fn () => StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $level = $find();

        if ($level === null) {
            // Never stocked here before: create the empty row, then lock it.
            // (Only on this path, because INSERT IGNORE on an existing row takes
            // a shared lock that could deadlock with another request's FOR UPDATE.)
            StockLevel::query()->insertOrIgnore([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $level = $find();
        }

        return $level;
    }

    /** Steps 2 and 3. The caller must already hold the lock on $level. */
    private function apply(
        StockLevel $level,
        int $change,
        MovementType $type,
        ?User $user,
        ?string $note,
        ?Model $reference = null,
        ?string $unitCost = null,
    ): StockMovement {
        $newQuantity = $level->quantity + $change;

        if ($newQuantity < 0) {
            throw new InsufficientStockException($level, $change);
        }

        $level->update(['quantity' => $newQuantity]);

        $movement = new StockMovement([
            'product_id' => $level->product_id,
            'warehouse_id' => $level->warehouse_id,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_after' => $newQuantity,
            'unit_cost' => $unitCost,
            'user_id' => $user?->id,
            'note' => $note,
        ]);
        $movement->reference()->associate($reference);
        $movement->save();

        return $movement;
    }
}
