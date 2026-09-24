<?php

namespace App\Http\Resources;

use App\Models\ChargeCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChargeCategory */
class ChargeCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'isRoom' => $this->is_room,
        ];
    }
}
