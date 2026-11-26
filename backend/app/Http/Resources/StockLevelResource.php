<?php

namespace App\Http\Resources;

use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockLevel */
class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
                'category' => $this->product->relationLoaded('category') ? $this->product->category->name : null,
                'is_active' => $this->product->is_active,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
                'is_active' => $this->warehouse->is_active,
            ]),
            'quantity' => $this->quantity,
            'reorder_point' => $this->reorder_point,
            'reorder_qty' => $this->reorder_qty,
            // Computed, not stored (App\Enums\StockStatus)
            'status' => $this->status()->value,
            'updated_at' => $this->updated_at,
        ];
    }
}
