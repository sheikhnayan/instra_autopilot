<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Instagram API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Instagram Graph API integration
    |
    */

    'app_id' => env('INSTAGRAM_APP_ID'),
    'app_secret' => env('INSTAGRAM_APP_SECRET'),
    'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Account Import Limits
    |--------------------------------------------------------------------------
    |
    | These limits help prevent server overload when importing large numbers
    | of Instagram accounts, especially on servers with limited resources.
    |
    */

    'import_limits' => [
        // Maximum number of Facebook pages to fetch per import session
        'max_pages_per_import' => env('INSTAGRAM_MAX_PAGES_PER_IMPORT', 100),
        
        // Number of pages to fetch per API request (pagination)
        'pages_batch_size' => env('INSTAGRAM_PAGES_BATCH_SIZE', 25),
        
        // Maximum processing time in seconds to prevent timeouts
        'max_processing_time' => env('INSTAGRAM_MAX_PROCESSING_TIME', 30),
        
        // Delay between API calls in microseconds (to prevent rate limiting)
        'api_call_delay' => env('INSTAGRAM_API_CALL_DELAY', 150000), // 150ms
    ],

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */

    'api_version' => 'v18.0',
    'graph_url' => 'https://graph.facebook.com',
];
