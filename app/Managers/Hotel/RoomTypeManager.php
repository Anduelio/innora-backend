<?php

namespace App\Managers\Hotel;

use App\Access\Visibility\PropertyVisibility;
use App\Models\Amenity;
use App\Models\BedType;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\User;
use App\Traits\HasMessages;
use App\Traits\PipelineHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RoomTypeManager
{
    use HasMessages, PipelineHandler;

    public function __construct(private readonly PropertyVisibility $visibility) {}

    public function list(User $actor, array $filters): Builder
    {
        $query = $this->visibility->list(
            RoomType::query()->with(['bedTypes', 'amenities', 'images'])->orderBy('sort_order')->orderBy('name'),
            $actor,
        );

        return $this->applyPipeline($query, $filters, []);
    }

    public function create(User $actor, array $data): RoomType
    {
        $property = $this->property($actor);
        $this->guardOccupancy($data);

        return DB::transaction(function () use ($property, $data) {
            $type = RoomType::query()->create($this->attributes($property, $data));
            $this->syncBeds($type, $data['beds'] ?? []);
            $this->syncAmenities($type, $data['amenityCodes'] ?? []);

            return $type->load(['bedTypes', 'amenities', 'images']);
        });
    }

    public function update(User $actor, RoomType $type, array $data): RoomType
    {
        if ($type->property_id !== $actor->property_id) {
            $this->throwAuthorizationError();
        }
        $merged = array_merge([
            'maxAdults' => $type->max_adults,
            'maxChildren' => $type->max_children,
            'maxOccupancy' => $type->max_occupancy,
        ], $data);
        $this->guardOccupancy($merged);

        return DB::transaction(function () use ($type, $data) {
            $fill = [];
            foreach ([
                'name' => 'name',
                'code' => 'code',
                'baseOccupancy' => 'base_occupancy',
                'maxAdults' => 'max_adults',
                'maxChildren' => 'max_children',
                'maxOccupancy' => 'max_occupancy',
                'basePriceCents' => 'base_price_cents',
                'sizeM2' => 'size_m2',
                'isActive' => 'is_active',
                'sortOrder' => 'sort_order',
            ] as $from => $column) {
                if (array_key_exists($from, $data)) {
                    $fill[$column] = $data[$from];
                }
            }
            if (array_key_exists('description', $data)) {
                $fill['description'] = $this->blank($data['description']);
            }
            if (array_key_exists('shortDescription', $data)) {
                $fill['short_description'] = $this->blank($data['shortDescription']);
            }
            if (array_key_exists('maxOccupancy', $data)) {
                $fill['capacity'] = $data['maxOccupancy'];
            }
            $type->fill($fill)->save();

            if (array_key_exists('beds', $data)) {
                $this->syncBeds($type, $data['beds']);
            }
            if (array_key_exists('amenityCodes', $data)) {
                $this->syncAmenities($type, $data['amenityCodes']);
            }

            return $type->load(['bedTypes', 'amenities', 'images']);
        });
    }

    private function attributes(Property $property, array $data): array
    {
        return [
            'property_id' => $property->id,
            'code' => $data['code'],
            'ui_type' => $data['uiType'] ?? 'Dyshe',
            'name' => $data['name'],
            'description' => $this->blank($data['description'] ?? null),
            'short_description' => $this->blank($data['shortDescription'] ?? null),
            'capacity' => $data['maxOccupancy'],
            'base_occupancy' => $data['baseOccupancy'] ?? $data['maxAdults'],
            'max_adults' => $data['maxAdults'],
            'max_children' => $data['maxChildren'],
            'max_occupancy' => $data['maxOccupancy'],
            'base_price_cents' => $data['basePriceCents'] ?? 0,
            'size_m2' => $data['sizeM2'] ?? null,
            'is_active' => $data['isActive'] ?? true,
            'sort_order' => $data['sortOrder'] ?? 0,
        ];
    }

    private function guardOccupancy(array $data): void
    {
        $adults = (int) ($data['maxAdults'] ?? 0);
        $children = (int) ($data['maxChildren'] ?? 0);
        $total = (int) ($data['maxOccupancy'] ?? 0);
        if ($adults < 1 || $total < $adults || $total > $adults + $children) {
            $this->throwValidationError('maxOccupancy', 'messages.rooms.occupancy');
        }
    }

    private function syncBeds(RoomType $type, array $beds): void
    {
        $sync = [];
        foreach ($beds as $bed) {
            $row = BedType::query()->where('code', $bed['code'])->first();
            if ($row === null || (int) $bed['quantity'] < 1) {
                $this->throwValidationError('beds', 'messages.rooms.bed');
            }
            $sync[$row->id] = ['quantity' => (int) $bed['quantity']];
        }
        $type->bedTypes()->sync($sync);
    }

    private function syncAmenities(RoomType $type, array $codes): void
    {
        $ids = Amenity::query()->whereIn('code', $codes)->pluck('id');
        if ($ids->count() !== count(array_unique($codes))) {
            $this->throwValidationError('amenityCodes', 'messages.rooms.amenity');
        }
        $type->amenities()->sync($ids);
    }

    private function property(User $actor): Property
    {
        if ($actor->property === null) {
            $this->throwAuthorizationError();
        }

        return $actor->property;
    }

    private function blank(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
