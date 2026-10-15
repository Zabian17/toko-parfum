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

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000'), env('APP_URL', 'http://localhost')],

    'allowed_origins_patterns' => [
        '#^https://.*\.devtunnels\.ms$#',   // VS Code dev tunnels
        '#^https?://.*\.ngrok\.io$#',       // ngrok
        '#^https?://.*\.ngrok-free\.app$#', // ngrok free
        '#^http://localhost(:\d+)?$#',       // localhost any port
        '#^http://127\.0\.0\.1(:\d+)?$#',  // 127.0.0.1 any port
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
