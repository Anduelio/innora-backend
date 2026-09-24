<?php

return [
    'default' => 'hotelrunner',

    /*
    | Base URLs are public endpoints. Tokens, passwords, and property ids
    | are typed in Cilësimet and stored encrypted on channel_connections.
    */
    'providers' => [
        'hotelrunner' => [
            'label' => 'HotelRunner',
            'base_url' => env('HOTELRUNNER_BASE_URL', 'https://app.hotelrunner.com'),
            'fields' => [
                ['key' => 'api_key', 'secret' => true, 'required' => true],
                ['key' => 'hotel_id', 'secret' => false, 'required' => true],
                ['key' => 'base_url', 'secret' => false, 'required' => false],
            ],
        ],
        'siteminder' => [
            'label' => 'SiteMinder',
            'base_url' => env('SITEMINDER_BASE_URL', 'https://api.siteminder.com'),
            'fields' => [
                ['key' => 'username', 'secret' => false, 'required' => true],
                ['key' => 'password', 'secret' => true, 'required' => true],
                ['key' => 'hotel_code', 'secret' => false, 'required' => true],
                ['key' => 'base_url', 'secret' => false, 'required' => false],
            ],
        ],
        'beds24' => [
            'label' => 'Beds24',
            'base_url' => env('BEDS24_BASE_URL', 'https://beds24.com/api/v2'),
            'fields' => [
                ['key' => 'api_key', 'secret' => true, 'required' => true],
                ['key' => 'prop_id', 'secret' => false, 'required' => true],
                ['key' => 'base_url', 'secret' => false, 'required' => false],
            ],
        ],
    ],
];
