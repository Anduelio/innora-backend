<?php

namespace App\Enums;

enum ChargeCategoryCode: string
{
    case Room = 'room';
    case FoodBeverage = 'food_beverage';
    case Minibar = 'minibar';
    case Parking = 'parking';
    case Transport = 'transport';
    case Laundry = 'laundry';
    case LateCheckout = 'late_checkout';
    case EarlyCheckin = 'early_checkin';
    case ExtraBed = 'extra_bed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Akomodim',
            self::FoodBeverage => 'Ushqim dhe pije',
            self::Minibar => 'Minibar',
            self::Parking => 'Parking',
            self::Transport => 'Transport',
            self::Laundry => 'Lavanderi',
            self::LateCheckout => 'Dalje e vonuar',
            self::EarlyCheckin => 'Hyrje e hershme',
            self::ExtraBed => 'Krevat shtesë',
            self::Other => 'Të tjera',
        };
    }

    public function isRoom(): bool
    {
        return $this === self::Room;
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Room => 10,
            self::FoodBeverage => 20,
            self::Minibar => 30,
            self::Parking => 40,
            self::Transport => 50,
            self::Laundry => 60,
            self::LateCheckout => 70,
            self::EarlyCheckin => 80,
            self::ExtraBed => 90,
            self::Other => 100,
        };
    }
}
