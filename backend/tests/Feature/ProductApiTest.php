<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        Warehouse::factory()->count(3)->create();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'sku' => 'sp-new-0001',
            'name' => 'Desk Lamp',
            'category_id' => Category::factory()->create()->id,
            'cost_price' => '12.50',
            'sell_price' => '24.00',
            'reorder_point' => 10,
            'reorder_qty' => 30,
            ...$overrides,
        ];
    }

    public function test_the_list_is_paginated_and_searchable(): void
    {
        Product::factory()->count(3)->create();
        Product::factory()->create(['name' => 'Bamboo Cutting Board', 'sku' => 'SP-HOM-0099']);

        $this->actingAs($this->manager)->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 5);

        // by name or by SKU
        $this->actingAs($this->manager)->getJson('/api/products?search=bamboo')->assertJsonCount(1, 'data');
        $this->actingAs($this->manager)->getJson('/api/products?search=HOM-0099')->assertJsonCount(1, 'data');
    }

    public function test_the_attention_filter_returns_products_at_or_below_their_reorder_point(): void
    {
        $healthy = StockLevel::factory()->create(['quantity' => 100, 'reorder_point' => 10]);
        $low = StockLevel::factory()->create(['quantity' => 5, 'reorder_point' => 10]);

        $this->actingAs($this->manager)->getJson('/api/products?stock=attention')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $low->product_id)
            ->assertJsonPath('data.0.attention_count', 1);

        $this->assertNotSame($healthy->product_id, $low->product_id);
    }

    public function test_a_manager_creates_a_product_with_a_stock_row_in_every_warehouse(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'SP-NEW-0001') // normalised to upper case
            ->assertJsonPath('data.total_quantity', 0)
            ->assertJsonCount(3, 'data.stock');

        $levels = StockLevel::where('product_id', $response->json('data.id'))->get();
        $this->assertCount(3, $levels);
        $this->assertTrue($levels->every(fn ($l) => $l->quantity === 0 && $l->reorder_point === 10 && $l->reorder_qty === 30));
    }

    public function test_skus_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'SP-NEW-0001']);

        $this->actingAs($this->manager)->postJson('/api/products', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_staff_can_view_but_not_change_the_catalog(): void
    {
        $staff = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($staff)->getJson('/api/products')->assertOk();
        $this->actingAs($staff)->getJson("/api/products/{$product->id}")->assertOk();
        $this->actingAs($staff)->postJson('/api/products', $this->payload())->assertForbidden();
        $this->actingAs($staff)->putJson("/api/products/{$product->id}", $this->payload())->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/products/{$product->id}")->assertForbidden();
    }

    public function test_a_product_can_be_edited(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager)
            ->putJson("/api/products/{$product->id}", $this->payload(['name' => 'Desk Lamp Pro', 'sell_price' => '29.90']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Desk Lamp Pro')
            ->assertJsonPath('data.sell_price', '29.90');
    }

    public function test_a_product_with_stock_cannot_be_deleted(): void
    {
        $level = StockLevel::factory()->create(['quantity' => 3]);

        $this->actingAs($this->manager)->deleteJson("/api/products/{$level->product_id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product');

        $this->assertNotSoftDeleted('products', ['id' => $level->product_id]);
    }

    public function test_a_product_without_stock_is_soft_deleted_and_hidden(): void
    {
        $level = StockLevel::factory()->create(['quantity' => 0]);

        $this->actingAs($this->manager)->deleteJson("/api/products/{$level->product_id}")->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $level->product_id]);
        $this->actingAs($this->manager)->getJson('/api/products')->assertJsonCount(0, 'data');
        $this->actingAs($this->manager)->getJson("/api/products/{$level->product_id}")->assertNotFound();
    }

    public function test_deleted_products_are_listed_separately_and_can_be_restored(): void
    {
        $level = StockLevel::factory()->create(['quantity' => 0]);
        Product::factory()->create(); // a normal product, so the two lists differ
        $this->actingAs($this->manager)->deleteJson("/api/products/{$level->product_id}")->assertNoContent();

        // ?deleted=1 lists only the deleted one, with its deleted_at
        $this->actingAs($this->manager)->getJson('/api/products?deleted=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $level->product_id)
            ->assertJsonPath('data.0.deleted_at', fn ($value) => $value !== null);

        $this->actingAs($this->manager)->postJson("/api/products/{$level->product_id}/restore")
            ->assertOk()
            ->assertJsonPath('data.deleted_at', null);

        // Back everywhere: the catalog, its own page, and the inventory list
        $this->assertNotSoftDeleted('products', ['id' => $level->product_id]);
        $this->actingAs($this->manager)->getJson('/api/products')->assertJsonCount(2, 'data');
        $this->actingAs($this->manager)->getJson("/api/products/{$level->product_id}")->assertOk();
        $this->actingAs($this->manager)->getJson('/api/stock-levels')->assertJsonCount(1, 'data');
        $this->actingAs($this->manager)->getJson('/api/products?deleted=1')->assertJsonCount(0, 'data');
    }

    public function test_staff_can_neither_see_nor_restore_deleted_products(): void
    {
        $product = Product::factory()->create();
        $product->delete();
        $staff = User::factory()->create();

        $this->actingAs($staff)->getJson('/api/products?deleted=1')->assertForbidden();
        $this->actingAs($staff)->postJson("/api/products/{$product->id}/restore")->assertForbidden();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_the_movement_history_is_newest_first_and_filterable(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::first();
        $inventory = app(InventoryService::class);
        $inventory->adjust($product->id, $warehouse->id, 10, $this->manager, 'Opening stock');
        $inventory->adjust($product->id, $warehouse->id, -2, $this->manager, 'Damaged');

        $this->actingAs($this->manager)->getJson("/api/products/{$product->id}/movements")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.quantity_change', -2)
            ->assertJsonPath('data.0.note', 'Damaged')
            ->assertJsonPath('data.0.user', $this->manager->name)
            ->assertJsonPath('data.0.warehouse.code', $warehouse->code);

        $this->actingAs($this->manager)->getJson("/api/products/{$product->id}/movements?type=sale")
            ->assertJsonCount(0, 'data');
    }

    public function test_the_current_user_says_what_they_may_do(): void
    {
        $this->actingAs($this->manager)->fromSpa()->getJson('/api/user')
            ->assertJsonPath('data.can.manage_catalog', true)
            ->assertJsonPath('data.can.manage_warehouses', false)
            ->assertJsonPath('data.can.adjust_stock', true);
    }
}
