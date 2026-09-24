<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    protected $fillable = [
        'property_id',
        'code',
        'ui_type',
        'name',
        'description',
        'short_description',
        'capacity',
        'base_occupancy',
        'max_adults',
        'max_children',
        'max_occupancy',
        'base_price_cents',
        'size_m2',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function bedTypes(): BelongsToMany
    {
        return $this->belongsToMany(BedType::class, 'room_type_beds')->withPivot('quantity');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomTypeImage::class)->orderBy('sort_order');
    }

    public function fits(int $adults, int $children): bool
    {
        return $adults <= $this->max_adults
            && $children <= $this->max_children
            && ($adults + $children) <= $this->max_occupancy
            && $adults >= 1;
    }
}
