<?php

namespace App\Http\Resources;

use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guest */
class GuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'phone' => $this->phone ?? '',
            'stays' => (int) ($this->stays_count ?? 0),
            'lastStay' => $this->last_stay ? substr((string) $this->last_stay, 0, 10) : null,
        ];
    }
}
