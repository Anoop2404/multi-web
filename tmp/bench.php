<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$start = microtime(true);
$marks = \App\Models\FestMark::where('event_id', 1)
    ->with(['participant.registration', 'item'])
    ->get();
$load = microtime(true) - $start;
echo "Mark count: " . $marks->count() . " load_ms:" . ($load * 1000) . "\n";

$start = microtime(true);
$dedup = $marks->unique(fn ($m) => $m->deduplicationKey())->keyBy('id');
$dedupMs = (microtime(true) - $start) * 1000;
echo "Dedup: " . $dedup->count() . " in " . $dedupMs . "ms\n";
