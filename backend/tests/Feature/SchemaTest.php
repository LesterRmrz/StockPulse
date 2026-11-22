<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Checks that the relationships and database rules from docs/DATABASE.md
 * actually hold, so a later migration can't quietly break them.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_nest_through_parent_id(): void
    {
        $electronics = Category::factory()->create(['name' => 'Electronics']);
        $accessories = Category::factory()->create(['name' => 'Accessories', 'parent_id' => $electronics->id]);

        $this->assertTrue($accessories->parent->is($electronics));
        $this->assertTrue($electronics->children->first()->is($accessories));
    }

    public function test_deleting_a_parent_category_keeps_its_children(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $parent->delete();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);
        $product->category->delete();
    }

    public function test_a_product_has_one_stock_level_per_warehouse(): void
    {
        $level = StockLevel::factory()->create();

        $this->expectException(QueryException::class);
        StockLevel::factory()->create([
            'product_id' => $level->product_id,
            'warehouse_id' => $level->warehouse_id,
        ]);
    }

    public function test_stock_quantity_cannot_go_below_zero(): void
    {
        $level = StockLevel::factory()->create(['quantity' => 5]);

        // Bypasses the model on purpose: the database itself must refuse it.
        $this->expectException(QueryException::class);
        DB::table('stock_levels')->where('id', $level->id)->update(['quantity' => -1]);
    }

    public function test_product_skus_are_unique(): void
    {
        Product::factory()->create(['sku' => 'SKU-1']);

        $this->expectException(QueryException::class);
        Product::factory()->create(['sku' => 'SKU-1']);
    }

    public function test_products_are_soft_deleted(): void
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    public function test_a_movement_links_to_its_order_through_the_morph_map(): void
    {
        $warehouse = Warehouse::factory()->create();
        $order = Order::create([
            'order_number' => 'SO-TEST-1',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'channel' => SalesChannel::Web,
            'status' => OrderStatus::Paid,
            'placed_at' => now(),
        ]);

        $movement = $order->stockMovements()->create([
            'product_id' => Product::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'type' => MovementType::Sale,
            'quantity_change' => -2,
            'quantity_after' => 8,
        ]);

        // Stored as the short alias, not the PHP class name
        $this->assertSame('order', $movement->getRawOriginal('reference_type'));
        $this->assertTrue(StockMovement::find($movement->id)->reference->is($order));
    }
}
