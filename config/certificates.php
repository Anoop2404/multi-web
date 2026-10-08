<?php

return [
    // Small jobs distribute large event runs across available queue workers.
    'render_chunk_size' => env('CERTIFICATE_RENDER_CHUNK_SIZE', 20),
    // Concurrent converter requests per worker; every certificate has two variants.
    'render_concurrency' => env('CERTIFICATE_RENDER_CONCURRENCY', 6),
    // Keep existing workers compatible unless a dedicated queue is configured.
    'render_queue' => env('CERTIFICATE_RENDER_QUEUE', 'default'),
];
