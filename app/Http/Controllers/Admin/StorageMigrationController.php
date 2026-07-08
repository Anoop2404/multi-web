<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\MigrateLegacyUploadsJob;
use App\Models\Tenant;
use App\Services\Storage\LegacyStorageMigrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StorageMigrationController extends Controller
{
    public function index(LegacyStorageMigrationService $migration)
    {
        return inertia('Storage/Migration', [
            'status'     => $migration->status(),
            'scan'       => $migration->scan(),
            'sahodayas'  => Tenant::query()->where('type', 'sahodaya')->orderBy('name')->get(['id', 'name']),
            'lastJobKey' => session('storage_migration_key'),
        ]);
    }

    public function scan(Request $request, LegacyStorageMigrationService $migration)
    {
        $tenantId = $request->string('tenant')->toString() ?: null;

        return response()->json($migration->scan($tenantId));
    }

    public function migrate(Request $request, LegacyStorageMigrationService $migration)
    {
        $data = $request->validate([
            'tenant'            => 'nullable|string|exists:tenants,id',
            'delete_local'      => 'boolean',
            'include_filesystem'=> 'boolean',
            'sync'              => 'boolean',
        ]);

        $tenantId = $data['tenant'] ?? null;
        $deleteLocal = (bool) ($data['delete_local'] ?? false);
        $includeFilesystem = (bool) ($data['include_filesystem'] ?? false);

        if ($request->boolean('sync')) {
            $result = $migration->migrate($tenantId, false, $deleteLocal, $includeFilesystem);

            return back()->with('success', "Migration complete — migrated {$result['migrated']}, skipped {$result['skipped']}, failed {$result['failed']}.");
        }

        $key = 'storage_migration_'.Str::uuid();
        MigrateLegacyUploadsJob::dispatch($tenantId, $deleteLocal, $includeFilesystem, $key);

        return back()
            ->with('success', 'Migration queued. Refresh this page to check progress.')
            ->with('storage_migration_key', $key);
    }

    public function progress(Request $request)
    {
        $key = $request->string('key')->toString();
        abort_if($key === '', 422);

        return response()->json(Cache::get($key, ['status' => 'unknown']));
    }
}
