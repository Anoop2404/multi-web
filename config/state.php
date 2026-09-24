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

    /*
    |--------------------------------------------------------------------------
    | State Kalotsav module cutover
    |--------------------------------------------------------------------------
    |
    | Phase 11 of docs/STATE_KALOTSAV_MODULE_PLAN_2026_09_23.md. While this is
    | false both stacks stay reachable and nothing in flight breaks: the older
    | /admin/state-workspace/* screens keep working beside the new module at
    | /admin/state/fest/*. Turning it on redirects the old paths into the module,
    | which is the actual switch — deliberately one setting, so it can be turned
    | back off during an event if the module misbehaves, rather than needing a
    | deploy to recover.
    |
    */

    'module_switched' => env('STATE_MODULE_SWITCHED', false),

];
