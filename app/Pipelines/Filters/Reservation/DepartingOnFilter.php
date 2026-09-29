<?php

namespace App\Pipelines\Filters\Reservation;

use App\Pipelines\Filters\Filter;

class DepartingOnFilter extends Filter
{
    public string $attribute = 'departing_on';

    protected function applyFilter($builder)
    {
        return $builder->whereDate('check_out', $this->parameters[$this->attribute]);
    }
}
