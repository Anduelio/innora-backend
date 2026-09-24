<?php

namespace Tests\Feature;

use App\Enums\FolioStatus;
use App\Enums\OperationalStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
use App\Models\ChargeCategory;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Reporting\HotelReportService;
use Database\Seeders\ChargeCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FolioFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marco_bianchi_acceptance_separates_revenue_and_payments(): void
    {
        [$reception, $owner, $room] = $this->hotel();
        Passport::actingAs($reception);

        $created = $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Marco Bianchi',
            'persons' => 2,
            'checkIn' => '2026-09-22',
            'nights' => 3,
            'totalCents' => 30000,
            'source' => 'DIREKT',
        ])->assertOk();

        $reservationId = $created->json('data.id');
        $folioId = $created->json('data.folioId');
        $this->assertNotNull($folioId);

        $folio = Folio::query()->findOrFail($folioId);
        $this->assertSame(30000, $folio->charges_cents);
        $this->assertCount(3, FolioItem::query()->where('folio_id', $folio->id)->whereNull('voided_at')->get());

        $minibar = ChargeCategory::query()->where('property_id', $reception->property_id)->where('code', 'minibar')->firstOrFail();
        $late = ChargeCategory::query()->where('property_id', $reception->property_id)->where('code', 'late_checkout')->firstOrFail();

        $this->postJson("/api/folios/{$folioId}/charges", [
            'chargeCategoryId' => $minibar->id,
            'description' => 'Minibar',
            'quantity' => 1,
            'unitCents' => 1500,
            'serviceDate' => '2026-09-24',
        ])->assertOk();

        $this->postJson("/api/folios/{$folioId}/charges", [
            'chargeCategoryId' => $late->id,
            'description' => 'Late checkout',
            'quantity' => 1,
            'unitCents' => 3000,
            'serviceDate' => '2026-09-25',
        ])->assertOk();

        $this->postJson("/api/folios/{$folioId}/payments", [
            'method' => 'cash',
            'amountCents' => 10000,
            'paidAt' => '2026-09-22 14:30:00',
        ])->assertOk();

        $this->postJson("/api/folios/{$folioId}/payments", [
            'method' => 'card',
            'amountCents' => 10000,
            'paidAt' => '2026-09-23 09:15:00',
        ])->assertOk();

        $this->postJson("/api/folios/{$folioId}/payments", [
            'method' => 'cash',
            'amountCents' => 14500,
            'paidAt' => '2026-09-25 10:45:00',
        ])->assertOk();

        $folio->refresh();
        $this->assertSame(34500, $folio->charges_cents);
        $this->assertSame(34500, $folio->payments_cents);
        $this->assertSame(0, $folio->balance_cents);

        Passport::actingAs($owner);
        $reports = app(HotelReportService::class);
        $revenue = $reports->revenue($owner, '2026-09-22', '2026-09-25');
        $payments = $reports->payments($owner, '2026-09-22', '2026-09-25');

        $this->assertSame(34500, $revenue['totalCents']);
        $this->assertSame(30000, $revenue['roomCents']);
        $this->assertSame(4500, $revenue['extraCents']);
        $byCategory = collect($revenue['byCategory'])->keyBy('code');
        $this->assertSame(30000, $byCategory['room']['amountCents']);
        $this->assertSame(1500, $byCategory['minibar']['amountCents']);
        $this->assertSame(3000, $byCategory['late_checkout']['amountCents']);

        $this->assertSame(34500, $payments['totalCents']);
        $byMethod = collect($payments['byMethod'])->keyBy('method');
        $this->assertSame(24500, $byMethod['cash']['amountCents']);
        $this->assertSame(10000, $byMethod['card']['amountCents']);

        $this->assertNotEquals($revenue['byCategory'], $payments['byMethod']);

        Passport::actingAs($reception);
        $this->postJson("/api/reservations/{$reservationId}/check-in")->assertOk();
        $this->postJson("/api/reservations/{$reservationId}/check-out")->assertOk();
        $this->assertSame(FolioStatus::Closed, $folio->fresh()->status);
    }

    public function test_reception_cannot_checkout_with_balance(): void
    {
        [$reception, , $room] = $this->hotel();
        Passport::actingAs($reception);

        $created = $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Guest Due',
            'persons' => 1,
            'checkIn' => '2026-09-22',
            'nights' => 1,
            'totalCents' => 10000,
        ])->assertOk();

        $id = $created->json('data.id');
        $this->postJson("/api/reservations/{$id}/check-in")->assertOk();
        $this->postJson("/api/reservations/{$id}/check-out")
            ->assertStatus(422)
            ->assertJsonPath('errors.balance.0', fn ($m) => is_string($m) && $m !== '');
    }

    public function test_posted_room_charge_keeps_snapshot_when_booking_total_changes_on_closed_path(): void
    {
        [$reception, , $room] = $this->hotel();
        Passport::actingAs($reception);

        $created = $this->postJson('/api/reservations', [
            'roomId' => $room->number,
            'guestName' => 'Snapshot Guest',
            'persons' => 1,
            'checkIn' => '2026-09-22',
            'nights' => 1,
            'totalCents' => 10000,
        ])->assertOk();

        $folioId = $created->json('data.folioId');
        $item = FolioItem::query()->where('folio_id', $folioId)->whereNull('voided_at')->firstOrFail();
        $this->assertSame(10000, $item->amount_cents);

        $this->patchJson('/api/reservations/'.$created->json('data.id'), [
            'totalCents' => 15000,
        ])->assertOk();

        $active = FolioItem::query()->where('folio_id', $folioId)->whereNull('voided_at')->get();
        $this->assertCount(1, $active);
        $this->assertSame(15000, $active->first()->amount_cents);
        $this->assertTrue(FolioItem::query()->where('folio_id', $folioId)->whereNotNull('voided_at')->exists());
    }

    /**
     * @return array{0: User, 1: User, 2: Room}
     */
    private function hotel(): array
    {
        foreach (UserRole::cases() as $role) {
            Role::query()->updateOrCreate(['code' => $role->value], ['name' => $role->label()]);
        }

        $property = Property::query()->create([
            'name' => 'Hotel Folio',
            'city' => 'Sarandë',
            'currency' => 'EUR',
            'default_check_in_time' => '14:00',
            'default_check_out_time' => '11:00',
        ]);

        (new ChargeCategorySeeder)->seedFor($property);

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

        $room = Room::query()->create([
            'property_id' => $property->id,
            'room_type_id' => $type->id,
            'number' => '204',
            'capacity' => 2,
            'status' => RoomStatus::Active,
            'operational_status' => OperationalStatus::Ready,
            'is_active' => true,
        ]);

        $reception = User::factory()->create([
            'property_id' => $property->id,
            'role_id' => Role::query()->where('code', UserRole::Reception->value)->value('id'),
            'email' => 'recepsion-'.uniqid().'@innora.test',
        ]);

        $owner = User::factory()->create([
            'property_id' => $property->id,
            'role_id' => Role::query()->where('code', UserRole::Owner->value)->value('id'),
            'email' => 'owner-'.uniqid().'@innora.test',
        ]);

        return [$reception, $owner, $room];
    }
}
