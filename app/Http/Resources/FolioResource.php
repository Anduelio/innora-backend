<?php

namespace App\Http\Resources;

use App\Models\Folio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Folio */
class FolioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reservation = $this->reservation;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'reservationId' => (string) $this->reservation_id,
            'guestName' => $reservation?->guest?->name,
            'roomId' => $reservation?->stay?->room?->number ?? '',
            'checkIn' => $reservation?->check_in?->toDateString(),
            'checkOut' => $reservation?->check_out?->toDateString(),
            'chargesCents' => $this->charges_cents,
            'paymentsCents' => $this->payments_cents,
            'balanceCents' => $this->balance_cents,
            'currency' => $this->currency,
            'items' => FolioItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
