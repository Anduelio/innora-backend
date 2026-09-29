<?php

namespace App\Pipelines\Filters\Reservation;

use App\Pipelines\Filters\Filter;

class InHouseOnFilter extends Filter
{
    public string $attribute = 'in_house_on';

    protected function applyFilter($builder)
    {
        $day = $this->parameters[$this->attribute];

        return $builder
            ->whereDate('check_in', '<=', $day)
            ->whereDate('check_out', '>', $day);
    }
}
