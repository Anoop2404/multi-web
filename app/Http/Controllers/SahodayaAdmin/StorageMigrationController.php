<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Jobs\MigrateLegacyUploadsJob;
use App\Services\Storage\LegacyStorageMigrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StorageMigrationController extends SahodayaAdminController
{
    public function index(LegacyStorageMigrationService $migration)
    {
        return $this->inertia('Sahodaya/Settings/StorageMigration', [
            'status'     => $migration->status(),
            'scan'       => $migration->scan($this->sahodaya->id),
            'lastJobKey' => session('storage_migration_key'),
        ]);
    }

    public function scan(LegacyStorageMigrationService $migration)
    {
        return response()->json($migration->scan($this->sahodaya->id));
    }

    public function migrate(Request $request, LegacyStorageMigrationService $migration)
    {
        $data = $request->validate([
            'delete_local'       => 'boolean',
            'include_filesystem' => 'boolean',
            'sync'               => 'boolean',
        ]);

        $deleteLocal = (bool) ($data['delete_local'] ?? false);
        $includeFilesystem = (bool) ($data['include_filesystem'] ?? false);

        if ($request->boolean('sync')) {
            $result = $migration->migrate($this->sahodaya->id, false, $deleteLocal, $includeFilesystem);

            return back()->with('success', "Migration complete — migrated {$result['migrated']}, skipped {$result['skipped']}, failed {$result['failed']}.");
        }

        $key = 'storage_migration_'.Str::uuid();
        MigrateLegacyUploadsJob::dispatch($this->sahodaya->id, $deleteLocal, $includeFilesystem, $key);

        return back()
            ->with('success', 'Migration queued. Refresh to check progress.')
            ->with('storage_migration_key', $key);
    }

    public function progress(Request $request)
    {
        $key = $request->string('key')->toString();
        abort_if($key === '', 422);

        return response()->json(Cache::get($key, ['status' => 'unknown']));
    }
}
