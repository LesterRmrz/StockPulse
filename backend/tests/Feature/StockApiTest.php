<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The HTTP side of adjustments, transfers and reorder settings. */
class StockApiTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private StockLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(); // the default role is staff
        // Start empty, then add the opening stock through the API, so the
        // ledger has a movement for it and assertLedgerMatches() holds.
        $this->level = StockLevel::factory()->create(['quantity' => 0, 'reorder_point' => 10]);
        $this->adjust(['mode' => 'change', 'quantity' => 20, 'reason' => 'Opening stock'])->assertOk();
    }

    private function adjust(array $data)
    {
        return $this->actingAs($this->staff)->postJson('/api/stock-adjustments', [
            'product_id' => $this->level->product_id,
            'warehouse_id' => $this->level->warehouse_id,
            ...$data,
        ]);
    }

    public function test_staff_can_add_or_remove_stock_with_a_reason(): void
    {
        $this->adjust(['mode' => 'change', 'quantity' => -4, 'reason' => 'Water damage'])
            ->assertOk()
            ->assertJsonPath('data.quantity', 16)
            ->assertJsonPath('data.status', 'ok');

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(-4, $movement->quantity_change);
        $this->assertSame('Water damage', $movement->note);
        $this->assertTrue($movement->user->is($this->staff));
        $this->assertLedgerMatches();
    }

    public function test_a_count_sets_the_quantity_to_what_was_counted(): void
    {
        $this->adjust(['mode' => 'count', 'quantity' => 7, 'reason' => 'Cycle count'])
            ->assertOk()
            ->assertJsonPath('data.quantity', 7)
            ->assertJsonPath('data.status', 'low');

        $this->assertSame(-13, StockMovement::latest('id')->value('quantity_change'));
        $this->assertLedgerMatches();
    }

    public function test_removing_more_than_is_in_stock_is_rejected(): void
    {
        $this->adjust(['mode' => 'change', 'quantity' => -25, 'reason' => 'Oops'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity')
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'has 20 in'));

        $this->assertSame(20, $this->level->fresh()->quantity);
    }

    public function test_an_adjustment_needs_an_amount_a_reason_and_an_active_warehouse(): void
    {
        $this->adjust(['mode' => 'change', 'quantity' => 0, 'reason' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'reason']);

        $this->level->warehouse->update(['is_active' => false]);

        $this->adjust(['mode' => 'change', 'quantity' => 1, 'reason' => 'Found one'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('warehouse_id');
    }

    public function test_staff_can_transfer_stock_between_warehouses(): void
    {
        $to = Warehouse::factory()->create();

        $response = $this->actingAs($this->staff)->postJson('/api/stock-transfers', [
            'from_warehouse_id' => $this->level->warehouse_id,
            'to_warehouse_id' => $to->id,
            'items' => [['product_id' => $this->level->product_id, 'quantity' => 8]],
            'note' => 'Weekend promo in Cebu',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_units', 8)
            ->assertJsonPath('data.user', $this->staff->name);

        $this->assertSame(12, $this->level->fresh()->quantity);
        $this->assertSame(8, StockLevel::where('warehouse_id', $to->id)->value('quantity'));
        $this->assertLedgerMatches();

        $this->actingAs($this->staff)->getJson('/api/stock-transfers')
            ->assertJsonPath('data.0.transfer_number', $response->json('data.transfer_number'));
    }

    public function test_a_transfer_is_validated(): void
    {
        $this->actingAs($this->staff)->postJson('/api/stock-transfers', [
            'from_warehouse_id' => $this->level->warehouse_id,
            'to_warehouse_id' => $this->level->warehouse_id,
            'items' => [
                ['product_id' => $this->level->product_id, 'quantity' => 1],
                ['product_id' => $this->level->product_id, 'quantity' => 0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['to_warehouse_id', 'items.0.product_id', 'items.1.quantity']);
    }

    public function test_a_transfer_larger_than_the_stock_is_rejected(): void
    {
        $this->actingAs($this->staff)->postJson('/api/stock-transfers', [
            'from_warehouse_id' => $this->level->warehouse_id,
            'to_warehouse_id' => Warehouse::factory()->create()->id,
            'items' => [['product_id' => $this->level->product_id, 'quantity' => 21]],
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame(20, $this->level->fresh()->quantity);
    }

    public function test_only_managers_and_admins_change_reorder_settings(): void
    {
        $settings = ['reorder_point' => 25, 'reorder_qty' => 60];

        $this->actingAs($this->staff)->patchJson("/api/stock-levels/{$this->level->id}", $settings)->assertForbidden();

        $this->actingAs(User::factory()->manager()->create())
            ->patchJson("/api/stock-levels/{$this->level->id}", [...$settings, 'quantity' => 999])
            ->assertOk()
            ->assertJsonPath('data.reorder_point', 25)
            ->assertJsonPath('data.status', 'low')
            ->assertJsonPath('data.quantity', 20); // quantity is ignored: it only changes through the ledger
    }

    public function test_stock_levels_can_be_filtered_by_status(): void
    {
        StockLevel::factory()->create(['quantity' => 0, 'reorder_point' => 5]);   // out
        StockLevel::factory()->create(['quantity' => 2, 'reorder_point' => 9]);   // critical
        StockLevel::factory()->create(['quantity' => 50, 'reorder_point' => 5]);  // ok

        $get = fn (string $query) => $this->actingAs($this->staff)->getJson("/api/stock-levels?{$query}")->assertOk();

        $get('status=out')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'out');
        $get('status=critical')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'critical');
        $get('status=attention')->assertJsonCount(2, 'data');
        // the default sort puts the most urgent row first
        $get('')->assertJsonCount(4, 'data')->assertJsonPath('data.0.status', 'out');
    }

    public function test_deleted_products_disappear_from_the_inventory_list(): void
    {
        $this->adjust(['mode' => 'count', 'quantity' => 0, 'reason' => 'Clearance'])->assertOk();
        Product::find($this->level->product_id)->delete();

        $this->actingAs($this->staff)->getJson('/api/stock-levels')->assertJsonCount(0, 'data');
    }

    public function test_inactive_products_stay_in_the_inventory_list_marked_as_inactive(): void
    {
        Product::find($this->level->product_id)->update(['is_active' => false]);

        // Still listed (it may still have stock to sell off or move), but flagged
        $this->actingAs($this->staff)->getJson('/api/stock-levels')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.is_active', false);
    }
}
