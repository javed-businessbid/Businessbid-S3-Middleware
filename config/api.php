<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed IP Addresses
    |--------------------------------------------------------------------------
    |
    | List the IP addresses or CIDR ranges that are allowed to call the API.
    | Examples:
    |   203.0.113.10
    |   203.0.113.0/24
    |
    | Leave empty to disable the IP restriction.
    |
    */
    'allowlisted_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('API_ALLOWLISTED_IPS', ''))
    ))),
];
