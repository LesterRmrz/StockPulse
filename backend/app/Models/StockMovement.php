<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One immutable row of the inventory ledger.
 */
#[Fillable([
    'product_id', 'warehouse_id', 'type', 'quantity_change', 'quantity_after',
    'unit_cost', 'reference_type', 'reference_id', 'user_id', 'note', 'created_at',
])]
class StockMovement extends Model
{
    // The table has created_at but no updated_at.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The ledger is append-only. A mistake is fixed with a new "adjustment"
     * row, never by editing or deleting an old one.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Stock movements are immutable.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The order, purchase order or transfer that caused this movement (null for adjustments). */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
