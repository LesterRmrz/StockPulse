<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sku', 'name', 'description', 'category_id', 'default_supplier_id',
    'cost_price', 'sell_price', 'image_path', 'is_active',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            // 'decimal:2' keeps money as an exact string like "39.00", never a float.
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Product::search('cable') matches the name or the SKU. Blank = no filter. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%"));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** Warehouses holding this product, with quantity and reorder settings on ->pivot. */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'stock_levels')
            ->withPivot(['quantity', 'reorder_point', 'reorder_qty'])
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
