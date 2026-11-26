<?php

namespace App\Exceptions;

use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown by InventoryService when a change would take stock below zero.
 *
 * It's a business-rule error, not an HTTP one, so the service doesn't know
 * about responses. Laravel calls render() when this reaches the HTTP layer
 * and turns it into a 422, the same shape as a validation error, so the
 * frontend shows it like any other form error.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly StockLevel $level, public readonly int $change)
    {
        $level->loadMissing(['product', 'warehouse']);

        parent::__construct(sprintf(
            'Not enough stock: %s has %d in %s, so you can\'t remove %d.',
            $level->product->sku,
            $level->quantity,
            $level->warehouse->code,
            abs($change),
        ));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['quantity' => [$this->getMessage()]],
        ], 422);
    }
}
