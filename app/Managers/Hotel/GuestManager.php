<?php

namespace App\Managers\Hotel;

use App\Access\Visibility\PropertyVisibility;
use App\Models\Guest;
use App\Models\User;
use App\Traits\PipelineHandler;
use Illuminate\Database\Eloquent\Builder;

class GuestManager
{
    use PipelineHandler;

    public function __construct(private readonly PropertyVisibility $visibility) {}

    public function list(User $actor, array $filters): Builder
    {
        $query = $this->visibility->list(
            Guest::query()
                ->has('reservations')
                ->withCount('reservations as stays_count')
                ->withMax('reservations as last_stay', 'check_in')
                ->orderByDesc('last_stay'),
            $actor,
        );

        return $this->applyPipeline($query, $filters, []);
    }
}
