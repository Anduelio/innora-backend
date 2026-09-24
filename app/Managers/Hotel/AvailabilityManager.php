<?php

namespace App\Managers\Hotel;

use App\Enums\ReservationStatus;
use App\Models\Property;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;

class AvailabilityManager
{
    public function roomIsFree(Room $room, string $checkIn, string $checkOut, ?int $ignoreReservationId = null): bool
    {
        if (! $room->sellsInventory()) {
            return false;
        }

        if ($this->hasStay($room, $checkIn, $checkOut, $ignoreReservationId)) {
            return false;
        }

        return ! $this->hasBlock($room, $checkIn, $checkOut);
    }

    public function canCheckIn(Room $room, string $checkIn, string $checkOut): bool
    {
        return $room->is_active
            && $room->operational_status->acceptsCheckIn()
            && ! $this->hasBlock($room, $checkIn, $checkOut);
    }

    public function firstFreeRoom(Property $property, RoomType $type, string $checkIn, string $checkOut): ?Room
    {
        $rooms = Room::query()
            ->where('property_id', $property->id)
            ->where('room_type_id', $type->id)
            ->orderBy('number')
            ->lockForUpdate()
            ->get();

        foreach ($rooms as $room) {
            if ($this->roomIsFree($room, $checkIn, $checkOut)) {
                return $room;
            }
        }

        return null;
    }

    public function availableUnits(RoomType $type, string $night): int
    {
        $next = date('Y-m-d', strtotime($night.' +1 day'));
        $free = Room::query()
            ->where('room_type_id', $type->id)
            ->orderBy('number')
            ->get()
            ->filter(fn (Room $room) => $this->roomIsFree($room, $night, $next))
            ->count();

        $unassigned = ReservationRoom::query()
            ->where('room_type_id', $type->id)
            ->whereNull('room_id')
            ->whereDate('check_in', '<=', $night)
            ->whereDate('check_out', '>', $night)
            ->whereHas('reservation', function ($query) {
                $query->whereNotIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::NoShow->value,
                ]);
            })
            ->count();

        return max(0, $free - $unassigned);
    }

    private function hasStay(Room $room, string $checkIn, string $checkOut, ?int $ignoreReservationId): bool
    {
        return ReservationRoom::query()
            ->where('room_id', $room->id)
            ->whereDate('check_in', '<', $checkOut)
            ->whereDate('check_out', '>', $checkIn)
            ->whereHas('reservation', function ($query) use ($ignoreReservationId) {
                $query->whereNotIn('status', [
                    ReservationStatus::Cancelled->value,
                    ReservationStatus::NoShow->value,
                ]);
                if ($ignoreReservationId !== null) {
                    $query->where('id', '!=', $ignoreReservationId);
                }
            })
            ->exists();
    }

    private function hasBlock(Room $room, string $checkIn, string $checkOut): bool
    {
        return RoomBlock::query()
            ->where('room_id', $room->id)
            ->whereDate('starts_on', '<', $checkOut)
            ->whereDate('ends_on', '>', $checkIn)
            ->exists();
    }
}
