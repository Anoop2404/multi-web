<?php

namespace App\Services\Exports;

use App\Models\ExportJob;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\ReportRegistry;
use App\Support\TenantStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportDispatcher
{
    public function threshold(): int
    {
        return ReportRegistry::asyncExportThreshold();
    }

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $rows
     * @param  list<string>  $headers
     * @param  callable(array<string, mixed>): array<int, string|int|float|null>  $mapRow
     */
    public function dispatch(
        User $user,
        string $exportType,
        string $filename,
        Collection|array $rows,
        array $headers,
        callable $mapRow,
    ): StreamedResponse|RedirectResponse {
        $collection = $rows instanceof Collection ? $rows : collect($rows);
        $count = $collection->count();

        if ($count <= $this->threshold()) {
            return $this->streamDownload($filename, $headers, $collection, $mapRow);
        }

        $payload = [
            'headers' => $headers,
            'rows'    => $collection->map(fn ($row) => $mapRow(is_array($row) ? $row : (array) $row))->values()->all(),
        ];

        $path = 'exports/queued/'.uniqid($exportType.'_', true).'.json';
        TenantStorage::put($path, json_encode($payload));

        $job = ExportJob::create([
            'user_id'     => $user->id,
            'export_type' => $exportType,
            'filename'    => $filename,
            'row_count'   => $count,
            'status'      => 'pending',
        ]);

        \App\Jobs\GenerateCsvExportJob::dispatch($job->id, $path);

        return back()->with('success', "Export queued ({$count} rows). You will be notified when the CSV is ready.");
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $headers
     * @param  callable(array<string, mixed>): array<int, string|int|float|null>  $mapRow
     */
    public function streamDownload(
        string $filename,
        array $headers,
        Collection $rows,
        callable $mapRow,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($headers, $rows, $mapRow) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $mapRow(is_array($row) ? $row : (array) $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
