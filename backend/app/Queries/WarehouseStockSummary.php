<?php

namespace App\Queries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Totals per warehouse for the Warehouses page, in one query.
 *
 * A "query class" (docs/PLAN.md, architecture rule 6): a report that joins
 * several tables lives in its own class instead of a controller.
 */
class WarehouseStockSummary
{
    /**
     * One row per warehouse, keyed by warehouse id. Columns: units,
     * skus_in_stock, needs_attention, stock_value (MySQL returns them as strings).
     *
     * @return Collection<int, object>
     */
    public function get(): Collection
    {
        return DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->whereNull('p.deleted_at')
            ->groupBy('sl.warehouse_id')
            ->selectRaw('sl.warehouse_id')
            ->selectRaw('SUM(sl.quantity) AS units')
            // In MySQL a comparison is 1 or 0, so SUM() counts the matching rows.
            ->selectRaw('SUM(sl.quantity > 0) AS skus_in_stock')
            ->selectRaw('SUM(sl.quantity <= sl.reorder_point) AS needs_attention')
            ->selectRaw('SUM(sl.quantity * p.cost_price) AS stock_value')
            ->get()
            ->keyBy('warehouse_id');
    }
}
