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
}
