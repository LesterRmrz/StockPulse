<?php

namespace Tests\Unit;

use App\Enums\StockStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockStatusTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_status_is_derived_from_quantity_and_reorder_point(int $quantity, int $reorderPoint, StockStatus $expected): void
    {
        $this->assertSame($expected, StockStatus::for($quantity, $reorderPoint));
    }

    public static function cases(): array
    {
        return [
            'empty' => [0, 40, StockStatus::Out],
            'a third of the reorder point' => [13, 40, StockStatus::Critical],
            'just above a third' => [14, 40, StockStatus::Low],
            'at the reorder point' => [40, 40, StockStatus::Low],
            'above the reorder point' => [41, 40, StockStatus::Ok],
            'no reorder point set' => [5, 0, StockStatus::Ok],
        ];
    }
}
