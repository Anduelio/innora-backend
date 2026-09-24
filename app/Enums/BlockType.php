<?php

namespace App\Enums;

enum BlockType: string
{
    case Maintenance = 'maintenance';
    case OutOfOrder = 'out_of_order';
    case OwnerUse = 'owner_use';
    case InternalHold = 'internal_hold';
    case Other = 'other';
}
