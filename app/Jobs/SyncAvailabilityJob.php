<?php

namespace App\Jobs;

use App\Channels\Contracts\ChannelProvider;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAvailabilityJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $propertyId) {}

    public function handle(ChannelProvider $provider): void
    {
        $property = Property::query()->findOrFail($this->propertyId);
        $provider->pushAvailability($property);
    }
}
