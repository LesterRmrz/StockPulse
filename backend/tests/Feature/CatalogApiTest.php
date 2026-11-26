<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Categories, suppliers and warehouses. */
class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
    }

    public function test_categories_get_a_unique_slug(): void
    {
        Category::factory()->create(['name' => 'Garden', 'slug' => 'garden']);

        $this->actingAs($this->manager)->postJson('/api/categories', ['name' => 'Garden'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'garden-2')
            ->assertJsonPath('data.products_count', 0);
    }

    public function test_categories_nest_only_one_level_deep(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        // can't go under a subcategory
        $this->actingAs($this->manager)->postJson('/api/categories', ['name' => 'Deep', 'parent_id' => $child->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        // a category with subcategories must stay top-level
        $other = Category::factory()->create();
        $this->actingAs($this->manager)->putJson("/api/categories/{$parent->id}", ['name' => $parent->name, 'parent_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        // and can't be its own parent
        $this->actingAs($this->manager)->putJson("/api/categories/{$other->id}", ['name' => $other->name, 'parent_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager)->deleteJson("/api/categories/{$product->category_id}")
            ->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_deleting_a_parent_makes_its_subcategories_top_level(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->manager)->deleteJson("/api/categories/{$parent->id}")->assertNoContent();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_suppliers_are_searchable_and_editable(): void
    {
        Supplier::factory()->create(['name' => 'Visayas Paper Mills']);
        $supplier = Supplier::factory()->create(['name' => 'Northwind Components']);

        $this->actingAs($this->manager)->getJson('/api/suppliers?search=northwind')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $supplier->id);

        $this->actingAs($this->manager)->putJson("/api/suppliers/{$supplier->id}", ['name' => 'Northwind', 'lead_time_days' => 5])
            ->assertOk()->assertJsonPath('data.lead_time_days', 5);
    }

    public function test_a_supplier_with_purchase_orders_cannot_be_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        PurchaseOrder::create([
            'po_number' => 'PO-9001',
            'supplier_id' => $supplier->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'status' => 'ordered',
        ]);

        $this->actingAs($this->manager)->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('supplier');

        $unused = Supplier::factory()->create();
        $this->actingAs($this->manager)->deleteJson("/api/suppliers/{$unused->id}")->assertNoContent();
    }

    public function test_only_admins_manage_warehouses(): void
    {
        $data = ['code' => 'ilo-01', 'name' => 'Iloilo Depot', 'city' => 'Iloilo'];

        $this->actingAs($this->manager)->postJson('/api/warehouses', $data)->assertForbidden();

        Product::factory()->count(2)->create();

        $response = $this->actingAs(User::factory()->admin()->create())->postJson('/api/warehouses', $data)
            ->assertCreated()
            ->assertJsonPath('data.code', 'ILO-01');

        // every existing product gets an empty stock row in the new warehouse
        $this->assertSame(2, StockLevel::where('warehouse_id', $response->json('data.id'))->count());
    }

    public function test_the_warehouse_list_includes_stock_totals(): void
    {
        $level = StockLevel::factory()->create(['quantity' => 4, 'reorder_point' => 10]);
        $level->product->update(['cost_price' => '2.50']);

        $this->actingAs($this->manager)->getJson('/api/warehouses')
            ->assertOk()
            ->assertJsonPath('data.0.summary.units', 4)
            ->assertJsonPath('data.0.summary.needs_attention', 1)
            ->assertJsonPath('data.0.summary.stock_value', '10.00');
    }
}
