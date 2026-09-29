<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GuestCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_be_created_updated_and_deleted_without_stays(): void
    {
        [$user] = $this->desk();
        Passport::actingAs($user);

        $created = $this->postJson('/api/guests', [
            'name' => 'Ana Kola',
            'phonePrefix' => '+355',
            'phone' => '69 111 2222',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ana Kola')
            ->assertJsonPath('data.phonePrefix', '+355')
            ->assertJsonPath('data.phone', '691112222')
            ->assertJsonPath('data.stays', 0);

        $id = $created->json('data.id');

        $this->getJson('/api/guests/'.$id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Ana Kola')
            ->assertJsonPath('data.phonePrefix', '+355')
            ->assertJsonPath('data.phone', '691112222')
            ->assertJsonPath('data.stays', 0);

        $this->putJson('/api/guests/'.$id, [
            'name' => 'Ana Kola Duka',
            'phonePrefix' => '+355',
            'phone' => '691112222',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ana Kola Duka');

        $this->postJson('/api/guests', [
            'name' => 'Dublikat',
            'phonePrefix' => '+355',
            'phone' => '691112222',
        ])->assertStatus(422);

        $this->deleteJson('/api/guests/'.$id)->assertOk();
        $this->assertNull(Guest::query()->find($id));
        $this->getJson('/api/guests/'.$id)->assertNotFound();
    }

    public function test_phone_is_unique_inside_one_property_and_a_guest_from_another_property_is_hidden(): void
    {
        [$user] = $this->desk(false);
        Passport::actingAs($user);

        Guest::query()->create([
            'property_id' => $user->property_id,
            'name' => 'Ana',
            'phone_prefix' => '+355',
            'phone' => '691110000',
        ]);

        $other = Property::query()->create([
            'name' => 'Tjetër',
            'city' => 'Sarandë',
            'currency' => 'EUR',
        ]);
        $foreign = Guest::query()->create([
            'property_id' => $other->id,
            'name' => 'I huaj',
            'phone_prefix' => '+355',
            'phone' => '691110000',
        ]);

        Guest::query()->create([
            'property_id' => $user->property_id,
            'name' => 'Pa numër',
            'phone_prefix' => null,
            'phone' => null,
        ]);
        Guest::query()->create([
            'property_id' => $user->property_id,
            'name' => 'Pa numër 2',
            'phone_prefix' => null,
            'phone' => null,
        ]);

        $this->getJson('/api/guests/'.$foreign->id)->assertStatus(422);
        $this->getJson('/api/guests?no_pagination=1')
            ->assertOk()
            ->assertJsonMissing(['name' => 'I huaj']);

        try {
            Guest::query()->create([
                'property_id' => $user->property_id,
                'name' => 'Dublikat',
                'phone_prefix' => '+355',
                'phone' => '691110000',
            ]);
            $this->fail('The same phone was stored twice.');
        } catch (UniqueConstraintViolationException) {
            $this->assertTrue(true);
        }
    }

    public function test_guest_with_a_reservation_cannot_be_deleted_and_list_filters_phone(): void
    {
        [$user, $room] = $this->desk();
        Passport::actingAs($user);

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Ana Kola',
            'phonePrefix' => '+355',
            'phone' => '693334444',
            'registerCustomer' => true,
            'persons' => 1,
            'checkIn' => '2026-09-19',
            'nights' => 1,
        ])->assertOk();

        $guestId = Guest::query()->where('phone', '693334444')->value('id');

        $this->deleteJson('/api/guests/'.$guestId)->assertStatus(422);

        $this->getJson('/api/guests?withPhone=1&page_size=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/guests', [
            'name' => 'Pa telefon',
        ])->assertOk();

        $this->getJson('/api/guests?withPhone=1&page_size=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/guests?q=Ana&page_size=10')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ana Kola');
    }

    /**
     * @return array{0: User, 1?: Room}
     */
    private function desk(bool $withRoom = true): array
    {
        $property = Property::query()->create([
            'name' => 'Hotel Innora',
            'city' => 'Sarandë',
            'currency' => 'EUR',
        ]);
        $user = User::factory()->create([
            'property_id' => $property->id,
            'role_id' => Role::query()->firstOrCreate(
                ['code' => UserRole::Reception->value],
                ['name' => UserRole::Reception->label()],
            )->id,
        ]);

        if (! $withRoom) {
            return [$user];
        }

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

        return [$user, $room];
    }
}
