<?php

namespace App\Policies;

use App\Models\User;

/** Moving stock between warehouses is everyday work: every role. */
class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
