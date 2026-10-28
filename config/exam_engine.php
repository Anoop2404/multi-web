<?php

/*
| Online exam engine (docs/ONLINE_EXAM_ENGINE_PLAN_2026_10_15.md, exam-engine/CONTRACT.md).
| Laravel talks to the Go engine over its internal API; only the engine touches Redis.
*/
return [
    // Server-to-server base URL of the engine's internal API, e.g. http://127.0.0.1:8090
    'internal_url' => env('EXAM_ENGINE_INTERNAL_URL'),
    'internal_token' => env('EXAM_ENGINE_INTERNAL_TOKEN'),
    'timeout_seconds' => (int) env('EXAM_ENGINE_TIMEOUT', 15),

    // Public exam site students open, e.g. https://exam.example.org (the Sahodaya code is appended).
    'public_url' => env('EXAM_ENGINE_PUBLIC_URL', 'http://127.0.0.1:8090'),

    // Ed25519 key pair for portal launch tokens (php artisan exam-engine:keys). Base64, libsodium format.
    'launch_secret_key' => env('EXAM_ENGINE_LAUNCH_SECRET_KEY'),

    // Platform capacity per time slot, by stage (plan §9). Bookings above it are refused.
    'capacity' => (int) env('EXAM_ENGINE_CAPACITY', 2000),
    // A slot above this many booked students triggers scale-out (plan §9.2).
    'scale_out_threshold' => (int) env('EXAM_ENGINE_SCALE_OUT_THRESHOLD', 2000),
    // Online exams at/above this enrolled count should use the Go engine, not Laravel draft autosave.
    'force_engine_threshold' => (int) env('EXAM_ENGINE_FORCE_THRESHOLD', 500),

    // Defaults for new engine exams (plan §4, §11.2).
    'defaults' => [
        'timing' => 'fixed',
        'entry_mode' => 'either',
        'supervision' => 'open',
        'checkin_hours' => 48,
        'waiting_room_minutes' => 60,
        'late_entry_minutes' => 15,
        'grace_seconds' => 90,
        'device_idle_minutes' => 2,
        'sync_interval_seconds' => 10,
        'heartbeat_seconds' => 30,
        'coordinator_extend_cap_minutes' => 10,
        'show_score_on_submit' => false,
    ],

    // Questions with images are embedded in the encrypted paper; keep each image under this size.
    'max_image_bytes' => (int) env('EXAM_ENGINE_MAX_IMAGE_BYTES', 400 * 1024),
];
