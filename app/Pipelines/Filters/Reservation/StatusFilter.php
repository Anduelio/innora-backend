<?php

namespace App\Pipelines\Filters\Reservation;

use App\Enums\ReservationStatus;
use App\Pipelines\Filters\Filter;

class StatusFilter extends Filter
{
    public string $attribute = 'status';

    protected function applyFilter($builder)
    {
        $tokens = $this->parameters[$this->attribute];
        if (! is_array($tokens) || $tokens === []) {
            return $builder;
        }

        $values = [];
        foreach ($tokens as $token) {
            foreach (ReservationStatus::fromFilter((string) $token) as $status) {
                $values[$status->value] = $status->value;
            }
        }

        if ($values === []) {
            return $builder;
        }

        return $builder->whereIn('status', array_values($values));
    }
}
