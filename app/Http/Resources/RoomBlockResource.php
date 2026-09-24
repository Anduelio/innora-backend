<?php

namespace App\Http\Resources;

use App\Models\RoomBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoomBlock */
class RoomBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'startsOn' => $this->starts_on->toDateString(),
            'endsOn' => $this->ends_on->toDateString(),
            'type' => $this->type->value,
            'reason' => $this->reason,
        ];
    }
}
