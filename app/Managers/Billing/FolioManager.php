<?php

namespace App\Managers\Billing;

use App\Enums\ChargeCategoryCode;
use App\Enums\FolioStatus;
use App\Enums\PermissionEnum;
use App\Models\ChargeCategory;
use App\Models\FinancialEvent;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Models\User;
use App\Traits\HasMessages;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FolioManager
{
    use HasMessages;

    public function openFor(Reservation $reservation, ?User $actor = null): Folio
    {
        $existing = Folio::query()->where('reservation_id', $reservation->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($reservation, $actor) {
            $folio = Folio::query()->create([
                'property_id' => $reservation->property_id,
                'reservation_id' => $reservation->id,
                'number' => 'FOL-'.$reservation->id,
                'status' => FolioStatus::Open,
                'charges_cents' => 0,
                'payments_cents' => 0,
                'balance_cents' => 0,
                'currency' => $reservation->currency,
            ]);

            $this->recordEvent($folio, $actor, 'folio.opened', Folio::class, $folio->id);
            $this->syncRoomCharges($folio, $reservation, $actor);

            return $folio->refresh();
        });
    }

    public function forReservation(Reservation $reservation): Folio
    {
        $folio = Folio::query()->where('reservation_id', $reservation->id)->first();

        return $folio ?? $this->openFor($reservation);
    }

    public function show(User $actor, Folio $folio): Folio
    {
        $this->assertPermission($actor, PermissionEnum::FoliosView);
        $this->assertOwned($actor, $folio);

        return $folio->load([
            'items' => fn ($query) => $query->orderBy('service_date')->orderBy('id'),
            'payments' => fn ($query) => $query->orderBy('paid_at')->orderBy('id'),
            'reservation.guest',
            'reservation.stay.room',
        ]);
    }

    public function findForActor(User $actor, int $folioId): Folio
    {
        $folio = Folio::query()->find($folioId);
        if ($folio === null || (int) $folio->property_id !== (int) $actor->property_id) {
            $this->throwValidationError('folio', 'messages.folios.not_found');
        }

        return $folio;
    }

    public function close(User $actor, Folio $folio, bool $allowOutstanding = false): Folio
    {
        $this->assertPermission($actor, PermissionEnum::FoliosClose);
        $this->assertOwned($actor, $folio);
        $this->assertOpen($folio);
        $this->recalculate($folio);

        if ($folio->balance_cents > 0) {
            if (! $allowOutstanding) {
                $this->throwValidationError('balance', 'messages.folios.balance_must_be_zero');
            }
            $this->assertPermission($actor, PermissionEnum::FoliosCheckoutOutstanding);
        }

        $folio->status = FolioStatus::Closed;
        $folio->closed_at = now();
        $folio->closed_by = $actor->id;
        $folio->save();

        $this->recordEvent($folio, $actor, 'folio.closed', Folio::class, $folio->id, null, [
            'balance_cents' => $folio->balance_cents,
        ]);

        return $folio->refresh();
    }

    public function reopen(User $actor, Folio $folio): Folio
    {
        $this->assertPermission($actor, PermissionEnum::FoliosReopen);
        $this->assertOwned($actor, $folio);

        if ($folio->status !== FolioStatus::Closed) {
            $this->throwValidationError('status', 'messages.folios.not_closed');
        }

        $folio->status = FolioStatus::Open;
        $folio->closed_at = null;
        $folio->closed_by = null;
        $folio->save();

        $this->recordEvent($folio, $actor, 'folio.reopened', Folio::class, $folio->id);

        return $folio->refresh();
    }

    public function syncRoomCharges(Folio $folio, Reservation $reservation, ?User $actor = null): void
    {
        if (! $folio->isOpen()) {
            return;
        }

        $this->ensureCategories($folio->property_id);

        $category = ChargeCategory::query()
            ->where('property_id', $folio->property_id)
            ->where('code', ChargeCategoryCode::Room->value)
            ->firstOrFail();

        $reservation->loadMissing('stay.room');
        $roomNumber = $reservation->stay?->room?->number;

        FolioItem::query()
            ->where('folio_id', $folio->id)
            ->where('is_room_charge', true)
            ->whereNull('voided_at')
            ->get()
            ->each(function (FolioItem $item) use ($actor, $folio) {
                $item->voided_at = now();
                $item->voided_by = $actor?->id;
                $item->void_reason = 'Room charges rebuilt for stay change';
                $item->save();
                $this->recordEvent($folio, $actor, 'charge.voided', FolioItem::class, $item->id, $item->void_reason);
            });

        foreach ($this->nightlyAmounts(
            $reservation->check_in->toDateString(),
            $reservation->check_out->toDateString(),
            (int) $reservation->total_cents,
        ) as $night) {
            $description = $roomNumber
                ? 'Akomodim — Dhoma '.$roomNumber
                : 'Akomodim';

            $item = FolioItem::query()->create([
                'property_id' => $folio->property_id,
                'folio_id' => $folio->id,
                'charge_category_id' => $category->id,
                'category_code' => $category->code,
                'description' => $description,
                'room_number' => $roomNumber,
                'service_date' => $night['date'],
                'quantity' => 1,
                'unit_cents' => $night['amount'],
                'amount_cents' => $night['amount'],
                'is_room_charge' => true,
                'posted_by' => $actor?->id,
            ]);

            $this->recordEvent($folio, $actor, 'charge.created', FolioItem::class, $item->id);
        }

        $this->recalculate($folio);
    }

    public function recalculate(Folio $folio): Folio
    {
        $charges = (int) FolioItem::query()
            ->where('folio_id', $folio->id)
            ->whereNull('voided_at')
            ->sum('amount_cents');

        $payments = (int) DB::table('payments')
            ->where('folio_id', $folio->id)
            ->whereNull('voided_at')
            ->sum('amount_cents');

        $folio->charges_cents = $charges;
        $folio->payments_cents = $payments;
        $folio->balance_cents = $charges - $payments;
        $folio->save();

        $reservation = Reservation::query()->find($folio->reservation_id);
        if ($reservation !== null) {
            $reservation->paid_cents = max(0, $payments);
            $reservation->save();
        }

        return $folio;
    }

    public function assertOpen(Folio $folio): void
    {
        if (! $folio->isOpen()) {
            $this->throwValidationError('folio', 'messages.folios.closed');
        }
    }

    public function assertOwned(User $actor, Folio $folio): void
    {
        if ((int) $folio->property_id !== (int) $actor->property_id) {
            $this->throwValidationError('folio', 'messages.folios.not_found');
        }
    }

    public function assertPermission(User $actor, PermissionEnum $permission): void
    {
        if (! $actor->canPermission($permission)) {
            $this->throwAuthorizationError('messages.authz.you_are_not_allowed_to_perform_this_action');
        }
    }

    public function ensureCategories(int $propertyId): void
    {
        foreach (ChargeCategoryCode::cases() as $code) {
            ChargeCategory::query()->firstOrCreate(
                [
                    'property_id' => $propertyId,
                    'code' => $code->value,
                ],
                [
                    'name' => $code->label(),
                    'is_room' => $code->isRoom(),
                    'is_active' => true,
                    'sort_order' => $code->sortOrder(),
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordEvent(
        Folio $folio,
        ?User $actor,
        string $action,
        string $subjectType,
        int $subjectId,
        ?string $reason = null,
        ?array $meta = null,
    ): void {
        FinancialEvent::query()->create([
            'property_id' => $folio->property_id,
            'folio_id' => $folio->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'actor_id' => $actor?->id,
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }

    /**
     * @return list<array{date: string, amount: int}>
     */
    public function nightlyAmounts(string $checkIn, string $checkOut, int $totalCents): array
    {
        $start = Carbon::parse($checkIn)->startOfDay();
        $end = Carbon::parse($checkOut)->startOfDay();
        $nights = max(1, (int) $start->diffInDays($end));
        $base = intdiv($totalCents, $nights);
        $remainder = $totalCents - ($base * $nights);
        $rows = [];

        for ($i = 0; $i < $nights; $i++) {
            $rows[] = [
                'date' => $start->copy()->addDays($i)->toDateString(),
                'amount' => $base + ($i === $nights - 1 ? $remainder : 0),
            ];
        }

        return $rows;
    }
}
