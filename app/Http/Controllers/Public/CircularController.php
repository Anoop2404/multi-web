<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\Circular;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;

class CircularController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        $circulars = Circular::where('tenant_id', $tenant->id)
            ->orderByDesc('issued_date')
            ->paginate(20);

        return $this->renderPublic('public.circulars.index', $tenant, [
            'circulars' => $circulars,
            'pageSeo'   => [
                'title'       => 'Circulars — '.$tenant->name,
                'description' => 'Official circulars and notices from '.$tenant->name,
                'og_type'     => 'website',
            ],
        ]);
    }

    public function download(Circular $circular)
    {
        $tenant = $this->resolveTenant();

        abort_if($circular->tenant_id !== $tenant->id, 404);

        $disk = config('filesystems.default', TenantStorage::uploadDisk());

        if (! Storage::disk($disk)->exists($circular->file_path)) {
            abort(404, 'Circular file not found.');
        }

        $circular->increment('download_count');

        if ($disk === 's3' || $disk === 'private') {
            return redirect(Storage::disk($disk)->temporaryUrl($circular->file_path, now()->addMinutes(15)));
        }

        return Storage::disk($disk)->download($circular->file_path);
    }
}
