<?php

namespace App\Http\Resources;

use App\Models\FolioItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FolioItem */
class FolioItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoryCode' => $this->category_code,
            'description' => $this->description,
            'roomNumber' => $this->room_number,
            'serviceDate' => $this->service_date->toDateString(),
            'quantity' => $this->quantity,
            'unitCents' => $this->unit_cents,
            'amountCents' => $this->amount_cents,
            'isRoomCharge' => $this->is_room_charge,
            'voidedAt' => $this->voided_at?->toIso8601String(),
            'voidReason' => $this->void_reason,
        ];
    }
}
