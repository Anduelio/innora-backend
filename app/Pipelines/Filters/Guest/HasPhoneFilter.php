<?php

namespace App\Pipelines\Filters\Guest;

use App\Pipelines\Filters\Filter;

class HasPhoneFilter extends Filter
{
    public string $attribute = 'withPhone';

    protected function applyFilter($builder)
    {
        $flag = filter_var($this->parameters[$this->attribute], FILTER_VALIDATE_BOOLEAN);
        if (! $flag) {
            return $builder;
        }

        return $builder->whereNotNull('phone')->where('phone', '!=', '');
    }
}
