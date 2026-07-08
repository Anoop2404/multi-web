<?php

namespace App\Http\Controllers;

use App\Models\ExportJob;
use App\Support\TenantStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportJobDownloadController extends Controller
{
    public function __invoke(ExportJob $exportJob): StreamedResponse
    {
        abort_unless($exportJob->user_id === auth()->id(), 403);
        abort_unless($exportJob->isReady(), 404, 'Export is not ready yet.');

        return TenantStorage::downloadPrivate(
            $exportJob->file_path,
            $exportJob->storage_disk,
            $exportJob->filename,
        );
    }
}
