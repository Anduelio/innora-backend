<?php

return [
    'default' => 'beds24',

    /*
    | The catalog is a list so another provider can be added later.
    | Only Beds24 is configured for now. Tokens are typed in Cilësimet.
    */
    'providers' => [
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
