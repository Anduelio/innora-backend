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

    /**
     * Stored values plus the desk labels returned by the API.
     *
     * @return list<string>
     */
    public static function filterValues(): array
    {
        return [
            ...array_map(fn (self $case) => $case->value, self::cases()),
            'RESERVED',
            'IN_HOUSE',
            'COMPLETED',
            'CANCELLED',
        ];
    }

    public static function canonicalFilter(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $direct = self::tryFrom($trimmed) ?? self::tryFrom(strtolower($trimmed));
        if ($direct !== null) {
            return $direct->value;
        }

        $upper = strtoupper($trimmed);

        return $upper === 'CANCELED' ? 'CANCELLED' : $upper;
    }

    /**
     * Desk label `CANCELLED` covers both cancelled and no-show, because the
     * resource hides that split. A stored value such as `no_show` stays exact.
     *
     * @return list<self>
     */
    public static function fromFilter(string $value): array
    {
        $canonical = self::canonicalFilter($value);

        return match ($canonical) {
            'RESERVED' => [self::Pending, self::Confirmed],
            'IN_HOUSE' => [self::CheckedIn],
            'COMPLETED' => [self::CheckedOut],
            'CANCELLED' => [self::Cancelled, self::NoShow],
            default => ($direct = self::tryFrom($canonical)) !== null ? [$direct] : [],
        };
    }
}
