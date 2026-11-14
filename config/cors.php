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
        'null',
        'tauri://localhost',
        'https://tauri.localhost',
        'http://tauri.localhost',
        'http://localhost',
        'https://malcom-lgc.com',
        'https://www.malcom-lgc.com',
        'https://app.malcom-lgc.com',
        'https://admin.malcom-lgc.com',
        'https://front.maboutique.tech',
        'https://admin.maboutique.tech',
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:8001',
        'http://127.0.0.1:8001',
    ],

    'allowed_origins_patterns' => [
        '#^tauri://.*$#',
        '#^https?://.*tauri\.localhost.*$#',
        '#^https?://localhost.*$#',
        '#^https?://127\.0\.0\.1.*$#',
        '#^https?://.*malcom-lgc\.com.*$#',
        '#^null$#',
        '#.*#'
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Shop-Nature', 'Authorization'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
