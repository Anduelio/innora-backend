<?php

namespace App\Pipelines\Filters\Reservation;

use App\Enums\ReservationSource;
use App\Pipelines\Filters\Filter;

class SourceFilter extends Filter
{
    public string $attribute = 'source';

    protected function applyFilter($builder)
    {
        $source = ReservationSource::fromFilter((string) $this->parameters[$this->attribute]);
        if ($source === null) {
            return $builder;
        }

        return $builder->where('source', $source->value);
    }
}
