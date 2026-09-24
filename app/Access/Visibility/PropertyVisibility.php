<?php

namespace App\Access\Visibility;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PropertyVisibility
{
    public function list(Builder $query, User $actor): Builder
    {
        return $query->where($query->getModel()->getTable().'.property_id', $actor->property_id);
    }
}
