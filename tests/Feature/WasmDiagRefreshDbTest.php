<?php

// Diagnostic scratch file from a php-wasm sandbox investigation (confirmed the
// available WASM PHP CLI crashes on any nested Artisan::call() from inside an
// already-running PHPUnit process — e.g. $this->seed() or eager RefreshDatabase
// migration — independent of PHP version or seeder content). Not a real test,
// not referenced anywhere. Safe to delete.
