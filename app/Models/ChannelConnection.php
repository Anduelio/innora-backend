<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelConnection extends Model
{
    protected $fillable = [
        'property_id',
        'provider',
        'provider_code',
        'status',
        'is_active',
        'config_encrypted',
    ];

    protected $hidden = [
        'config_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config_encrypted' => 'encrypted:array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
