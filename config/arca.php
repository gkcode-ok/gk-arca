<?php

return [
 
   'gkarca' => [
        'base_url' => rtrim((string) env('GKARCA_BASE_URL', 'http://localhost:8001'), '/'),
        'admin_token' => env('GKARCA_ADMIN_TOKEN'),
        'entorno' => env('GKARCA_ENTORNO', 'homo'),
    ],
];
