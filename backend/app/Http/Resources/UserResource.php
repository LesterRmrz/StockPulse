<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The JSON shape of a user sent to the frontend. Resources decide exactly
 * which fields leave the server (never the password hash or remember token).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_demo' => $this->is_demo,
            // What this user may do, answered by the policies. The frontend uses
            // these to hide buttons; the API still checks every request itself.
            'can' => [
                'manage_catalog' => $this->resource->can('create', Product::class),
                'manage_warehouses' => $this->resource->can('create', Warehouse::class),
                'adjust_stock' => $this->resource->can('adjust', StockLevel::class),
                'transfer_stock' => $this->resource->can('create', StockTransfer::class),
                'edit_reorder_settings' => $this->resource->can('update', StockLevel::class),
            ],
        ];
    }
}
