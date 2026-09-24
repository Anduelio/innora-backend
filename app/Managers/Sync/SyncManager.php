<?php

namespace App\Managers\Sync;

use App\Jobs\SyncAvailabilityJob;
use App\Models\SyncLog;
use App\Models\User;

class SyncManager
{
    public function status(User $actor): array
    {
        $log = SyncLog::query()->where('property_id', $actor->property_id)->latest('id')->first();

        return [
            'ok' => $log === null || $log->status === 'succeeded',
            'lastSyncAt' => ($log?->created_at ?? now())->toIso8601String(),
            'message' => $log?->status === 'failed' ? $log->message : null,
        ];
    }

    public function retry(User $actor): array
    {
        SyncAvailabilityJob::dispatchSync($actor->property_id);

        return $this->status($actor);
    }

    public function fail(User $actor): array
    {
        SyncLog::query()->create([
            'property_id' => $actor->property_id,
            'direction' => 'outbound',
            'status' => 'failed',
            'message' => __('messages.sync.connection_problem'),
        ]);

        return $this->status($actor);
    }
}
