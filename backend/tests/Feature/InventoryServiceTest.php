<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stock correctness: the rules InventoryService must never break.
 */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private User $user;

    private Product $product;

    private Warehouse $manila;

    private Warehouse $cebu;

    protected function setUp(): void
    {
        parent::setUp();

        // app() builds the service the same way a controller would receive it
        $this->inventory = app(InventoryService::class);
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create();
        $this->manila = Warehouse::factory()->create(['code' => 'MNL-01']);
        $this->cebu = Warehouse::factory()->create(['code' => 'CEB-01']);
    }

    private function quantity(Warehouse $warehouse, ?Product $product = null): int
    {
        return (int) StockLevel::query()
            ->where('product_id', ($product ?? $this->product)->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity');
    }

    /** Give the product some starting stock, through the service like real code. */
    private function stock(Warehouse $warehouse, int $quantity, ?Product $product = null): void
    {
        $this->inventory->adjust(($product ?? $this->product)->id, $warehouse->id, $quantity, $this->user, 'Opening stock');
    }

    public function test_an_adjustment_changes_the_level_and_writes_one_ledger_row(): void
    {
        $this->stock($this->manila, 10);

        $movement = $this->inventory->adjust($this->product->id, $this->manila->id, -3, $this->user, 'Damaged in storage');

        $this->assertSame(7, $this->quantity($this->manila));
        $this->assertSame(MovementType::Adjustment, $movement->type);
        $this->assertSame(-3, $movement->quantity_change);
        $this->assertSame(7, $movement->quantity_after);
        $this->assertSame('Damaged in storage', $movement->note);
        $this->assertTrue($movement->user->is($this->user));
        $this->assertSame(2, StockMovement::count());
        $this->assertLedgerMatches();
    }

    public function test_stock_can_never_go_below_zero(): void
    {
        $this->stock($this->manila, 2);

        try {
            $this->inventory->adjust($this->product->id, $this->manila->id, -5, $this->user, 'Too many');
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('has 2 in MNL-01', $e->getMessage());
        }

        // Nothing changed: the level and the ledger are exactly as before
        $this->assertSame(2, $this->quantity($this->manila));
        $this->assertSame(1, StockMovement::count());
        $this->assertLedgerMatches();
    }

    public function test_a_recount_records_only_the_difference(): void
    {
        $this->stock($this->manila, 10);

        $movement = $this->inventory->recount($this->product->id, $this->manila->id, 8, $this->user, 'Cycle count');

        $this->assertSame(-2, $movement->quantity_change);
        $this->assertSame(8, $this->quantity($this->manila));
        $this->assertLedgerMatches();
    }

    public function test_a_recount_that_matches_writes_nothing(): void
    {
        $this->stock($this->manila, 10);

        $this->assertNull($this->inventory->recount($this->product->id, $this->manila->id, 10, $this->user, 'Cycle count'));
        $this->assertSame(1, StockMovement::count());
    }

    public function test_a_level_row_is_created_the_first_time_a_product_is_stocked_somewhere(): void
    {
        $this->assertSame(0, StockLevel::count());

        $this->stock($this->cebu, 4);

        $this->assertSame(4, $this->quantity($this->cebu));
        $this->assertLedgerMatches();
    }

    public function test_a_transfer_moves_stock_and_writes_a_pair_of_movements(): void
    {
        $this->stock($this->manila, 20);

        $transfer = $this->inventory->transfer($this->manila->id, $this->cebu->id, [
            ['product_id' => $this->product->id, 'quantity' => 6],
        ], $this->user, 'Rebalance');

        $this->assertSame('TR-'.(1000 + $transfer->id), $transfer->transfer_number);
        $this->assertSame(14, $this->quantity($this->manila));
        $this->assertSame(6, $this->quantity($this->cebu));

        $movements = $transfer->stockMovements()->orderBy('id')->get();
        $this->assertSame([MovementType::TransferOut, MovementType::TransferIn], $movements->pluck('type')->all());
        $this->assertSame([-6, 6], $movements->pluck('quantity_change')->all());
        $this->assertSame(6, (int) $transfer->items()->sum('quantity')); // SQL SUM() comes back as a string
        $this->assertLedgerMatches();
    }

    public function test_a_transfer_is_all_or_nothing(): void
    {
        $plenty = Product::factory()->create();
        $this->stock($this->manila, 50, $plenty);
        $this->stock($this->manila, 1); // the second line will be short

        try {
            $this->inventory->transfer($this->manila->id, $this->cebu->id, [
                ['product_id' => $plenty->id, 'quantity' => 10],
                ['product_id' => $this->product->id, 'quantity' => 5],
            ], $this->user);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException) {
            // expected
        }

        // The first line was rolled back too
        $this->assertSame(50, $this->quantity($this->manila, $plenty));
        $this->assertSame(0, $this->quantity($this->cebu, $plenty));
        $this->assertSame(0, StockTransfer::count());
        $this->assertSame(2, StockMovement::count());
        $this->assertLedgerMatches();
    }

    public function test_changes_lock_the_stock_row_first(): void
    {
        $this->stock($this->manila, 5);

        DB::enableQueryLog();
        $this->inventory->adjust($this->product->id, $this->manila->id, -1, $this->user, 'Sold at counter');
        $queries = collect(DB::getQueryLog())->pluck('query');

        // SELECT ... FOR UPDATE on stock_levels comes before the UPDATE
        $lock = $queries->search(fn ($sql) => str_contains($sql, 'from `stock_levels`') && str_contains($sql, 'for update'));
        $update = $queries->search(fn ($sql) => str_starts_with($sql, 'update `stock_levels`'));

        $this->assertNotFalse($lock, 'The stock row was not locked');
        $this->assertNotFalse($update);
        $this->assertLessThan($update, $lock);
    }

    public function test_new_products_get_an_empty_level_in_every_warehouse(): void
    {
        $this->inventory->createLevelsForProduct($this->product, reorderPoint: 12, reorderQty: 40);

        $levels = StockLevel::where('product_id', $this->product->id)->get();

        $this->assertCount(2, $levels);
        $this->assertTrue($levels->every(fn ($level) => $level->quantity === 0 && $level->reorder_point === 12 && $level->reorder_qty === 40));
        $this->assertLedgerMatches();
    }
}
