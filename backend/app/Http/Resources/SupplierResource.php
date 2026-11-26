<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'lead_time_days' => $this->lead_time_days,
            'products_count' => $this->whenCounted('products'),
            'purchase_orders_count' => $this->whenCounted('purchaseOrders'),
        ];
    }
}
