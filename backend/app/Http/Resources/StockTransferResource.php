<?php

namespace App\Http\Resources;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'status' => $this->status->value,
            'from' => $this->whenLoaded('fromWarehouse', fn () => ['id' => $this->fromWarehouse->id, 'code' => $this->fromWarehouse->code]),
            'to' => $this->whenLoaded('toWarehouse', fn () => ['id' => $this->toWarehouse->id, 'code' => $this->toWarehouse->code]),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'note' => $this->note,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (StockTransferItem $item) => [
                'product' => [
                    'id' => $item->product->id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                ],
                'quantity' => $item->quantity,
            ])),
            'total_units' => $this->whenLoaded('items', fn () => $this->items->sum('quantity')),
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
