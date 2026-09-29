<?php

namespace App\Enums;

enum ReservationSource: string
{
    case BookingCom = 'booking_com';
    case Direct = 'direct';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case WalkIn = 'walk_in';

    public function uiValue(): string
    {
        return match ($this) {
            self::BookingCom => 'BOOKING',
            self::Direct => 'DIREKT',
            self::Phone => 'TELEFON',
            self::WhatsApp => 'WHATSAPP',
            self::WalkIn => 'RECEPSION',
        };
    }

    public static function fromDesk(string $value): self
    {
        return match ($value) {
            'DIREKT' => self::Direct,
            'TELEFON' => self::Phone,
            'WHATSAPP' => self::WhatsApp,
            'RECEPSION' => self::WalkIn,
            default => self::Direct,
        };
    }

    /**
     * Stored values. Desk labels are normalized to these before validation.
     *
     * @return list<string>
     */
    public static function filterValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public static function canonicalFilter(string $value): string
    {
        return self::fromFilter($value)?->value ?? trim($value);
    }

    public static function fromFilter(string $value): ?self
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $direct = self::tryFrom($trimmed) ?? self::tryFrom(strtolower($trimmed));
        if ($direct !== null) {
            return $direct;
        }

        return match (strtoupper($trimmed)) {
            'BOOKING', 'BOOKING.COM' => self::BookingCom,
            'DIREKT', 'DIRECT' => self::Direct,
            'TELEFON', 'PHONE' => self::Phone,
            'WHATSAPP' => self::WhatsApp,
            'RECEPSION', 'RECEPTION', 'WALK_IN', 'WALK-IN', 'WALKIN' => self::WalkIn,
            default => null,
        };
    }
}
