<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private function movement(): StockMovement
    {
        return StockMovement::create([
            'product_id' => Product::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'type' => MovementType::Receipt,
            'quantity_change' => 10,
            'quantity_after' => 10,
        ]);
    }

    public function test_movements_cannot_be_edited(): void
    {
        $movement = $this->movement();

        $this->expectException(LogicException::class);
        $movement->update(['quantity_change' => 999]);
    }

    public function test_movements_cannot_be_deleted(): void
    {
        $movement = $this->movement();

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_type_is_cast_to_an_enum(): void
    {
        $this->assertSame(MovementType::Receipt, $this->movement()->fresh()->type);
    }
}
