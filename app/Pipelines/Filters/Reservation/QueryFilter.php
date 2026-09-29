<?php

namespace App\Pipelines\Filters\Reservation;

use App\Pipelines\Filters\Filter;

class QueryFilter extends Filter
{
    public string $attribute = 'q';

    protected function applyFilter($builder)
    {
        return StayText::apply($builder, (string) $this->parameters[$this->attribute]);
    }
}
