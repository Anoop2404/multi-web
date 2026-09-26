<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'pdf_converter' => [
        'url' => env('PDF_CONVERTER_URL'),
        'timeout' => env('PDF_CONVERTER_TIMEOUT', 300),
        'connect_timeout' => env('PDF_CONVERTER_CONNECT_TIMEOUT', 15),
        // Converter connections PdfGenerator::renderEach() keeps busy at once (rolling
        // window) during bulk certificate renders.
        'concurrency' => env('PDF_CONVERTER_CONCURRENCY', 3),
        // DomPDF fallback when the converter fails. Off: it exhausts memory on large reports.
        'fallback' => env('PDF_CONVERTER_FALLBACK', false),
    ],

    // Private S3 downloads (certificate PDFs, ZIP exports, school documents, ...) are handed
    // to the browser as short-lived signed CloudFront URLs instead of being streamed through
    // the app server, when url + key_pair_id + private_key_path are all set. The
    // distribution must be restricted to this key group and read the bucket through OAC —
    // see TenantStorage::cloudFrontSignedUrl(). Unset: downloads stream as before.
    'cloudfront' => [
        'url' => env('CLOUDFRONT_PRIVATE_URL'),
        'key_pair_id' => env('CLOUDFRONT_KEY_PAIR_ID'),
        'private_key_path' => env('CLOUDFRONT_PRIVATE_KEY_PATH'),
        // The distribution's S3 origin path, if it has one (normally empty).
        'origin_path' => env('CLOUDFRONT_ORIGIN_PATH', ''),
        'ttl' => env('CLOUDFRONT_SIGNED_URL_TTL', 600),
    ],

];
