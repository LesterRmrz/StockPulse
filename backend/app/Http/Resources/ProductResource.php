<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 *
 * when...() helpers leave a key out unless the controller loaded it, so the
 * list page and the detail page can share this resource without extra queries.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'default_supplier_id' => $this->default_supplier_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'supplier' => $this->whenLoaded('defaultSupplier', fn () => $this->defaultSupplier ? [
                'id' => $this->defaultSupplier->id,
                'name' => $this->defaultSupplier->name,
            ] : null),
            // Money stays a string like "39.00" so no float rounding sneaks in.
            'cost_price' => $this->cost_price,
            'sell_price' => $this->sell_price,
            'is_active' => $this->is_active,
            // Present when the query used withSum / withCount
            'total_quantity' => $this->whenHas('total_quantity', fn ($value) => (int) $value),
            'attention_count' => $this->whenHas('attention_count', fn ($value) => (int) $value),
            'stock' => StockLevelResource::collection($this->whenLoaded('stockLevels')),
            // Set only for deleted products (the "Deleted" view of the list)
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
