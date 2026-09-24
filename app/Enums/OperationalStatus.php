<?php

namespace App\Enums;

enum OperationalStatus: string
{
    case Ready = 'ready';
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case Inspected = 'inspected';
    case Maintenance = 'maintenance';
    case OutOfOrder = 'out_of_order';

    public function sellsInventory(): bool
    {
        return $this !== self::Maintenance && $this !== self::OutOfOrder;
    }

    public function acceptsCheckIn(): bool
    {
        return $this === self::Ready || $this === self::Inspected;
    }
}
