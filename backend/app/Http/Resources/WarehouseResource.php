<?php

namespace App\Http\Resources;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Warehouse */
class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->address,
            'is_active' => $this->is_active,
            // Set by WarehouseController::index from the WarehouseStockSummary query
            'summary' => $this->whenHas('summary', fn ($summary) => [
                'units' => (int) ($summary->units ?? 0),
                'skus_in_stock' => (int) ($summary->skus_in_stock ?? 0),
                'needs_attention' => (int) ($summary->needs_attention ?? 0),
                'stock_value' => number_format((float) ($summary->stock_value ?? 0), 2, '.', ''),
            ]),
        ];
    }
}
