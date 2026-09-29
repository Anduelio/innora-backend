<?php

namespace Tests\Feature;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReservationDeskFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_desk_filters_dates_status_source_and_search_without_breaking_the_calendar(): void
    {
        [$user, $rooms] = $this->desk();
        Passport::actingAs($user);

        $this->stay($rooms['101'], 'Ana Kola', 'TELEFON', '2026-09-21', 3, 'krevat shtesë');
        $this->stay($rooms['101'], 'Ben Hoxha', 'DIREKT', '2026-09-24', 1);
        $cila = $this->stay($rooms['102'], 'Cila Meta', 'WHATSAPP', '2026-09-20', 2);
        $dritan = $this->stay($rooms['103'], 'Dritan Lika', 'RECEPSION', '2026-09-21', 1);
        $this->stay($rooms['104'], 'Era Duka', 'DIREKT', '2026-09-19', 2);

        $this->postJson('/api/reservations/'.$cila.'/check-in')->assertOk();
        $this->postJson('/api/reservations/'.$dritan.'/cancel')->assertOk();

        $bookingGuest = Guest::query()->create([
            'property_id' => $user->property_id,
            'name' => 'Booking Guest',
            'phone_prefix' => '+355',
            'phone' => '699990000',
        ]);
        Reservation::query()->create([
            'property_id' => $user->property_id,
            'guest_id' => $bookingGuest->id,
            'source' => ReservationSource::BookingCom,
            'status' => ReservationStatus::Confirmed,
            'external_id' => 'bdc-1',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-03',
            'adults' => 1,
            'total_cents' => 0,
            'paid_cents' => 0,
            'currency' => 'EUR',
        ]);

        $this->assertGuestNames('/api/reservations?arriving_on=2026-09-21&no_pagination=1', [
            'Ana Kola',
            'Dritan Lika',
        ]);
        $this->assertGuestNames('/api/reservations?departing_on=2026-09-22&no_pagination=1', [
            'Cila Meta',
            'Dritan Lika',
        ]);
        $this->assertGuestNames('/api/reservations?in_house_on=2026-09-21&no_pagination=1', [
            'Ana Kola',
            'Cila Meta',
            'Dritan Lika',
        ]);
        $this->assertGuestNames('/api/reservations?in_house_on=2026-09-22&status=IN_HOUSE&no_pagination=1', []);
        $this->assertGuestNames('/api/reservations?in_house_on=2026-09-21&status=IN_HOUSE', [
            'Cila Meta',
        ]);
        $this->assertGuestNames('/api/reservations?arriving_on=2026-09-21&status=RESERVED,IN_HOUSE&no_pagination=1', [
            'Ana Kola',
        ]);
        $this->assertGuestNames('/api/reservations?arriving_on=2026-09-21&status=CANCELLED', [
            'Dritan Lika',
        ]);
        $this->assertGuestNames('/api/reservations?status=checked_in&no_pagination=1', [
            'Cila Meta',
        ]);
        $this->assertGuestNames('/api/reservations?source=TELEFON&no_pagination=1', [
            'Ana Kola',
        ]);
        $this->assertGuestNames('/api/reservations?source=BOOKING&no_pagination=1', [
            'Booking Guest',
        ]);
        $this->assertGuestNames('/api/reservations?q=krevat&page_size=10', [
            'Ana Kola',
        ]);
        $this->assertGuestNames('/api/reservations?search=102&no_pagination=1', [
            'Cila Meta',
        ]);

        $calendar = $this->getJson('/api/reservations?from=2026-09-21&to=2026-09-23&no_pagination=1');
        $calendar->assertOk()->assertJsonMissingPath('meta.total');
        $names = $this->names($calendar->json('data'));
        $this->assertSame(['Ana Kola', 'Cila Meta', 'Dritan Lika'], $names);
        $this->assertNotContains('Ben Hoxha', $names);
        $this->assertNotContains('Era Duka', $names);

        $this->getJson('/api/reservations?status=SLEEPING')->assertStatus(422)
            ->assertJsonPath('message', 'Zgjidhni një status të vlefshëm.');
        $this->getJson('/api/reservations?source=AGODA')->assertStatus(422)
            ->assertJsonPath('message', 'Zgjidhni një burim të vlefshëm.');
        $this->getJson('/api/reservations?arriving_on=21-09-2026')->assertStatus(422);
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertGuestNames(string $url, array $expected): void
    {
        $response = $this->getJson($url)->assertOk();
        $this->assertSame($expected, $this->names($response->json('data')));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function names(array $rows): array
    {
        $names = array_map(fn (array $row) => $row['guestName'], $rows);
        sort($names);

        return array_values($names);
    }

    private function stay(Room $room, string $name, string $source, string $checkIn, int $nights, ?string $notes = null): string
    {
        $payload = [
            'roomId' => $room->number,
            'guestName' => $name,
            'persons' => 1,
            'source' => $source,
            'checkIn' => $checkIn,
            'nights' => $nights,
            'totalCents' => 10000,
        ];
        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        return (string) $this->postJson('/api/reservations', $payload)->assertOk()->json('data.id');
    }

    /**
     * @return array{0: User, 1: array<string, Room>}
     */
    private function desk(): array
    {
        $property = Property::query()->create([
            'name' => 'Vila Dea',
            'city' => 'Sarandë',
            'currency' => 'EUR',
        ]);
        $type = RoomType::query()->create([
            'property_id' => $property->id,
            'code' => 'double',
            'ui_type' => 'Dyshe',
            'capacity' => 2,
        ]);
        $rooms = [];
        foreach (['101', '102', '103', '104'] as $number) {
            $rooms[$number] = Room::query()->create([
                'property_id' => $property->id,
                'room_type_id' => $type->id,
                'number' => $number,
                'capacity' => 2,
                'status' => RoomStatus::Active,
            ]);
        }
        $user = User::factory()->create([
            'property_id' => $property->id,
            'role_id' => Role::query()->firstOrCreate(
                ['code' => UserRole::Reception->value],
                ['name' => UserRole::Reception->label()],
            )->id,
        ]);

        return [$user, $rooms];
    }
}
