<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current quantity of one product in one warehouse.
 *
 * Never change quantity directly in app code: from M2 on, every change goes
 * through InventoryService, so a matching stock_movements row is always written.
 */
#[Fillable(['product_id', 'warehouse_id', 'quantity', 'reorder_point', 'reorder_qty'])]
class StockLevel extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reorder_point' => 'integer',
            'reorder_qty' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function status(): StockStatus
    {
        return StockStatus::for($this->quantity, $this->reorder_point);
    }

    /**
     * The same rules as StockStatus::for(), written as SQL so the database can
     * filter: StockLevel::withStatus(StockStatus::Low)->get()
     */
    public function scopeWithStatus(Builder $query, StockStatus $status): void
    {
        match ($status) {
            StockStatus::Out => $query->where('quantity', 0),
            StockStatus::Critical => $query->where('quantity', '>', 0)->whereRaw('quantity * 3 <= reorder_point'),
            StockStatus::Low => $query->whereRaw('quantity * 3 > reorder_point')->whereColumn('quantity', '<=', 'reorder_point'),
            StockStatus::Ok => $query->whereColumn('quantity', '>', 'reorder_point'),
        };
    }

    /** Out, critical or low: at or below the reorder point. */
    public function scopeNeedsAttention(Builder $query): void
    {
        $query->whereColumn('quantity', '<=', 'reorder_point');
    }
}
