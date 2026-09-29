<?php

namespace App\Pipelines\Filters\Reservation;

use App\Pipelines\Filters\Filter;

class ArrivingOnFilter extends Filter
{
    public string $attribute = 'arriving_on';

    protected function applyFilter($builder)
    {
        return $builder->whereDate('check_in', $this->parameters[$this->attribute]);
    }
}
