<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioItem extends Model
{
    protected $fillable = [
        'property_id',
        'folio_id',
        'charge_category_id',
        'category_code',
        'description',
        'room_number',
        'service_date',
        'quantity',
        'unit_cents',
        'amount_cents',
        'is_room_charge',
        'posted_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date:Y-m-d',
            'is_room_charge' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChargeCategory::class, 'charge_category_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
