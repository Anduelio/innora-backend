<?php

namespace App\Pipelines\Filters\Reservation;

use App\Pipelines\Filters\Filter;

class SearchFilter extends Filter
{
    public string $attribute = 'search';

    protected function applyFilter($builder)
    {
        return StayText::apply($builder, (string) $this->parameters[$this->attribute]);
    }
}
