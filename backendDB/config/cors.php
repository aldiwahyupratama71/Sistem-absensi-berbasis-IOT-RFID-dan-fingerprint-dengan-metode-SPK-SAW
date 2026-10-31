<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi CORS untuk mengizinkan frontend React mengakses API.
    | Origin frontend dibaca dari env FRONTEND_URL agar tidak hardcode.
    | Tambahkan FRONTEND_URL=http://localhost:5173 di file .env.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(
        explode(',', env('FRONTEND_URL', 'http://localhost:5173'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Stateful cookie-based auth memerlukan credentials.
    // Untuk token Bearer, ini opsional tapi tidak membahayakan.
    'supports_credentials' => true,

];
