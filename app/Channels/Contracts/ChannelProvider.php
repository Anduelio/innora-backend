<?php

namespace App\Channels\Contracts;

use App\Models\Property;

interface ChannelProvider
{
    public function pushAvailability(Property $property): void;
}
