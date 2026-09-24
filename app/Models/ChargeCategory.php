<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeCategory extends Model
{
    protected $fillable = [
        'property_id',
        'code',
        'name',
        'is_room',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_room' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
