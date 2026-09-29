<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GuestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_follow_meta_and_the_search_term(): void
    {
        [$user, $room] = $this->desk();
        Passport::actingAs($user);

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Ana Kola',
            'phone' => '692345566',
            'persons' => 2,
            'checkIn' => '2026-09-19',
            'nights' => 2,
        ])->assertOk();

        $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Endrit Lika',
            'phone' => '684112200',
            'persons' => 1,
            'checkIn' => '2026-09-22',
            'nights' => 1,
        ])->assertOk();

        $page = $this->getJson('/api/guests?page=1&page_size=1');
        $page->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/guests?q=Ana&page_size=4')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ana Kola');

        $this->getJson('/api/guests?no_pagination=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('meta.total');
    }

    /**
     * @return array{0: User, 1: Room}
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
        ]);

        return [$user, $room];
    }
}
