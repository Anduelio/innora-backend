<?php

namespace App\Http\Resources;

use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoomType */
class RoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'uiType' => $this->ui_type,
            'description' => $this->description,
            'shortDescription' => $this->short_description,
            'baseOccupancy' => $this->base_occupancy,
            'maxAdults' => $this->max_adults,
            'maxChildren' => $this->max_children,
            'maxOccupancy' => $this->max_occupancy,
            'basePriceCents' => $this->base_price_cents,
            'sizeM2' => $this->size_m2,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'beds' => $this->whenLoaded('bedTypes', fn () => $this->bedTypes->map(fn ($bed) => [
                'code' => $bed->code,
                'quantity' => (int) $bed->pivot->quantity,
            ])->values()),
            'amenities' => AmenityResource::collection($this->whenLoaded('amenities')),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'path' => $image->path,
                'isCover' => $image->is_cover,
            ])->values()),
        ];
    }
}
