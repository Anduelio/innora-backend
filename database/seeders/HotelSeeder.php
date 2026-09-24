<?php

namespace Database\Seeders;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SyncLog;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::query()->create([
            'name' => 'Vila Dea',
            'city' => 'Sarandë',
            'timezone' => 'Europe/Tirane',
            'currency' => 'EUR',
        ]);

        (new ChargeCategorySeeder)->seedFor($property);

        $types = [];
        foreach ([
            'Dyshe' => ['double', 3],
            'Teke' => ['single', 1],
            'Suitë' => ['suite', 4],
            'Familjare' => ['family', 4],
        ] as $uiType => [$code, $capacity]) {
            $types[$uiType] = RoomType::query()->create([
                'property_id' => $property->id,
                'code' => $code,
                'ui_type' => $uiType,
                'capacity' => $capacity,
            ]);
        }

        $rooms = [];
        foreach ([
            ['101', 'Dyshe', 2],
            ['102', 'Dyshe', 2],
            ['103', 'Dyshe', 2],
            ['104', 'Dyshe', 2],
            ['105', 'Teke', 1],
            ['106', 'Dyshe', 3],
            ['201', 'Suitë', 4],
            ['202', 'Suitë', 4],
            ['203', 'Familjare', 4],
            ['204', 'Dyshe', 2],
            ['301', 'Suitë', 4],
            ['302', 'Dyshe', 2],
        ] as [$number, $uiType, $capacity]) {
            $rooms[$number] = Room::query()->create([
                'property_id' => $property->id,
                'room_type_id' => $types[$uiType]->id,
                'number' => $number,
                'capacity' => $capacity,
                'status' => RoomStatus::Active,
            ]);
        }

        foreach ($this->stays() as $stay) {
            $guest = Guest::query()->create([
                'property_id' => $property->id,
                'name' => $stay['guest'],
                'phone' => $stay['phone'],
            ]);
            $reservation = Reservation::query()->create([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'source' => $stay['source'],
                'status' => $stay['status'],
                'external_id' => $stay['external_id'],
                'check_in' => $stay['check_in'],
                'check_out' => $stay['check_out'],
                'adults' => $stay['persons'],
                'children' => 0,
                'total_cents' => $stay['total'],
                'paid_cents' => $stay['paid'],
                'currency' => 'EUR',
                'notes' => $stay['notes'],
                'expected_arrival' => $stay['arrival'],
                'created_at' => '2026-09-01 09:00:00',
                'updated_at' => '2026-09-01 09:00:00',
            ]);
            $room = $rooms[$stay['room']];
            ReservationRoom::query()->create([
                'reservation_id' => $reservation->id,
                'room_type_id' => $room->room_type_id,
                'room_id' => $room->id,
                'check_in' => $stay['check_in'],
                'check_out' => $stay['check_out'],
            ]);
        }

        $log = SyncLog::query()->create([
            'property_id' => $property->id,
            'direction' => 'outbound',
            'status' => 'succeeded',
            'message' => null,
        ]);
        $log->created_at = '2026-09-21 08:42:00';
        $log->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stays(): array
    {
        $row = function (
            string $room,
            string $guest,
            string $checkIn,
            string $checkOut,
            int $persons,
            ReservationSource $source,
            ReservationStatus $status,
            int $total,
            int $paid,
            string $phone,
            ?string $arrival = null,
            ?string $notes = null,
            ?string $externalId = null,
        ): array {
            return [
                'room' => $room,
                'guest' => $guest,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'persons' => $persons,
                'source' => $source,
                'status' => $status,
                'total' => $total * 100,
                'paid' => $paid * 100,
                'phone' => $phone,
                'arrival' => $arrival ?? '14:00',
                'notes' => $notes,
                'external_id' => $externalId,
            ];
        };

        return [
            $row('101', 'Ana Kola', '2026-09-19', '2026-09-23', 2, ReservationSource::Direct, ReservationStatus::CheckedIn, 320, 320, '+355 69 234 5566'),
            $row('102', 'Endrit Lika', '2026-09-20', '2026-09-22', 1, ReservationSource::Phone, ReservationStatus::CheckedIn, 90, 0, '+355 68 411 2200'),
            $row('103', 'Arben Hoxha', '2026-09-21', '2026-09-24', 2, ReservationSource::Direct, ReservationStatus::Confirmed, 240, 100, '+355 69 123 4567', '14:00', 'Kërkon dhomë me pamje nga deti.'),
            $row('104', 'John Smith', '2026-09-21', '2026-09-25', 2, ReservationSource::BookingCom, ReservationStatus::Confirmed, 380, 380, '+44 7700 900123', '18:30', null, 'BDC-4471902'),
            $row('105', 'Arta Dervishi', '2026-09-18', '2026-09-21', 1, ReservationSource::Direct, ReservationStatus::CheckedIn, 150, 150, '+355 67 990 1122'),
            $row('203', 'Familja Krasniqi', '2026-09-19', '2026-09-21', 4, ReservationSource::WhatsApp, ReservationStatus::CheckedIn, 260, 100, '+383 44 556 677'),
            $row('201', 'Mario Rossi', '2026-09-21', '2026-09-24', 2, ReservationSource::Direct, ReservationStatus::Confirmed, 450, 0, '+39 335 778 9911', '16:00'),
            $row('202', 'Sofia Müller', '2026-09-20', '2026-09-26', 2, ReservationSource::BookingCom, ReservationStatus::CheckedIn, 720, 720, '+49 151 2233 445', null, null, 'BDC-4468110'),
            $row('106', 'Besnik Gashi', '2026-09-20', '2026-09-23', 3, ReservationSource::WalkIn, ReservationStatus::CheckedIn, 270, 90, '+355 69 887 6543'),
            $row('301', 'Luan Berisha', '2026-09-19', '2026-09-22', 2, ReservationSource::Direct, ReservationStatus::CheckedIn, 400, 400, '+355 69 300 1200'),
            $row('204', 'Blerta Hoxhaj', '2026-09-23', '2026-09-27', 2, ReservationSource::BookingCom, ReservationStatus::Confirmed, 340, 340, '+355 68 220 3344'),
            $row('302', 'Petrit Shala', '2026-09-22', '2026-09-25', 2, ReservationSource::Direct, ReservationStatus::Confirmed, 210, 50, '+355 69 555 7788'),
            $row('105', 'Klara Meyer', '2026-09-24', '2026-09-28', 2, ReservationSource::BookingCom, ReservationStatus::Confirmed, 360, 360, '+49 170 6677 889'),
            $row('203', 'Ilir Dema', '2026-09-26', '2026-09-29', 4, ReservationSource::Phone, ReservationStatus::Confirmed, 330, 0, '+355 67 121 3131'),
            $row('101', 'Anila Prifti', '2026-09-24', '2026-09-26', 2, ReservationSource::Direct, ReservationStatus::Confirmed, 180, 0, '+355 69 444 9090'),
            $row('102', 'Marco Bianchi', '2026-09-23', '2026-09-27', 2, ReservationSource::BookingCom, ReservationStatus::Confirmed, 300, 300, '+39 340 111 2233'),
            $row('106', 'Genci Mema', '2026-09-25', '2026-09-30', 2, ReservationSource::WalkIn, ReservationStatus::Confirmed, 420, 100, '+355 69 777 1234'),
            $row('301', 'Erion Çela', '2026-09-25', '2026-09-27', 2, ReservationSource::Direct, ReservationStatus::Cancelled, 200, 0, '+355 68 909 0909'),
            $row('204', 'Vera Sula', '2026-09-17', '2026-09-20', 2, ReservationSource::Direct, ReservationStatus::CheckedOut, 210, 210, '+355 69 202 3040'),
        ];
    }
}
