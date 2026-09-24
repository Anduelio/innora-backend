<?php

namespace App\Channels\Providers;

use App\Channels\Contracts\ChannelProvider;
use App\Models\Property;
use App\Models\SyncLog;

class NullChannelProvider implements ChannelProvider
{
    public function pushAvailability(Property $property): void
    {
        SyncLog::query()->create([
            'property_id' => $property->id,
            'direction' => 'outbound',
            'status' => 'succeeded',
            'message' => null,
        ]);
    }
}
