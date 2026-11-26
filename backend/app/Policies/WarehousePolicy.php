<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Warehouses are part of how the business is set up, so only admins change
 * them. There's no delete: a warehouse with history is deactivated instead.
 */
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
