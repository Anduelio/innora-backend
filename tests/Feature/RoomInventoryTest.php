<?php

namespace Tests\Feature;

use App\Enums\OperationalStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Managers\Hotel\AvailabilityManager;
use App\Managers\Reservations\ReservationManager;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RoomInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_numbers_are_strings_and_unique_per_property(): void
    {
        [$user, $type] = $this->hotel();
        Passport::actingAs($user);

        $this->postJson('/api/rooms', [
            'roomTypeId' => $type->id,
            'number' => 'A01',
            'floor' => '1',
        ])->assertOk()->assertJsonPath('data.id', 'A01');

        $this->postJson('/api/rooms', [
            'roomTypeId' => $type->id,
            'number' => 'A01',
        ])->assertStatus(422);

        $other = Property::query()->create(['name' => 'Tjetër', 'city' => 'Tiranë', 'currency' => 'EUR']);
        $otherType = RoomType::query()->create([
            'property_id' => $other->id,
            'code' => 'DBL',
            'ui_type' => 'Dyshe',
            'name' => 'Dhomë Dyshe',
            'capacity' => 3,
            'max_adults' => 2,
            'max_children' => 1,
            'max_occupancy' => 3,
        ]);
        Room::query()->create([
            'property_id' => $other->id,
            'room_type_id' => $otherType->id,
            'number' => 'A01',
            'capacity' => 3,
            'status' => RoomStatus::Active,
        ]);

        $this->assertSame(2, Room::query()->where('number', 'A01')->count());
    }

    public function test_room_type_stores_beds_amenities_and_rejects_too_many_guests(): void
    {
        [$user] = $this->hotel();
        Passport::actingAs($user);

        $created = $this->postJson('/api/room-types', [
            'name' => 'Dhomë Dyshe Deluxe',
            'code' => 'DBL-DLX',
            'maxAdults' => 2,
            'maxChildren' => 1,
            'maxOccupancy' => 3,
            'sizeM2' => 28,
            'basePriceCents' => 8000,
            'beds' => [['code' => 'king', 'quantity' => 1]],
            'amenityCodes' => ['wifi', 'tv', 'air_conditioning', 'balcony'],
        ]);

        $created->assertOk()
            ->assertJsonPath('data.code', 'DBL-DLX')
            ->assertJsonPath('data.beds.0.code', 'king')
            ->assertJsonPath('data.maxOccupancy', 3);

        $typeId = $created->json('data.id');
        $this->postJson('/api/rooms', ['roomTypeId' => $typeId, 'number' => '204'])->assertOk();

        $this->postJson('/api/reservations', [
            'roomId' => '204',
            'guestName' => 'Shumë persona',
            'persons' => 3,
            'children' => 1,
            'checkIn' => '2026-09-22',
            'nights' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Ky tip dhome nuk i mban kaq persona.');

        $this->postJson('/api/reservations', [
            'roomId' => '204',
            'guestName' => 'Arben Hoxha',
            'persons' => 2,
            'children' => 1,
            'checkIn' => '2026-09-22',
            'nights' => 3,
        ])->assertOk()->assertJsonPath('data.roomId', '204');
    }

    public function test_blocks_checkout_day_and_unassigned_inventory(): void
    {
        [$user, $type, $room] = $this->hotel(withRoom: true);
        $availability = app(AvailabilityManager::class);

        $this->assertSame(1, $availability->availableUnits($type, '2026-09-22'));

        Passport::actingAs($user);
        $this->postJson('/api/rooms/'.$room->number.'/blocks', [
            'startsOn' => '2026-09-26',
            'endsOn' => '2026-09-28',
            'type' => 'maintenance',
            'reason' => 'Defekt kondicioneri',
        ])->assertOk();

        $this->assertSame(0, $availability->availableUnits($type->fresh(), '2026-09-26'));
        $this->assertSame(1, $availability->availableUnits($type->fresh(), '2026-09-25'));

        $open = $this->postJson('/api/reservations', [
            'roomTypeId' => $type->id,
            'guestName' => 'Pa dhomë',
            'persons' => 2,
            'checkIn' => '2026-09-22',
            'nights' => 3,
        ])->assertOk();
        $this->assertSame('', $open->json('data.roomId'));
        $this->assertSame(0, $availability->availableUnits($type->fresh(), '2026-09-22'));

        $this->patchJson('/api/reservations/'.$open->json('data.id'), [
            'notes' => 'Pa numër',
        ])->assertOk()->assertJsonPath('data.roomId', '');

        $this->postJson('/api/reservations/'.$open->json('data.id').'/assign', [
            'roomId' => $room->number,
        ])->assertOk()->assertJsonPath('data.roomId', $room->number);

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Pas daljes',
            'persons' => 1,
            'checkIn' => '2026-09-25',
            'nights' => 1,
        ])->assertOk()->assertJsonPath('data.checkIn', '2026-09-25');

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Konflikt',
            'persons' => 1,
            'checkIn' => '2026-09-24',
            'nights' => 2,
        ])->assertStatus(422);

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Gjatë bllokimit',
            'persons' => 1,
            'checkIn' => '2026-09-26',
            'nights' => 1,
        ])->assertStatus(422);
    }

    public function test_inactive_and_out_of_order_rooms_are_not_sellable(): void
    {
        [, $type, $room] = $this->hotel(withRoom: true);
        $availability = app(AvailabilityManager::class);

        $room->is_active = false;
        $room->save();
        $this->assertSame(0, $availability->availableUnits($type->fresh(), '2026-09-22'));

        $room->is_active = true;
        $room->operational_status = OperationalStatus::OutOfOrder;
        $room->save();
        $this->assertSame(0, $availability->availableUnits($type->fresh(), '2026-09-22'));
    }

    public function test_checkout_dirties_the_room_and_blocks_check_in_until_ready(): void
    {
        [$user, , $room] = $this->hotel(withRoom: true);
        Passport::actingAs($user);
        $manager = app(ReservationManager::class);

        $stay = $manager->create($user, [
            'roomId' => $room->number,
            'guestName' => 'Arben Hoxha',
            'persons' => 2,
            'checkIn' => '2026-09-22',
            'nights' => 3,
        ]);

        $manager->checkIn($user, $stay);
        $this->assertNotNull($stay->fresh()->checked_in_at);

        $manager->checkOut($user, $stay->fresh());
        $room->refresh();
        $this->assertSame(OperationalStatus::Dirty, $room->operational_status);
        $this->assertSame(ReservationStatus::CheckedOut, $stay->fresh()->status);

        $next = $manager->create($user, [
            'roomId' => $room->number,
            'guestName' => 'Tjetri',
            'persons' => 1,
            'checkIn' => '2026-10-01',
            'nights' => 1,
        ]);

        $this->postJson('/api/reservations/'.$next->id.'/check-in')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Dhoma '.$room->number.' nuk është ende gati.');

        $this->postJson('/api/rooms/'.$room->number.'/status', [
            'operationalStatus' => 'cleaning',
        ])->assertOk()->assertJsonPath('data.operationalStatus', 'cleaning');

        $this->postJson('/api/rooms/'.$room->number.'/status', [
            'operationalStatus' => 'ready',
        ])->assertOk();

        $this->postJson('/api/reservations/'.$next->id.'/check-in')
            ->assertOk()
            ->assertJsonPath('data.status', 'IN_HOUSE');
    }

    public function test_bulk_create_and_hotel_hours(): void
    {
        [$user, $type] = $this->hotel();
        Passport::actingAs($user);

        $this->postJson('/api/rooms/bulk', [
            'roomTypeId' => $type->id,
            'numbers' => ['201', 'Villa 2'],
        ])->assertOk()->assertJsonCount(2, 'data');

        $this->putJson('/api/settings/hotel', [
            'checkInTime' => '14:00',
            'checkOutTime' => '11:00',
            'currency' => 'ALL',
        ])->assertOk()
            ->assertJsonPath('data.checkInTime', '14:00')
            ->assertJsonPath('data.checkOutTime', '11:00')
            ->assertJsonPath('data.currency', 'ALL');
    }

    /**
     * @return array{0: User, 1: RoomType, 2?: Room}
     */
    private function hotel(bool $withRoom = false): array
    {
        $property = Property::query()->create([
            'name' => 'Hotel Innora',
            'city' => 'Sarandë',
            'currency' => 'EUR',
            'default_check_in_time' => '14:00',
            'default_check_out_time' => '11:00',
        ]);
        $type = RoomType::query()->create([
            'property_id' => $property->id,
            'code' => 'DBL',
            'ui_type' => 'Dyshe',
            'name' => 'Dhomë Dyshe',
            'capacity' => 2,
            'base_occupancy' => 2,
            'max_adults' => 2,
            'max_children' => 0,
            'max_occupancy' => 2,
        ]);
        $role = Role::query()->firstOrCreate(
            ['code' => UserRole::Reception->value],
            ['name' => UserRole::Reception->label()],
        );
        $user = User::factory()->create([
            'property_id' => $property->id,
            'role_id' => $role->id,
            'email' => 'recepsion-'.uniqid().'@innora.test',
        ]);

        if (! $withRoom) {
            return [$user, $type];
        }

        $room = Room::query()->create([
            'property_id' => $property->id,
            'room_type_id' => $type->id,
            'number' => '204',
            'capacity' => 2,
            'status' => RoomStatus::Active,
            'operational_status' => OperationalStatus::Ready,
            'is_active' => true,
        ]);

        return [$user, $type, $room];
    }
}
