<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\CsvSafety;
use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestMarkSaveService;
use App\Services\Events\FestParticipantLookupService;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FestMarksImportController extends SahodayaAdminController
{
    public function importForm(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Events/MarksImport', $this->withEventActivity($event, FestPageActivity::MARKS_IMPORT, [
            'event' => $event,
        ]));
    }

    public function importTemplate(string $tenantId, FestEvent $event, FestParticipantLookupService $lookup)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rows = $lookup->approvedRowsForTemplate($event);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            CsvSafety::fputcsv($out, ['participant_id', 'reg_no', 'chest_no', 'item_title', 'name', 'grade', 'position', 'score', 'measurement_value', 'measurement_unit']);
            foreach ($rows as $row) {
                CsvSafety::fputcsv($out, [
                    $row['participant_id'],
                    $row['reg_no'],
                    $row['chest_no'],
                    $row['item_title'],
                    $row['name'],
                    '', '', '', '', '',
                ]);
            }
            fclose($out);
        }, "fest-marks-{$event->id}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function importStore(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestMarkSaveService $markSave,
        FestParticipantLookupService $lookup,
        PlatformAuditLogger $audit,
    ) {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headerRow = fgetcsv($handle);
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow ?: []);
        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $key) {
                $data[$key] = trim((string) ($row[$i] ?? ''));
            }

            if (($data['participant_id'] ?? '') === '' && ($data['reg_no'] ?? '') === '') {
                continue;
            }

            $participant = $lookup->resolveForEvent($event, $data);
            if (! $participant) {
                $errors[] = 'Participant not found: '.($data['reg_no'] ?? $data['participant_id']);

                continue;
            }

            $itemId = $participant->registration->item_id;
            $item = FestEventItem::where('event_id', $event->id)->find($itemId);
            if (! $item) {
                $errors[] = 'Participant item does not belong to this event: '.($data['reg_no'] ?? $data['participant_id']);

                continue;
            }

            $score = isset($data['score']) && $data['score'] !== '' ? (float) $data['score'] : null;
            $grade = $data['grade'] ?? null;

            try {
                EventLifecycleGate::allowMarkEntryForItem($event, $item);
                // recalculate: false — this loop can run hundreds of rows per import; the
                // default recalculate() rescans every FestMark in the event, so leaving it
                // on here reran that full-event recompute once per CSV row (O(rows ×
                // total event marks)). Recalculating once after the loop instead makes the
                // whole import do that expensive pass exactly once, regardless of row count.
                $markSave->save($event, [
                    'participant_id'    => $participant->id,
                    'item_id'           => $itemId,
                    'grade'             => $grade ?: null,
                    'position'          => ! empty($data['position']) ? (int) $data['position'] : null,
                    'score'             => $score,
                    'measurement_value' => $data['measurement_value'] ?? null,
                    'measurement_unit'  => $data['measurement_unit'] ?? null,
                ], $request->user()->id, recalculate: false);
            } catch (ValidationException|HttpException $e) {
                $message = $e instanceof ValidationException
                    ? (collect($e->errors())->flatten()->first() ?? 'Mark could not be imported.')
                    : $e->getMessage();
                $errors[] = 'Participant '.($data['reg_no'] ?? $data['participant_id']).": {$message}";

                continue;
            }

            $imported++;
        }

        fclose($handle);

        if ($imported > 0) {
            $markSave->recalculate($event);
        }

        $audit->festEvent($event, FestPageActivity::MARKS_IMPORT, 'fest.marks.imported', "Imported {$imported} mark row(s)", [
            'imported' => $imported,
            'errors'   => count($errors),
        ]);

        $message = "Imported {$imported} mark row(s).";
        if ($errors !== []) {
            $message .= ' '.count($errors).' row(s) skipped.';
        }

        return back()->with('success', $message)->with('importErrors', $errors);
    }
}
