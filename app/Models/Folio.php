<?php

namespace App\Models;

use App\Enums\FolioStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folio extends Model
{
    protected $fillable = [
        'property_id',
        'reservation_id',
        'number',
        'status',
        'charges_cents',
        'payments_cents',
        'balance_cents',
        'currency',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FolioStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === FolioStatus::Open;
    }
}
