<?php

namespace App\Enums;

enum SalesChannel: string
{
    case Web = 'web';
    case Marketplace = 'marketplace';
    case Pos = 'pos';
}
