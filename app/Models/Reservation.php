<?php

namespace App\Models;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    protected $fillable = [
        'property_id',
        'guest_id',
        'source',
        'status',
        'external_id',
        'check_in',
        'check_out',
        'adults',
        'children',
        'total_cents',
        'paid_cents',
        'gross_cents',
        'commission_cents',
        'net_cents',
        'currency',
        'notes',
        'expected_arrival',
        'expected_departure',
        'checked_in_at',
        'checked_out_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => ReservationSource::class,
            'status' => ReservationStatus::class,
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function stay(): HasOne
    {
        return $this->hasOne(ReservationRoom::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function folio(): HasOne
    {
        return $this->hasOne(Folio::class);
    }
}
