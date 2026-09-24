<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Enums\UserRole;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Managers\Hotel\AvailabilityManager;
use App\Managers\Reservations\ReservationManager;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_reservation_is_created_and_overlap_is_rejected(): void
    {
        [$user, $room] = $this->desk();
        Passport::actingAs($user);

        $created = $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Elira Duka',
            'persons' => 2,
            'checkIn' => '2026-09-21',
            'nights' => 2,
            'totalCents' => 18000,
        ], ['no_pagination' => 1]);

        $created->assertOk()
            ->assertJsonPath('data.guestName', 'Elira Duka')
            ->assertJsonPath('data.status', 'RESERVED')
            ->assertJsonPath('data.source', 'DIREKT')
            ->assertJsonPath('data.checkOut', '2026-09-23');

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Tjetër',
            'persons' => 1,
            'checkIn' => '2026-09-22',
            'nights' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Dhoma është e zënë për këto data.');
    }

    public function test_phone_stay_is_stored_and_booking_cannot_be_typed_at_the_desk(): void
    {
        [$user, $room] = $this->desk();
        Passport::actingAs($user);

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Arben Hoxha',
            'phone' => '+355 69 123 4567',
            'persons' => 2,
            'source' => 'TELEFON',
            'checkIn' => '2026-09-21',
            'nights' => 2,
        ])->assertOk()
            ->assertJsonPath('data.source', 'TELEFON')
            ->assertJsonPath('data.guestName', 'Arben Hoxha');

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Nga Booking',
            'persons' => 2,
            'source' => 'BOOKING',
            'checkIn' => '2026-10-01',
            'nights' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Booking.com nuk shkruhet këtu. Zgjidhni telefon, recepsion, WhatsApp ose direkt.');
    }

    public function test_checkout_morning_is_free_and_cancel_restores_the_room(): void
    {
        [$user, $room] = $this->desk();
        $manager = app(ReservationManager::class);

        $stay = $manager->create($user, [
            'roomId' => $room->number,
            'guestName' => 'Arta',
            'persons' => 1,
            'checkIn' => '2026-09-18',
            'nights' => 3,
        ]);
        $this->assertSame('2026-09-21', $stay->check_out->toDateString());

        $next = $manager->create($user, [
            'roomId' => $room->number,
            'guestName' => 'Klara',
            'persons' => 2,
            'checkIn' => '2026-09-21',
            'nights' => 1,
        ]);
        $this->assertSame('Klara', $next->guest->name);

        $manager->cancel($user, $next);
        $again = $manager->create($user, [
            'roomId' => $room->number,
            'guestName' => 'Pas anulimit',
            'persons' => 1,
            'checkIn' => '2026-09-21',
            'nights' => 1,
        ]);
        $this->assertSame('Pas anulimit', $again->guest->name);
    }

    public function test_room_block_reduces_availability_and_external_import_is_idempotent(): void
    {
        [$user, $room, $type] = $this->desk();
        $availability = app(AvailabilityManager::class);
        $manager = app(ReservationManager::class);

        $this->assertSame(1, $availability->availableUnits($type, '2026-09-21'));

        RoomBlock::query()->create([
            'room_id' => $room->id,
            'starts_on' => '2026-09-21',
            'ends_on' => '2026-09-22',
            'reason' => 'maintenance',
        ]);
        $this->assertSame(0, $availability->availableUnits($type, '2026-09-21'));

        RoomBlock::query()->delete();
        $first = $manager->importExternal($user, [
            'external_id' => 'BDC-1',
            'guest_name' => 'John Smith',
            'check_in' => '2026-09-21',
            'check_out' => '2026-09-24',
            'room_type_code' => 'double',
            'persons' => 2,
            'total_cents' => 38000,
        ]);
        $second = $manager->importExternal($user, [
            'external_id' => 'BDC-1',
            'action' => 'create',
            'guest_name' => 'John Smith',
            'check_in' => '2026-09-21',
            'check_out' => '2026-09-25',
            'room_type_code' => 'double',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Reservation::query()->where('external_id', 'BDC-1')->count());
        $this->assertSame('2026-09-25', $second->check_out->toDateString());

        $manager->importExternal($user, [
            'external_id' => 'BDC-1',
            'action' => 'cancel',
        ]);
        $this->assertSame(1, $availability->availableUnits($type, '2026-09-21'));
    }

    public function test_checked_out_stay_still_occupies_its_dates_and_status_guards_hold(): void
    {
        [$user, $room] = $this->desk();
        $manager = app(ReservationManager::class);
        $stay = $manager->create($user, [
            'roomId' => $room->number,
            'guestName' => 'Vera',
            'persons' => 2,
            'checkIn' => '2026-09-17',
            'nights' => 3,
        ]);

        $this->assertSame(422, $this->rejection(fn () => $manager->checkOut($user, $stay)));
        $manager->checkIn($user, $stay);
        $this->assertSame(422, $this->rejection(fn () => $manager->checkIn($user, $stay->fresh())));
        $manager->checkOut($user, $stay->fresh());

        $this->assertSame(422, $this->rejection(fn () => $manager->create($user, [
            'roomId' => $room->number,
            'guestName' => 'Tjetër',
            'persons' => 1,
            'checkIn' => '2026-09-18',
            'nights' => 1,
        ])));
    }

    private function rejection(callable $action): int
    {
        try {
            $action();
        } catch (HttpResponseException $exception) {
            return $exception->getResponse()->getStatusCode();
        }

        $this->fail('Expected a validation response.');
    }

    /**
     * @return array{0: User, 1: Room, 2: RoomType}
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
        $room = Room::query()->create([
            'property_id' => $property->id,
            'room_type_id' => $type->id,
            'number' => '105',
            'capacity' => 2,
            'status' => RoomStatus::Active,
        ]);
        $user = User::factory()->create([
            'property_id' => $property->id,
            'role_id' => Role::query()->firstOrCreate(
                ['code' => UserRole::Reception->value],
                ['name' => UserRole::Reception->label()],
            )->id,
            'email' => 'recepsion@viladea.al',
        ]);

        return [$user, $room, $type];
    }
}
