<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quantity_change' => $this->quantity_change,
            'quantity_after' => $this->quantity_after,
            'unit_cost' => $this->unit_cost,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
            ]),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            // What caused it: an order, a purchase order or a transfer (null for adjustments)
            'reference' => $this->whenLoaded('reference', fn () => $this->reference ? [
                'type' => $this->reference_type,
                'id' => $this->reference_id,
                'label' => match (true) {
                    $this->reference instanceof Order => $this->reference->order_number,
                    $this->reference instanceof PurchaseOrder => $this->reference->po_number,
                    $this->reference instanceof StockTransfer => $this->reference->transfer_number,
                    default => null,
                },
            ] : null),
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
