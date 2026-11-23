<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A small version of the demo data so the test runs in seconds.
        config([
            // Long enough for purchase orders to arrive (supplier lead times are 3-12 days).
            'stockpulse.seed.history_days' => 20,
            'stockpulse.seed.orders_per_day_start' => 30,
            'stockpulse.seed.orders_per_day_end' => 40,
            'stockpulse.seed.customers' => 20,
        ]);

        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_creates_the_reference_data(): void
    {
        $this->assertSame(3, User::count());
        $this->assertSame(2, User::where('is_demo', true)->count());
        $this->assertSame(3, Warehouse::count());
        $this->assertSame(150, Product::count());
        // One stock level per product per warehouse
        $this->assertSame(450, StockLevel::count());
        $this->assertGreaterThan(0, Order::count());
    }

    /**
     * The core inventory rule: each stock level must equal the sum of its
     * ledger movements.
     */
    public function test_stock_levels_match_the_ledger(): void
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

        $this->assertSame(0, $mismatches);
    }

    public function test_order_totals_add_up(): void
    {
        $order = Order::with('items')->first();

        $this->assertEquals(
            round($order->items->sum(fn ($item) => $item->unit_price * $item->quantity) + $order->shipping_fee, 2),
            (float) $order->total,
        );
    }

    public function test_received_purchase_orders_wrote_receipts(): void
    {
        $received = PurchaseOrder::where('status', 'received')->withCount('stockMovements')->get();

        $this->assertNotEmpty($received, 'Expected some purchase orders to have arrived.');

        foreach ($received as $po) {
            $this->assertGreaterThan(0, $po->stock_movements_count, "{$po->po_number} has no receipt movements");
        }
    }
}
