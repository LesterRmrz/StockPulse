<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\StockLevel;
use App\Models\User;

class StockLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Recording a recount or damage is everyday warehouse work: every role. */
    public function adjust(User $user): bool
    {
        return true;
    }

    /**
     * Reorder point and reorder quantity are planning decisions.
     * $level is optional so the frontend can ask "may I edit these at all?"
     * with can('update', StockLevel::class).
     */
    public function update(User $user, ?StockLevel $level = null): bool
    {
        return $user->hasRole(Role::Admin, Role::Manager);
    }
}
