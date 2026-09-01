<?php
// TEMPORARY DIAGNOSTIC SCRIPT — delete this file after use.
// Hit this directly through the live web server (not `php artisan tinker`, which
// spins up a separate CLI process that usually bypasses PHP-FPM's opcode cache
// entirely and so can't reveal whether the FPM workers serving real requests are
// still running stale bytecode). This checks that directly, and resets it.
header('Content-Type: text/plain');

if (!function_exists('opcache_get_status')) {
    echo "OPcache is not available on this PHP install (opcache_get_status() missing).\n";
    echo "That rules out stale-bytecode as the cause -- look elsewhere.\n";
    exit;
}

$status = opcache_get_status(false);
echo "OPcache enabled: " . var_export($status !== false, true) . "\n";

if ($status) {
    echo "opcache.validate_timestamps ini setting: " . var_export(ini_get('opcache.validate_timestamps'), true) . "\n";
    echo "opcache.revalidate_freq: " . ini_get('opcache.revalidate_freq') . "\n";
    echo "cache_full: " . var_export($status['cache_full'] ?? null, true) . "\n";
    echo "num_cached_scripts: " . ($status['opcache_statistics']['num_cached_scripts'] ?? 'n/a') . "\n";

    // Check whether this specific controller file, as currently cached in opcache,
    // matches what's actually on disk right now.
    $target = __DIR__ . '/../app/Http/Controllers/SahodayaAdmin/FestIdCardController.php';
    $target = realpath($target);
    if ($target && isset($status['scripts'][$target])) {
        $cached = $status['scripts'][$target];
        echo "\nFestIdCardController.php IS in opcache.\n";
        echo "  cached timestamp: " . date('Y-m-d H:i:s', $cached['timestamp']) . "\n";
        echo "  file mtime on disk: " . date('Y-m-d H:i:s', filemtime($target)) . "\n";
        echo "  hits: " . $cached['hits'] . "\n";
        if ($cached['timestamp'] < filemtime($target)) {
            echo "  ==> STALE: opcache cached this file BEFORE its last on-disk change.\n";
        } else {
            echo "  ==> Cache timestamp is not older than the file -- opcache is not the cause.\n";
        }
    } elseif ($target) {
        echo "\nFestIdCardController.php is NOT currently in opcache (would compile fresh on next request).\n";
    } else {
        echo "\nCould not resolve FestIdCardController.php path to check.\n";
    }
}

$reset = opcache_reset();
echo "\nopcache_reset() called, result: " . var_export($reset, true) . "\n";
echo "Done. Delete this file now.\n";
