<?php

namespace App\Pipelines\Filters\Reservation;

use App\Pipelines\Filters\Filter;

class OverlapFromFilter extends Filter
{
    public string $attribute = 'from';

    protected function applyFilter($builder)
    {
        return $builder->whereDate('check_out', '>', $this->parameters[$this->attribute]);
    }
}
