<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reservation */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'roomId' => $this->stay?->room?->number ?? '',
            'roomTypeId' => $this->stay?->room_type_id,
            'roomTypeName' => $this->stay?->roomType?->name ?? $this->stay?->room?->roomType?->name,
            'guestName' => $this->guest->name,
            'phonePrefix' => $this->guest->phone_prefix,
            'phone' => $this->guest->phone ?? '',
            'currency' => $this->currency,
            'persons' => $this->adults + $this->children,
            'checkIn' => $this->check_in->toDateString(),
            'checkOut' => $this->check_out->toDateString(),
            'status' => $this->status->uiValue(),
            'source' => $this->source->uiValue(),
            'totalCents' => $this->total_cents,
            'paidCents' => $this->folio?->payments_cents ?? $this->paid_cents,
            'chargesCents' => $this->folio?->charges_cents,
            'balanceCents' => $this->folio?->balance_cents ?? ($this->total_cents - $this->paid_cents),
            'folioId' => $this->folio?->id,
            'folioNumber' => $this->folio?->number,
            'folioStatus' => $this->folio?->status?->value,
            'notes' => $this->notes,
            'expectedArrival' => $this->expected_arrival,
            'expectedDeparture' => $this->expected_departure,
            'checkedInAt' => $this->checked_in_at?->toIso8601String(),
            'checkedOutAt' => $this->checked_out_at?->toIso8601String(),
            'externalRef' => $this->external_id,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
