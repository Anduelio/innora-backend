<?php

namespace App\Managers\Hotel;

use App\Models\Property;
use App\Models\User;
use App\Traits\HasMessages;

class HotelSettingsManager
{
    use HasMessages;

    public function show(User $actor): Property
    {
        if ($actor->property === null) {
            $this->throwAuthorizationError();
        }

        return $actor->property;
    }

    public function update(User $actor, array $data): Property
    {
        $property = $this->show($actor);
        $property->fill([
            'default_check_in_time' => $data['checkInTime'] ?? $property->default_check_in_time,
            'default_check_out_time' => $data['checkOutTime'] ?? $property->default_check_out_time,
        ])->save();

        return $property;
    }
}
