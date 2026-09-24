<?php

namespace App\Http\Resources;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Property */
class HotelSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'city' => $this->city,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'checkInTime' => $this->default_check_in_time,
            'checkOutTime' => $this->default_check_out_time,
        ];
    }
}
