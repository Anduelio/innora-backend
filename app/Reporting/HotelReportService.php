<?php

namespace App\Reporting;

use App\Enums\PermissionEnum;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Traits\HasMessages;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HotelReportService
{
    use HasMessages;

    public function assertCanView(User $actor): void
    {
        if (! $actor->canPermission(PermissionEnum::ReportsView)) {
            $this->throwAuthorizationError('messages.authz.you_are_not_allowed_to_perform_this_action');
        }
    }

    public function assertCanExport(User $actor): void
    {
        if (! $actor->canPermission(PermissionEnum::ReportsExport)) {
            $this->throwAuthorizationError('messages.authz.you_are_not_allowed_to_perform_this_action');
        }
    }

    /**
     * Posted charges by service date. Revenue is not cash collected.
     *
     * @return array{totalCents: int, roomCents: int, extraCents: int, byCategory: list<array{code: string, name: string, amountCents: int}>}
     */
    public function revenue(User $actor, string $from, string $to): array
    {
        $this->assertCanView($actor);
        $propertyId = (int) $actor->property_id;

        $rows = FolioItem::query()
            ->where('property_id', $propertyId)
            ->whereNull('voided_at')
            ->whereDate('service_date', '>=', $from)
            ->whereDate('service_date', '<=', $to)
            ->select('category_code', DB::raw('SUM(amount_cents) as amount_cents'))
            ->groupBy('category_code')
            ->orderBy('category_code')
            ->get();

        $byCategory = [];
        $total = 0;
        $room = 0;

        foreach ($rows as $row) {
            $amount = (int) $row->amount_cents;
            $total += $amount;
            if ($row->category_code === 'room') {
                $room += $amount;
            }
            $byCategory[] = [
                'code' => $row->category_code,
                'name' => $this->categoryName($row->category_code),
                'amountCents' => $amount,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'totalCents' => $total,
            'roomCents' => $room,
            'extraCents' => $total - $room,
            'byCategory' => $byCategory,
            'note' => 'Revenue is posted folio charges. It is not cash received.',
        ];
    }

    /**
     * @return array{totalCents: int, byMethod: list<array{method: string, amountCents: int}>}
     */
    public function payments(User $actor, string $from, string $to): array
    {
        $this->assertCanView($actor);
        $propertyId = (int) $actor->property_id;

        $rows = Payment::query()
            ->where('property_id', $propertyId)
            ->whereNull('voided_at')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->select('method', DB::raw('SUM(amount_cents) as amount_cents'))
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $byMethod = [];
        $total = 0;
        foreach ($rows as $row) {
            $amount = (int) $row->amount_cents;
            $total += $amount;
            $byMethod[] = [
                'method' => is_string($row->method) ? $row->method : $row->method->value,
                'amountCents' => $amount,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'totalCents' => $total,
            'byMethod' => $byMethod,
            'note' => 'Payments are money received or refunded. They are not revenue.',
        ];
    }

    /**
     * @return array{totalBalanceCents: int, count: int, rows: list<array<string, mixed>>}
     */
    public function outstanding(User $actor): array
    {
        $this->assertCanView($actor);

        $folios = Folio::query()
            ->with(['reservation.guest', 'reservation.stay.room'])
            ->where('property_id', $actor->property_id)
            ->where('status', 'open')
            ->where('balance_cents', '>', 0)
            ->orderByDesc('balance_cents')
            ->get();

        $rows = $folios->map(function (Folio $folio) {
            $reservation = $folio->reservation;

            return [
                'folioId' => $folio->id,
                'folioNumber' => $folio->number,
                'reservationId' => (string) $folio->reservation_id,
                'guestName' => $reservation?->guest?->name,
                'roomId' => $reservation?->stay?->room?->number ?? '',
                'checkOut' => $reservation?->check_out?->toDateString(),
                'chargesCents' => $folio->charges_cents,
                'paymentsCents' => $folio->payments_cents,
                'balanceCents' => $folio->balance_cents,
            ];
        })->values()->all();

        return [
            'totalBalanceCents' => (int) $folios->sum('balance_cents'),
            'count' => $folios->count(),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{totalCents: int, bySource: list<array{source: string, amountCents: int}>}
     */
    public function revenueBySource(User $actor, string $from, string $to): array
    {
        $this->assertCanView($actor);

        $rows = FolioItem::query()
            ->join('folios', 'folios.id', '=', 'folio_items.folio_id')
            ->join('reservations', 'reservations.id', '=', 'folios.reservation_id')
            ->where('folio_items.property_id', $actor->property_id)
            ->whereNull('folio_items.voided_at')
            ->whereDate('folio_items.service_date', '>=', $from)
            ->whereDate('folio_items.service_date', '<=', $to)
            ->select('reservations.source', DB::raw('SUM(folio_items.amount_cents) as amount_cents'))
            ->groupBy('reservations.source')
            ->orderBy('reservations.source')
            ->get();

        $bySource = [];
        $total = 0;
        foreach ($rows as $row) {
            $amount = (int) $row->amount_cents;
            $total += $amount;
            $bySource[] = [
                'source' => $row->source,
                'amountCents' => $amount,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'totalCents' => $total,
            'bySource' => $bySource,
        ];
    }

    /**
     * @return array{totalGrossCents: int, totalCommissionCents: int, totalNetCents: int, rows: list<array<string, mixed>>}
     */
    public function channel(User $actor, string $from, string $to): array
    {
        $this->assertCanView($actor);

        $rows = Reservation::query()
            ->where('property_id', $actor->property_id)
            ->where('source', 'booking_com')
            ->whereDate('check_in', '>=', $from)
            ->whereDate('check_in', '<=', $to)
            ->whereNotNull('commission_cents')
            ->get(['id', 'external_id', 'gross_cents', 'commission_cents', 'net_cents', 'total_cents']);

        return [
            'from' => $from,
            'to' => $to,
            'totalGrossCents' => (int) $rows->sum(fn ($r) => $r->gross_cents ?? $r->total_cents),
            'totalCommissionCents' => (int) $rows->sum('commission_cents'),
            'totalNetCents' => (int) $rows->sum(fn ($r) => $r->net_cents ?? (($r->gross_cents ?? $r->total_cents) - ($r->commission_cents ?? 0))),
            'rows' => $rows->map(fn (Reservation $r) => [
                'reservationId' => (string) $r->id,
                'externalId' => $r->external_id,
                'grossCents' => $r->gross_cents,
                'commissionCents' => $r->commission_cents,
                'netCents' => $r->net_cents,
            ])->values()->all(),
            'note' => 'Commission figures appear only when the channel supplied them.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function daily(User $actor, string $date): array
    {
        $this->assertCanView($actor);
        $propertyId = (int) $actor->property_id;
        $day = Carbon::parse($date)->toDateString();

        $arrivals = Reservation::query()
            ->where('property_id', $propertyId)
            ->whereDate('check_in', $day)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->count();

        $departures = Reservation::query()
            ->where('property_id', $propertyId)
            ->whereDate('check_out', $day)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->count();

        $inHouse = Reservation::query()
            ->where('property_id', $propertyId)
            ->where('status', 'checked_in')
            ->count();

        $rooms = Room::query()->where('property_id', $propertyId)->where('is_active', true)->get();
        $occupied = Reservation::query()
            ->where('property_id', $propertyId)
            ->whereNotIn('status', ['cancelled', 'no_show', 'checked_out'])
            ->where('check_in', '<=', $day)
            ->where('check_out', '>', $day)
            ->whereHas('stay', fn ($q) => $q->whereNotNull('room_id'))
            ->count();

        $dirty = $rooms->filter(fn (Room $room) => $room->operational_status === \App\Enums\OperationalStatus::Dirty)->count();
        $ooo = $rooms->filter(fn (Room $room) => $room->operational_status === \App\Enums\OperationalStatus::OutOfOrder)->count();

        $revenue = $this->revenue($actor, $day, $day);
        $payments = $this->payments($actor, $day, $day);
        $outstanding = $this->outstanding($actor);

        return [
            'date' => $day,
            'operations' => [
                'arrivals' => $arrivals,
                'departures' => $departures,
                'inHouse' => $inHouse,
            ],
            'rooms' => [
                'occupied' => $occupied,
                'free' => max(0, $rooms->count() - $occupied - $ooo),
                'dirty' => $dirty,
                'outOfOrder' => $ooo,
            ],
            'finance' => [
                'revenueCents' => $revenue['totalCents'],
                'paymentsCents' => $payments['totalCents'],
                'outstandingCents' => $outstanding['totalBalanceCents'],
            ],
            'paymentsByMethod' => $payments['byMethod'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function occupancy(User $actor, string $from, string $to): array
    {
        $this->assertCanView($actor);
        $propertyId = (int) $actor->property_id;
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = max(1, (int) $start->diffInDays($end) + 1);

        $rooms = Room::query()
            ->where('property_id', $propertyId)
            ->where('is_active', true)
            ->get();

        $available = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $day = $d->toDateString();
            foreach ($rooms as $room) {
                if ($room->operational_status?->value === 'out_of_order') {
                    continue;
                }
                $blocked = RoomBlock::query()
                    ->where('room_id', $room->id)
                    ->where('starts_on', '<=', $day)
                    ->where('ends_on', '>', $day)
                    ->exists();
                if (! $blocked) {
                    $available++;
                }
            }
        }

        $soldItems = FolioItem::query()
            ->where('property_id', $propertyId)
            ->where('is_room_charge', true)
            ->whereNull('voided_at')
            ->whereDate('service_date', '>=', $from)
            ->whereDate('service_date', '<=', $to)
            ->whereHas('folio.reservation', fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show']))
            ->get(['amount_cents']);

        $roomsSold = $soldItems->count();
        $roomRevenue = (int) $soldItems->sum('amount_cents');
        $paidSold = $soldItems->filter(fn ($item) => $item->amount_cents > 0);
        $adrNights = $paidSold->count();
        $adrRevenue = (int) $paidSold->sum('amount_cents');

        $cancellations = Reservation::query()
            ->where('property_id', $propertyId)
            ->where('status', 'cancelled')
            ->whereDate('updated_at', '>=', $from)
            ->whereDate('updated_at', '<=', $to)
            ->count();

        $noShows = Reservation::query()
            ->where('property_id', $propertyId)
            ->where('status', 'no_show')
            ->whereDate('check_in', '>=', $from)
            ->whereDate('check_in', '<=', $to)
            ->count();

        return [
            'from' => $from,
            'to' => $to,
            'availableRoomNights' => $available,
            'roomsSold' => $roomsSold,
            'roomRevenueCents' => $roomRevenue,
            'occupancy' => $available > 0 ? round($roomsSold / $available, 4) : 0,
            'adrCents' => $adrNights > 0 ? (int) round($adrRevenue / $adrNights) : 0,
            'revparCents' => $available > 0 ? (int) round($roomRevenue / $available) : 0,
            'cancellations' => $cancellations,
            'noShows' => $noShows,
            'formulas' => [
                'occupancy' => 'rooms sold / available room nights',
                'adr' => 'room revenue / sold nights excluding complimentary',
                'revpar' => 'room revenue / available room nights',
            ],
        ];
    }

    public function dashboardDue(User $actor): array
    {
        if (! $actor->canPermission(PermissionEnum::FoliosView)
            && ! $actor->canPermission(PermissionEnum::ReportsView)) {
            $this->throwAuthorizationError('messages.authz.you_are_not_allowed_to_perform_this_action');
        }

        $folios = Folio::query()
            ->where('property_id', $actor->property_id)
            ->where('status', 'open')
            ->where('balance_cents', '>', 0)
            ->get(['balance_cents']);

        return [
            'outstandingCents' => (int) $folios->sum('balance_cents'),
            'outstandingCount' => $folios->count(),
        ];
    }

    public function toHtml(array $report, string $type): string
    {
        $title = match ($type) {
            'revenue' => 'Revenue report',
            'payments' => 'Payments report',
            'outstanding' => 'Outstanding balances',
            'daily' => 'Daily hotel report',
            default => 'Report',
        };

        $rows = '';
        if ($type === 'revenue') {
            foreach ($report['byCategory'] as $row) {
                $rows .= '<tr><td>'.e($row['name']).'</td><td>'.$row['amountCents'].'</td></tr>';
            }
        } elseif ($type === 'payments') {
            foreach ($report['byMethod'] as $row) {
                $rows .= '<tr><td>'.e($row['method']).'</td><td>'.$row['amountCents'].'</td></tr>';
            }
        } elseif ($type === 'outstanding') {
            foreach ($report['rows'] as $row) {
                $rows .= '<tr><td>'.e($row['guestName']).'</td><td>'.$row['balanceCents'].'</td></tr>';
            }
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.$title.'</title>'
            .'<style>body{font-family:sans-serif;padding:24px}table{border-collapse:collapse;width:100%}'
            .'td,th{border:1px solid #ccc;padding:8px;text-align:left}</style></head><body>'
            .'<h1>'.$title.'</h1><p>'.e($report['note'] ?? '').'</p>'
            .'<table><thead><tr><th>Label</th><th>Cents</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<script>window.print()</script></body></html>';
    }

    public function toCsv(array $report, string $type): string
    {
        $lines = [];
        if ($type === 'revenue') {
            $lines[] = 'category,amount_cents';
            foreach ($report['byCategory'] as $row) {
                $lines[] = $row['code'].','.$row['amountCents'];
            }
        } elseif ($type === 'payments') {
            $lines[] = 'method,amount_cents';
            foreach ($report['byMethod'] as $row) {
                $lines[] = $row['method'].','.$row['amountCents'];
            }
        } elseif ($type === 'outstanding') {
            $lines[] = 'guest,reservation,checkout,total,paid,balance';
            foreach ($report['rows'] as $row) {
                $lines[] = '"'.$row['guestName'].'",'.$row['reservationId'].','.$row['checkOut'].','.$row['chargesCents'].','.$row['paymentsCents'].','.$row['balanceCents'];
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function categoryName(string $code): string
    {
        return match ($code) {
            'room' => 'Akomodim',
            'food_beverage' => 'Ushqim dhe pije',
            'minibar' => 'Minibar',
            'parking' => 'Parking',
            'transport' => 'Transport',
            'laundry' => 'Lavanderi',
            'late_checkout' => 'Dalje e vonuar',
            'early_checkin' => 'Hyrje e hershme',
            'extra_bed' => 'Krevat shtesë',
            default => 'Të tjera',
        };
    }
}
