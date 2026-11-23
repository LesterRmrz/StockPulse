<?php

namespace App\Enums;

/**
 * Health of one product in one warehouse. Not stored: it is derived from
 * quantity and reorder_point every time, so it can never go stale.
 *
 *   out       quantity is 0
 *   critical  quantity is at or below a third of the reorder point
 *   low       quantity is at or below the reorder point
 *   ok        everything else
 */
enum StockStatus: string
{
    case Ok = 'ok';
    case Low = 'low';
    case Critical = 'critical';
    case Out = 'out';

    public static function for(int $quantity, int $reorderPoint): self
    {
        return match (true) {
            $quantity <= 0 => self::Out,
            $quantity * 3 <= $reorderPoint => self::Critical,
            $quantity <= $reorderPoint => self::Low,
            default => self::Ok,
        };
    }
}
