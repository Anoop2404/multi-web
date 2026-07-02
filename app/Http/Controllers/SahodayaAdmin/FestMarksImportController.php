<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Events\FestScoreboardUpdated;
use App\Models\FestEvent;
use App\Models\FestMark;
use App\Services\Events\EventContext;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestAthleticRecordService;
use App\Services\Events\FestGradePointService;
use App\Services\Events\FestParticipantLookupService;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

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
            fputcsv($out, ['participant_id', 'reg_no', 'chest_no', 'item_title', 'name', 'grade', 'position', 'score', 'measurement_value', 'measurement_unit']);
            foreach ($rows as $row) {
                fputcsv($out, [
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
        FestGradePointService $gradePointService,
        FestAthleticRecordService $recordService,
        FestParticipantLookupService $lookup,
        PlatformAuditLogger $audit,
    ) {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        EventLifecycleGate::allowMarkEntry($event);

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
            $score = isset($data['score']) && $data['score'] !== '' ? (float) $data['score'] : null;
            $grade = $data['grade'] ?? null;

            if ($score !== null && ! $grade) {
                $grade = $gradePointService->resolveGradeFromScore($event, $itemId, $score);
            }

            $mark = FestMark::updateOrCreate(
                ['item_id' => $itemId, 'participant_id' => $participant->id],
                [
                    'event_id'          => $event->id,
                    'grade'             => $grade ?: null,
                    'position'          => ! empty($data['position']) ? (int) $data['position'] : null,
                    'score'             => $score,
                    'measurement_value' => $data['measurement_value'] ?? null,
                    'measurement_unit'  => $data['measurement_unit'] ?? null,
                    'locked_by'         => $request->user()->id,
                    'locked_at'         => now(),
                ]
            );

            $recordService->evaluateMark($mark->fresh());
            $imported++;
        }

        fclose($handle);

        EventContext::for($event)->recalculateSchoolPoints();
        FestScoreboardUpdated::dispatch($event->fresh());

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
