<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;

/** Everyone signed in can view; admins and managers can change categories. */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }
}
