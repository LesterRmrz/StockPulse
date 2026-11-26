<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Make the next requests look like they come from the React app, so
     * Sanctum treats them as "stateful" (session cookie auth) like in the browser.
     */
    protected function fromSpa(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ]);
    }

    /**
     * The core inventory rule (docs/DATABASE.md): every stock level equals the
     * sum of its ledger movements.
     */
    protected function assertLedgerMatches(): void
    {
        $mismatches = DB::table('stock_levels as sl')
            ->leftJoinSub(
                DB::table('stock_movements')
                    ->selectRaw('product_id, warehouse_id, SUM(quantity_change) as total')
                    ->groupBy('product_id', 'warehouse_id'),
                'm',
                fn ($join) => $join->on('m.product_id', '=', 'sl.product_id')->on('m.warehouse_id', '=', 'sl.warehouse_id'),
            )
            ->whereRaw('COALESCE(m.total, 0) <> sl.quantity')
            ->count();

        $this->assertSame(0, $mismatches, 'stock_levels no longer match the stock_movements ledger');
    }
}
