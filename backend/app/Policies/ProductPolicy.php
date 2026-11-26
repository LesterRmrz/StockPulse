<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;

/**
 * Who may do what with products. Laravel finds this class automatically by
 * name (App\Models\Product -> App\Policies\ProductPolicy), and controllers ask
 * it through Gate::authorize('update', $product).
 *
 * Everyone signed in can view; admins and managers can change the catalog.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }

    /**
     * Seeing deleted products and bringing them back. Whoever can delete can undo it.
     * $product is optional so the list can ask "may I see deleted ones at all?"
     * with Gate::authorize('restore', Product::class).
     */
    public function restore(User $user, ?Product $product = null): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }
}
