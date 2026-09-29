<?php

namespace App\Pipelines\Filters\Guest;

use App\Pipelines\Filters\Filter;

class QueryFilter extends Filter
{
    public string $attribute = 'q';

    protected function applyFilter($builder)
    {
        $term = trim((string) $this->parameters[$this->attribute]);
        if ($term === '') {
            return $builder;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $builder->where(function ($query) use ($like) {
            $query->where('name', 'like', $like)->orWhere('phone', 'like', $like);
        });
    }
}
