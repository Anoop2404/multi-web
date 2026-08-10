<?php

return [

    /*
    |--------------------------------------------------------------------------
    | State Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Defines the primary domain host, database connection name, queue name,
    | cache prefix, and session cookie for dedicated State domain operations.
    |
    */

    'domain' => env('STATE_APP_DOMAIN', 'state.localhost'),

    'connection' => env('STATE_DB_CONNECTION_NAME', 'state'),

    'queue_connection' => env('STATE_QUEUE_CONNECTION', 'default'),

    'cache_prefix' => env('STATE_CACHE_PREFIX', 'state_cache_'),

    'session_cookie' => env('STATE_SESSION_COOKIE', 'state_session'),

    'private_disk' => env('STATE_PRIVATE_DISK', 'local'),

    'signing_key_id' => env('STATE_QUALIFIER_SIGNING_KEY_ID'),

];
