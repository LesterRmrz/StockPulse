<?php

namespace App\Enums;

/**
 * Who a user is in the system. Stored as a string in users.role.
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
