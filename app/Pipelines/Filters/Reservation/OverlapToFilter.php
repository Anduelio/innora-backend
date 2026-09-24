<?php

namespace App\Pipelines\Filters\Reservation;

use App\Pipelines\Filters\Filter;

class OverlapToFilter extends Filter
{
    public string $attribute = 'to';

    protected function applyFilter($builder)
    {
        return $builder->whereDate('check_in', '<', $this->parameters[$this->attribute]);
    }
}
