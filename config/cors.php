<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'admin/*', 'loginAdmin'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://front.maboutique.tech',
        'https://admin.maboutique.tech',
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'tauri://localhost',
        'https://tauri.localhost'
    ],

    'allowed_origins_patterns' => ['#^http://localhost:\d+$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Shop-Nature'],

    'max_age' => 0,

    'supports_credentials' => true,

];
