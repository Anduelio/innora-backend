<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'property_id',
        'room_type_id',
        'number',
        'capacity',
        'status',
        'floor',
        'building',
        'internal_name',
        'notes',
        'is_active',
        'operational_status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'is_active' => 'boolean',
            'operational_status' => OperationalStatus::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RoomEvent::class);
    }

    public function sellsInventory(): bool
    {
        return $this->is_active
            && $this->status === RoomStatus::Active
            && $this->operational_status->sellsInventory();
    }
}
