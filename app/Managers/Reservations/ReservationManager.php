<?php

namespace App\Managers\Reservations;

use App\Access\Visibility\PropertyVisibility;
use App\Enums\OperationalStatus;
use App\Enums\PermissionEnum;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Jobs\SyncAvailabilityJob;
use App\Managers\Billing\FolioManager;
use App\Managers\Hotel\AvailabilityManager;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomEvent;
use App\Models\RoomType;
use App\Models\User;
use App\Pipelines\Filters\Reservation\OverlapFromFilter;
use App\Pipelines\Filters\Reservation\OverlapToFilter;
use App\Traits\HasMessages;
use App\Traits\PipelineHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReservationManager
{
    use HasMessages, PipelineHandler;

    public function __construct(
        private readonly AvailabilityManager $availability,
        private readonly PropertyVisibility $visibility,
        private readonly FolioManager $folios,
    ) {}

    public function list(User $actor, array $filters): Builder
    {
        $query = $this->visibility->list(
            Reservation::query()->with(['guest', 'stay.room.roomType', 'stay.roomType', 'folio']),
            $actor,
        );

        return $this->applyPipeline($query, $filters, [
            OverlapFromFilter::class,
            OverlapToFilter::class,
        ]);
    }

    public function create(User $actor, array $data): Reservation
    {
        $property = $this->property($actor);
        $checkIn = $data['checkIn'];
        $checkOut = date('Y-m-d', strtotime($checkIn.' +'.$data['nights'].' days'));

        if (empty($data['roomId'])) {
            return $this->createUnassigned($property, $data, $checkIn, $checkOut);
        }

        $reservation = DB::transaction(function () use ($property, $data, $checkIn, $checkOut) {
            $room = $this->lockRoom($property, $data['roomId']);
            $room->load('roomType');
            $this->guardOccupancy($room->roomType, (int) $data['persons'], (int) ($data['children'] ?? 0));
            $this->guardFree($room, $checkIn, $checkOut);
            $guest = $this->resolveGuest($property, $data);

            $reservation = Reservation::query()->create([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'source' => ReservationSource::fromDesk($data['source'] ?? 'DIREKT'),
                'status' => ReservationStatus::Confirmed,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => $data['persons'],
                'children' => (int) ($data['children'] ?? 0),
                'expected_arrival' => $data['expectedArrival'] ?? '14:00',
                'total_cents' => $data['totalCents'] ?? 0,
                'paid_cents' => 0,
                'currency' => $property->currency,
                'notes' => $this->blankToNull($data['notes'] ?? null),
            ]);
            $this->writeStay($reservation, $room, $checkIn, $checkOut);
            $this->folios->openFor($reservation, null);

            return $reservation;
        });

        SyncAvailabilityJob::dispatch($property->id)->afterCommit();

        return $reservation->load(['guest', 'stay.room.roomType', 'folio']);
    }

    public function update(User $actor, Reservation $reservation, array $data): Reservation
    {
        $this->assertOwned($actor, $reservation);
        $mapped = [];
        foreach ([
            'roomId' => 'room_number',
            'guestName' => 'guest_name',
            'phone' => 'phone',
            'persons' => 'persons',
            'checkIn' => 'check_in',
            'checkOut' => 'check_out',
            'totalCents' => 'total_cents',
            'notes' => 'notes',
        ] as $from => $to) {
            if (array_key_exists($from, $data)) {
                $mapped[$to] = $data[$from];
            }
        }

        $updated = DB::transaction(function () use ($actor, $reservation, $mapped, $data) {
            $reservation->load(['stay.room', 'guest', 'property', 'folio']);
            $stay = $reservation->stay;
            $checkIn = $mapped['check_in'] ?? $reservation->check_in->toDateString();
            $checkOut = $mapped['check_out'] ?? $reservation->check_out->toDateString();
            if ($checkOut <= $checkIn) {
                $this->throwValidationError('checkOut', 'messages.reservations.checkout_after_checkin');
            }

            $number = ($mapped['room_number'] ?? '') !== '' ? $mapped['room_number'] : $stay?->room?->number;
            $datesOrTotalChanged = isset($mapped['check_in'])
                || isset($mapped['check_out'])
                || isset($mapped['total_cents'])
                || isset($mapped['room_number']);

            if (isset($mapped['guest_name']) || array_key_exists('phone', $data) || array_key_exists('phonePrefix', $data)) {
                $guest = $this->resolveGuest($reservation->property, [
                    'guestName' => $data['guestName'] ?? $reservation->guest->name,
                    'phone' => array_key_exists('phone', $data) ? $data['phone'] : $reservation->guest->phone,
                    'phonePrefix' => array_key_exists('phonePrefix', $data) ? $data['phonePrefix'] : $reservation->guest->phone_prefix,
                    'registerCustomer' => $data['registerCustomer'] ?? false,
                ], $reservation->guest);
                $reservation->guest_id = $guest->id;
                $reservation->setRelation('guest', $guest);
            }

            $reservation->fill([
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => $mapped['persons'] ?? $reservation->adults,
                'total_cents' => $mapped['total_cents'] ?? $reservation->total_cents,
                'notes' => array_key_exists('notes', $mapped) ? $this->blankToNull($mapped['notes']) : $reservation->notes,
            ])->save();
            if ($number) {
                $room = $this->lockRoom($reservation->property, $number);
                $this->guardFree($room, $checkIn, $checkOut, $reservation->id);
                $this->writeStay($reservation, $room, $checkIn, $checkOut);
            } elseif ($stay !== null) {
                $stay->fill(['check_in' => $checkIn, 'check_out' => $checkOut])->save();
            }

            if ($datesOrTotalChanged) {
                $folio = $this->folios->forReservation($reservation->fresh(['stay.room']));
                if ($folio->isOpen()) {
                    $this->folios->syncRoomCharges($folio, $reservation->fresh(['stay.room']), $actor);
                }
            }

            return $reservation;
        });

        SyncAvailabilityJob::dispatch($reservation->property_id)->afterCommit();

        return $updated->load(['guest', 'stay.room.roomType', 'folio']);
    }

    public function checkIn(User $actor, Reservation $reservation): Reservation
    {
        $this->assertOwned($actor, $reservation);
        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            $this->throwValidationError('status', 'messages.reservations.check_in_not_allowed');
        }

        $reservation->load('stay.room');
        $room = $reservation->stay?->room;
        if ($room === null) {
            $this->throwValidationError('roomId', 'messages.reservations.room_not_assigned');
        }
        if (! $this->availability->canCheckIn($room, $reservation->check_in->toDateString(), $reservation->check_out->toDateString())) {
            $this->throwValidationError('roomId', 'messages.reservations.room_not_ready', ['room' => $room->number]);
        }

        $reservation->status = ReservationStatus::CheckedIn;
        $reservation->checked_in_at = now();
        $reservation->save();
        $this->roomEvent($room, $actor, 'checked_in', ['reservation_id' => $reservation->id]);

        return $reservation->load(['guest', 'stay.room.roomType']);
    }

    public function checkOut(User $actor, Reservation $reservation): Reservation
    {
        $this->assertOwned($actor, $reservation);
        if ($reservation->status !== ReservationStatus::CheckedIn) {
            $this->throwValidationError('status', 'messages.reservations.check_out_not_allowed');
        }

        return DB::transaction(function () use ($actor, $reservation) {
            $reservation->load(['stay.room', 'folio']);
            $folio = $this->folios->forReservation($reservation);
            $this->folios->recalculate($folio);

            $allowOutstanding = $actor->canPermission(PermissionEnum::FoliosCheckoutOutstanding);
            if ($folio->balance_cents > 0 && ! $allowOutstanding) {
                $this->throwValidationError('balance', 'messages.folios.balance_must_be_zero');
            }

            $reservation->status = ReservationStatus::CheckedOut;
            $reservation->checked_out_at = now();
            $reservation->save();

            if ($folio->isOpen() && $folio->balance_cents <= 0) {
                $this->folios->close($actor, $folio->fresh());
            }

            $room = $reservation->stay?->room;
            if ($room !== null) {
                $from = $room->operational_status->value;
                $room->operational_status = OperationalStatus::Dirty;
                $room->save();
                $this->roomEvent($room, $actor, 'checked_out', [
                    'reservation_id' => $reservation->id,
                    'from' => $from,
                    'to' => OperationalStatus::Dirty->value,
                ]);
            }

            return $reservation->load(['guest', 'stay.room.roomType', 'folio']);
        });
    }

    public function assign(User $actor, Reservation $reservation, string $number): Reservation
    {
        $this->assertOwned($actor, $reservation);
        if (in_array($reservation->status, [ReservationStatus::Cancelled, ReservationStatus::CheckedOut, ReservationStatus::NoShow], true)) {
            $this->throwValidationError('status', 'messages.reservations.check_in_not_allowed');
        }

        $updated = DB::transaction(function () use ($actor, $reservation, $number) {
            $reservation->load(['stay', 'property']);
            $room = $this->lockRoom($reservation->property, $number);
            if ($reservation->stay === null || $room->room_type_id !== $reservation->stay->room_type_id) {
                $this->throwValidationError('roomId', 'messages.reservations.room_type_mismatch');
            }
            $this->guardFree($room, $reservation->check_in->toDateString(), $reservation->check_out->toDateString(), $reservation->id);
            $this->writeStay($reservation, $room, $reservation->check_in->toDateString(), $reservation->check_out->toDateString());
            $this->roomEvent($room, $actor, 'assigned', ['reservation_id' => $reservation->id]);
            $folio = $this->folios->forReservation($reservation->fresh(['stay.room']));
            if ($folio->isOpen()) {
                $this->folios->syncRoomCharges($folio, $reservation->fresh(['stay.room']), $actor);
            }

            return $reservation;
        });

        return $updated->load(['guest', 'stay.room.roomType', 'folio']);
    }

    public function cancel(User $actor, Reservation $reservation): Reservation
    {
        $this->assertOwned($actor, $reservation);
        $reservation->status = ReservationStatus::Cancelled;
        $reservation->save();
        SyncAvailabilityJob::dispatch($reservation->property_id)->afterCommit();

        return $reservation->load(['guest', 'stay.room.roomType']);
    }

    public function importExternal(User $actor, array $payload): Reservation
    {
        $property = $this->property($actor);

        try {
            return $this->writeExternal($property, $payload);
        } catch (UniqueConstraintViolationException) {
            $payload['action'] = 'modify';

            return $this->writeExternal($property, $payload);
        }
    }

    private function writeExternal(Property $property, array $payload): Reservation
    {
        $reservation = DB::transaction(function () use ($property, $payload) {
            $existing = Reservation::query()
                ->where('property_id', $property->id)
                ->where('source', ReservationSource::BookingCom)
                ->where('external_id', $payload['external_id'])
                ->lockForUpdate()
                ->first();

            if (($payload['action'] ?? 'create') === 'cancel') {
                if ($existing === null) {
                    $this->throwValidationError('external_id', 'messages.reservations.not_found');
                }
                $existing->status = ReservationStatus::Cancelled;
                $existing->save();

                return $existing;
            }

            $checkIn = $payload['check_in'] ?? $existing?->check_in->toDateString();
            $checkOut = $payload['check_out'] ?? $existing?->check_out->toDateString();
            if ($checkIn === null || $checkOut === null || $checkOut <= $checkIn) {
                $this->throwValidationError('check_in', 'messages.reservations.invalid_dates');
            }

            if ($existing !== null) {
                $existing->load('stay.room');
                $room = $this->lockRoom($property, $existing->stay->room->number);
                $this->guardFree($room, $checkIn, $checkOut, $existing->id);
                $existing->guest->fill([
                    'name' => $payload['guest_name'] ?? $existing->guest->name,
                    'phone' => array_key_exists('phone', $payload) ? $this->blankToNull($payload['phone']) : $existing->guest->phone,
                ])->save();
                $existing->fill([
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'adults' => $payload['persons'] ?? $existing->adults,
                    'total_cents' => $payload['total_cents'] ?? $existing->total_cents,
                    'status' => $existing->status === ReservationStatus::Cancelled
                        ? ReservationStatus::Confirmed
                        : $existing->status,
                ])->save();
                $this->writeStay($existing, $room, $checkIn, $checkOut);
                $folio = $this->folios->forReservation($existing->fresh(['stay.room']));
                if ($folio->isOpen()) {
                    $this->folios->syncRoomCharges($folio, $existing->fresh(['stay.room']));
                }

                return $existing;
            }

            $type = RoomType::query()
                ->where('property_id', $property->id)
                ->where('code', $payload['room_type_code'] ?? 'double')
                ->first();
            if ($type === null) {
                $this->throwValidationError('room_type_code', 'messages.reservations.room_type_not_found');
            }

            $room = $this->availability->firstFreeRoom($property, $type, $checkIn, $checkOut);
            if ($room === null) {
                $this->throwValidationError('roomId', 'messages.reservations.room_taken');
            }

            $guest = $this->findOrCreateGuest($property, $payload['guest_name'] ?? 'Klient', $payload['phone'] ?? null);
            $created = Reservation::query()->create([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'source' => ReservationSource::BookingCom,
                'status' => ReservationStatus::Confirmed,
                'external_id' => $payload['external_id'],
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => $payload['persons'] ?? 2,
                'children' => 0,
                'total_cents' => $payload['total_cents'] ?? 0,
                'paid_cents' => 0,
                'gross_cents' => $payload['gross_cents'] ?? null,
                'commission_cents' => $payload['commission_cents'] ?? null,
                'net_cents' => $payload['net_cents'] ?? null,
                'currency' => $property->currency,
                'expected_arrival' => '14:00',
            ]);
            $this->writeStay($created, $room, $checkIn, $checkOut);
            $this->folios->openFor($created);

            return $created;
        });

        SyncAvailabilityJob::dispatch($property->id)->afterCommit();

        return $reservation->load(['guest', 'stay.room.roomType', 'folio']);
    }

    private function createUnassigned(Property $property, array $data, string $checkIn, string $checkOut): Reservation
    {
        $reservation = DB::transaction(function () use ($property, $data, $checkIn, $checkOut) {
            $type = RoomType::query()
                ->where('property_id', $property->id)
                ->whereKey($data['roomTypeId'])
                ->lockForUpdate()
                ->first();
            if ($type === null) {
                $this->throwValidationError('roomTypeId', 'messages.reservations.room_type_not_found');
            }
            Room::query()->where('room_type_id', $type->id)->lockForUpdate()->get();
            $this->guardOccupancy($type, (int) $data['persons'], (int) ($data['children'] ?? 0));
            $cursor = $checkIn;
            while ($cursor < $checkOut) {
                if ($this->availability->availableUnits($type, $cursor) < 1) {
                    $this->throwValidationError('roomTypeId', 'messages.reservations.room_taken');
                }
                $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
            }

            $guest = $this->resolveGuest($property, $data);
            $created = Reservation::query()->create([
                'property_id' => $property->id,
                'guest_id' => $guest->id,
                'source' => ReservationSource::fromDesk($data['source'] ?? 'DIREKT'),
                'status' => ReservationStatus::Confirmed,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => $data['persons'],
                'children' => (int) ($data['children'] ?? 0),
                'total_cents' => $data['totalCents'] ?? 0,
                'paid_cents' => 0,
                'currency' => $property->currency,
                'notes' => $this->blankToNull($data['notes'] ?? null),
                'expected_arrival' => $data['expectedArrival'] ?? '14:00',
            ]);
            ReservationRoom::query()->create([
                'reservation_id' => $created->id,
                'room_type_id' => $type->id,
                'room_id' => null,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ]);
            $this->folios->openFor($created);

            return $created;
        });

        SyncAvailabilityJob::dispatch($property->id)->afterCommit();

        return $reservation->load(['guest', 'stay.room.roomType', 'folio']);
    }

    private function guardOccupancy(RoomType $type, int $adults, int $children): void
    {
        if (! $type->fits($adults, $children)) {
            $this->throwValidationError('persons', 'messages.reservations.occupancy_exceeded');
        }
    }

    private function roomEvent(Room $room, User $actor, string $action, array $meta = []): void
    {
        RoomEvent::query()->create([
            'room_id' => $room->id,
            'user_id' => $actor->id,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    private function property(User $actor): Property
    {
        if ($actor->property === null) {
            $this->throwAuthorizationError();
        }

        return $actor->property;
    }

    private function assertOwned(User $actor, Reservation $reservation): void
    {
        if ($reservation->property_id !== $actor->property_id) {
            $this->throwAuthorizationError();
        }
    }

    private function guardFree(Room $room, string $checkIn, string $checkOut, ?int $ignoreId = null): void
    {
        if (! $this->availability->roomIsFree($room, $checkIn, $checkOut, $ignoreId)) {
            $this->throwValidationError('roomId', 'messages.reservations.room_taken');
        }
    }

    private function lockRoom(Property $property, string $number): Room
    {
        $room = Room::query()
            ->where('property_id', $property->id)
            ->where('number', $number)
            ->lockForUpdate()
            ->first();

        if ($room === null) {
            $this->throwValidationError('roomId', 'messages.reservations.room_not_found');
        }

        return $room;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveGuest(Property $property, array $data, ?Guest $current = null): Guest
    {
        $register = filter_var($data['registerCustomer'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $prefix = $this->normalizePrefix(isset($data['phonePrefix']) ? (string) $data['phonePrefix'] : null);
        $rawPhone = isset($data['phone']) ? (string) $data['phone'] : null;
        $national = $this->nationalPhone($rawPhone);
        $storedPhone = $prefix === null ? $this->blankToNull($rawPhone) : $national;
        $name = trim((string) $data['guestName']);

        if ($register) {
            if ($prefix === null || $national === null) {
                $this->throwValidationError('phone', 'messages.reservations.phone_required_to_register');
            }

            $existing = Guest::query()
                ->where('property_id', $property->id)
                ->where('phone_prefix', $prefix)
                ->where('phone', $national)
                ->first();

            if ($existing !== null) {
                $existing->name = $name;
                $existing->save();

                return $existing;
            }
        }

        if ($current !== null) {
            $current->fill([
                'name' => $name,
                'phone_prefix' => $prefix,
                'phone' => $storedPhone,
            ])->save();

            return $current;
        }

        return Guest::query()->create([
            'property_id' => $property->id,
            'name' => $name,
            'phone_prefix' => $prefix,
            'phone' => $storedPhone,
        ]);
    }

    private function findOrCreateGuest(Property $property, string $name, ?string $phone): Guest
    {
        return $this->resolveGuest($property, [
            'guestName' => $name,
            'phone' => $phone,
            'registerCustomer' => false,
        ]);
    }

    private function normalizePrefix(?string $prefix): ?string
    {
        $prefix = $this->blankToNull($prefix);
        if ($prefix === null) {
            return null;
        }

        if (! preg_match('/^\+\d{1,4}$/', $prefix)) {
            $this->throwValidationError('phonePrefix', 'messages.reservations.phone_prefix_invalid');
        }

        return $prefix;
    }

    private function nationalPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    private function writeStay(Reservation $reservation, Room $room, string $checkIn, string $checkOut): void
    {
        ReservationRoom::query()->updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'room_type_id' => $room->room_type_id,
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ],
        );
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
