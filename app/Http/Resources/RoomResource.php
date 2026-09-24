<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Room */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->number,
            'type' => $this->roomType->ui_type,
            'typeName' => $this->roomType->name,
            'roomTypeId' => $this->room_type_id,
            'capacity' => $this->roomType->max_occupancy,
            'floor' => $this->floor,
            'building' => $this->building,
            'notes' => $this->notes,
            'isActive' => $this->is_active,
            'operationalStatus' => $this->operational_status->value,
            'sizeM2' => $this->roomType->size_m2,
            'basePriceCents' => $this->roomType->base_price_cents,
            'beds' => $this->roomType->relationLoaded('bedTypes')
                ? $this->roomType->bedTypes->map(fn ($bed) => [
                    'code' => $bed->code,
                    'quantity' => (int) $bed->pivot->quantity,
                ])->values()
                : [],
            'amenities' => AmenityResource::collection(
                $this->roomType->relationLoaded('amenities') ? $this->roomType->amenities : [],
            ),
            'blocks' => RoomBlockResource::collection($this->whenLoaded('blocks')),
        ];
    }
}
