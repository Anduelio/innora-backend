<?php

namespace App\Pipelines\Filters\Reservation;

class StayText
{
    public static function apply($builder, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $builder;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $builder->where(function ($query) use ($like) {
            $query->where('notes', 'like', $like)
                ->orWhereHas('guest', function ($guest) use ($like) {
                    $guest->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->orWhereHas('stay.room', function ($room) use ($like) {
                    $room->where('number', 'like', $like);
                });
        });
    }
}
