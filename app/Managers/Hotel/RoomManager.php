<?php

namespace App\Managers\Hotel;

use App\Access\Visibility\PropertyVisibility;
use App\Enums\BlockType;
use App\Enums\OperationalStatus;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomEvent;
use App\Models\RoomType;
use App\Models\User;
use App\Traits\HasMessages;
use App\Traits\PipelineHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RoomManager
{
    use HasMessages, PipelineHandler;

    public function __construct(
        private readonly PropertyVisibility $visibility,
        private readonly AvailabilityManager $availability,
    ) {}

    public function list(User $actor, array $filters): Builder
    {
        $query = $this->visibility->list(
            Room::query()->with(['roomType.amenities', 'roomType.bedTypes', 'blocks'])->orderBy('number'),
            $actor,
        );

        return $this->applyPipeline($query, $filters, []);
    }

    public function find(User $actor, string $number): Room
    {
        $room = Room::query()
            ->with(['roomType.amenities', 'roomType.bedTypes', 'blocks'])
            ->where('property_id', $actor->property_id)
            ->where('number', $number)
            ->first();

        if ($room === null) {
            $this->throwValidationError('roomId', 'messages.rooms.not_found');
        }

        return $room;
    }

    public function create(User $actor, array $data): Room
    {
        $property = $this->property($actor);

        return DB::transaction(function () use ($property, $actor, $data) {
            $type = $this->type($property, (int) $data['roomTypeId']);
            $this->guardNumber($property, $data['number']);

            $room = Room::query()->create([
                'property_id' => $property->id,
                'room_type_id' => $type->id,
                'number' => trim($data['number']),
                'capacity' => $type->max_occupancy,
                'status' => RoomStatus::Active,
                'floor' => $this->blank($data['floor'] ?? null),
                'building' => $this->blank($data['building'] ?? null),
                'notes' => $this->blank($data['notes'] ?? null),
                'is_active' => true,
                'operational_status' => OperationalStatus::from($data['operationalStatus'] ?? OperationalStatus::Ready->value),
            ]);
            $this->event($room, $actor, 'created');

            return $room->load(['roomType.amenities', 'roomType.bedTypes', 'blocks']);
        });
    }

    /**
     * @return list<Room>
     */
    public function createMany(User $actor, array $data): array
    {
        $numbers = $this->numbers($data);
        $created = [];

        DB::transaction(function () use ($actor, $data, $numbers, &$created) {
            foreach ($numbers as $number) {
                $created[] = $this->create($actor, [
                    'roomTypeId' => $data['roomTypeId'],
                    'number' => $number,
                    'floor' => $data['floor'] ?? null,
                    'building' => $data['building'] ?? null,
                    'operationalStatus' => OperationalStatus::Ready->value,
                ]);
            }
        });

        return $created;
    }

    public function update(User $actor, Room $room, array $data): Room
    {
        $this->assertOwned($actor, $room);
        $property = $this->property($actor);

        if (isset($data['number']) && $data['number'] !== $room->number) {
            $this->guardNumber($property, $data['number'], $room->id);
            $room->number = trim($data['number']);
        }
        if (isset($data['roomTypeId'])) {
            $type = $this->type($property, (int) $data['roomTypeId']);
            $room->room_type_id = $type->id;
            $room->capacity = $type->max_occupancy;
        }
        foreach (['floor', 'building', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $room->{$field} = $this->blank($data[$field]);
            }
        }
        if (array_key_exists('isActive', $data)) {
            $room->is_active = (bool) $data['isActive'];
            $room->status = $room->is_active ? RoomStatus::Active : RoomStatus::OutOfService;
        }
        $room->save();

        return $room->load(['roomType.amenities', 'roomType.bedTypes', 'blocks']);
    }

    public function changeStatus(User $actor, Room $room, string $status): Room
    {
        $this->assertOwned($actor, $room);
        $next = OperationalStatus::from($status);
        $previous = $room->operational_status;
        $room->operational_status = $next;
        if ($next === OperationalStatus::OutOfOrder) {
            $room->status = RoomStatus::OutOfService;
        } elseif ($room->is_active) {
            $room->status = RoomStatus::Active;
        }
        $room->save();
        $this->event($room, $actor, 'status', ['from' => $previous->value, 'to' => $next->value]);

        return $room->load(['roomType.amenities', 'roomType.bedTypes', 'blocks']);
    }

    public function block(User $actor, Room $room, array $data): RoomBlock
    {
        $this->assertOwned($actor, $room);
        if ($data['endsOn'] <= $data['startsOn']) {
            $this->throwValidationError('endsOn', 'messages.rooms.block_dates');
        }

        $block = RoomBlock::query()->create([
            'room_id' => $room->id,
            'starts_on' => $data['startsOn'],
            'ends_on' => $data['endsOn'],
            'type' => BlockType::from($data['type'] ?? BlockType::Maintenance->value),
            'reason' => $this->blank($data['reason'] ?? null),
            'notes' => $this->blank($data['notes'] ?? null),
            'created_by' => $actor->id,
        ]);
        $this->event($room, $actor, 'blocked', ['block_id' => $block->id]);

        return $block;
    }

    public function removeBlock(User $actor, RoomBlock $block): void
    {
        $block->load('room');
        $this->assertOwned($actor, $block->room);
        $room = $block->room;
        $block->delete();
        $this->event($room, $actor, 'unblocked');
    }

    /**
     * @return list<string>
     */
    private function numbers(array $data): array
    {
        $numbers = array_values(array_filter(array_map(
            fn ($number) => trim((string) $number),
            $data['numbers'] ?? [],
        )));

        if (isset($data['from'], $data['to']) && ctype_digit((string) $data['from']) && ctype_digit((string) $data['to'])) {
            $from = (int) $data['from'];
            $to = (int) $data['to'];
            if ($to < $from || ($to - $from) > 50) {
                $this->throwValidationError('to', 'messages.rooms.range');
            }
            for ($n = $from; $n <= $to; $n++) {
                $numbers[] = (string) $n;
            }
        }

        $numbers = array_values(array_unique($numbers));
        if ($numbers === []) {
            $this->throwValidationError('numbers', 'messages.rooms.numbers_required');
        }

        return $numbers;
    }

    private function guardNumber(Property $property, string $number, ?int $ignoreId = null): void
    {
        $taken = Room::query()
            ->where('property_id', $property->id)
            ->where('number', trim($number))
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($taken) {
            $this->throwValidationError('number', 'messages.rooms.number_taken');
        }
    }

    private function type(Property $property, int $id): RoomType
    {
        $type = RoomType::query()->where('property_id', $property->id)->whereKey($id)->first();
        if ($type === null) {
            $this->throwValidationError('roomTypeId', 'messages.reservations.room_type_not_found');
        }

        return $type;
    }

    private function property(User $actor): Property
    {
        if ($actor->property === null) {
            $this->throwAuthorizationError();
        }

        return $actor->property;
    }

    private function assertOwned(User $actor, Room $room): void
    {
        if ($room->property_id !== $actor->property_id) {
            $this->throwAuthorizationError();
        }
    }

    private function event(Room $room, User $actor, string $action, array $meta = []): void
    {
        RoomEvent::query()->create([
            'room_id' => $room->id,
            'user_id' => $actor->id,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    private function blank(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
