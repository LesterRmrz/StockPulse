<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'city', 'address', 'is_active'])]
class Warehouse extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** Products stocked here, with quantity and reorder settings on ->pivot. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'stock_levels')
            ->withPivot(['quantity', 'reorder_point', 'reorder_qty'])
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
