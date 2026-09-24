<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function blocksInventory(): bool
    {
        return $this !== self::Cancelled && $this !== self::NoShow;
    }

    public function uiValue(): string
    {
        return match ($this) {
            self::Pending, self::Confirmed => 'RESERVED',
            self::CheckedIn => 'IN_HOUSE',
            self::CheckedOut => 'COMPLETED',
            self::Cancelled, self::NoShow => 'CANCELLED',
        };
    }
}
