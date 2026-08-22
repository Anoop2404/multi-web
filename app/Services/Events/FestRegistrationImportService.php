<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Support\ExcelImport;
use App\Support\FestTeamSquadRules;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FestRegistrationImportService
{
    /** @return array{imported: int, skipped: int, errors: list<string>} */
    public function importFromCsv(FestEvent $event, Tenant $school, string $path, bool $isTeacherFest = false): array
    {
        return $this->importFromSpreadsheet($event, $school, $path, $isTeacherFest);
    }

    /** @return array{imported: int, skipped: int, errors: list<string>} */
    public function importFromSpreadsheet(FestEvent $event, Tenant $school, string $path, bool $isTeacherFest = false): array
    {
        $parsed = ExcelImport::associativeRows($path);
        $rows = collect($parsed['rows'])
            ->filter(fn (array $row) => ($row['reg_no'] ?? '') !== '')
            ->values()
            ->all();

        if ($rows === []) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['No data rows found.']];
        }

        $grouped = [];
        foreach ($rows as $lineNum => $row) {
            $item = $this->resolveItem($event, $row);
            if (! $item) {
                return [
                    'imported' => 0,
                    'skipped' => count($rows),
                    'errors'   => ['Row '.($lineNum + 2).': unknown item (use item_id or item_title).'],
                ];
            }

            $teamName = $row['team_name'] ?? '';
            $role = strtolower($row['role'] ?? 'performer') === 'standby' ? 'standby' : 'performer';
            $isGroup = FestTeamSquadRules::isMultiPerson($item->participant_type);

            if ($isGroup) {
                $key = $item->id.'|'.($teamName !== '' ? $teamName : 'group-'.$row['reg_no']);
            } else {
                $key = $item->id.'|'.$row['reg_no'];
            }

            $grouped[$key]['item'] = $item;
            $grouped[$key]['team_name'] = $teamName;
            $grouped[$key]['rows'][] = ['reg_no' => $row['reg_no'], 'role' => $role];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $createService = app(FestRegistrationCreateService::class);

        // Previously looked up one Student/Teacher row per reg_no, individually, inside
        // the loop below — a school importing a few hundred rows ran a few hundred
        // individual lookup queries. Batch-fetch every reg_no referenced anywhere in
        // this import once, up front, keyed by reg_no so the loop below is an in-memory
        // lookup instead. Duplicate-reg_no-across-groups (e.g. the same student on a
        // performer row in one group and a standby row in another) is naturally
        // deduped by array_unique() before the query, and the keyed map handles being
        // read multiple times identically to the old per-row query.
        $allRegNos = [];
        foreach ($grouped as $group) {
            foreach ($group['rows'] as $entry) {
                $allRegNos[] = $entry['reg_no'];
            }
        }
        $allRegNos = array_values(array_unique($allRegNos));

        if ($isTeacherFest) {
            $teachersByRegNo = Teacher::where('tenant_id', $school->id)
                ->whereIn('reg_no', $allRegNos)
                ->get()
                ->keyBy('reg_no');
        } else {
            $studentsByRegNo = Student::where('tenant_id', $school->id)
                ->whereIn('reg_no', $allRegNos)
                ->get()
                ->keyBy('reg_no');
        }

        foreach ($grouped as $group) {
            $item = $group['item'];
            $performers = [];
            $standbys = [];

            foreach ($group['rows'] as $entry) {
                if ($entry['role'] === 'standby') {
                    $standbys[] = $entry['reg_no'];
                } else {
                    $performers[] = $entry['reg_no'];
                }
            }

            if ($isTeacherFest) {
                $teacherIds = [];
                foreach ($performers as $regNo) {
                    $teacher = $teachersByRegNo->get($regNo);
                    if (! $teacher) {
                        $errors[] = "Teacher reg_no {$regNo} not found.";
                        $skipped++;

                        continue 2;
                    }
                    $teacherIds[] = $teacher->id;
                }

                if (count($teacherIds) > 1 && ! FestTeamSquadRules::isMultiPerson($item->participant_type)) {
                    $errors[] = "Item {$item->title} allows only one teacher.";
                    $skipped++;

                    continue;
                }

                try {
                    $registration = $createService->createForSchool($event, $item, $school, $teacherIds);
                    app(FestEventNotifier::class)->registrationSubmittedAdmin($registration->fresh(['event', 'item']));
                } catch (ValidationException|HttpException $e) {
                    $errors[] = "Item {$item->title}: ".$this->importErrorMessage($e);
                    $skipped++;

                    continue;
                }

                $imported++;

                continue;
            }

            $performerIds = [];
            foreach ($performers as $regNo) {
                $student = $studentsByRegNo->get($regNo);
                if (! $student) {
                    $errors[] = "Student reg_no {$regNo} not found.";
                    $skipped++;

                    continue 2;
                }
                $performerIds[] = $student->id;
            }

            $standbyIds = [];
            foreach ($standbys as $regNo) {
                $student = $studentsByRegNo->get($regNo);
                if (! $student) {
                    $errors[] = "Standby reg_no {$regNo} not found.";
                    $skipped++;

                    continue 2;
                }
                $standbyIds[] = $student->id;
            }

            $isGroup = FestTeamSquadRules::isMultiPerson($item->participant_type);
            if ($isGroup) {
                $teamName = $group['team_name'] ?? '';
                if ($teamName === '') {
                    $errors[] = "Item {$item->title} requires team_name.";
                    $skipped++;

                    continue;
                }
                $error = $item->validateSquadCount(count($performerIds));
                if ($error) {
                    $errors[] = $error;
                    $skipped++;

                    continue;
                }
            } elseif (count($performerIds) > 1) {
                $errors[] = "Item {$item->title} allows only one participant.";
                $skipped++;

                continue;
            }

            try {
                $registration = $createService->createForSchool(
                    $event,
                    $item,
                    $school,
                    $performerIds,
                    $standbyIds,
                    $group['team_name'] ?: null,
                );
                app(FestEventNotifier::class)->registrationSubmittedAdmin($registration->fresh(['event', 'item']));
            } catch (ValidationException|HttpException $e) {
                $errors[] = "Item {$item->title}: ".$this->importErrorMessage($e);
                $skipped++;

                continue;
            }

            $imported++;
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importErrorMessage(ValidationException|HttpException $exception): string
    {
        if ($exception instanceof ValidationException) {
            return collect($exception->errors())->flatten()->first() ?? 'Registration could not be imported.';
        }

        return $exception->getMessage();
    }

    /** @param array<string, string> $row */
    private function resolveItem(FestEvent $event, array $row): ?FestEventItem
    {
        if (! empty($row['item_id'])) {
            return FestEventItem::where('event_id', $event->id)->find($row['item_id']);
        }

        if (! empty($row['item_title'])) {
            return FestEventItem::where('event_id', $event->id)
                ->where('title', $row['item_title'])
                ->first();
        }

        return null;
    }

    /**
     * Sahodaya cluster import — CSV includes school_id or school_prefix per row.
     *
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importClusterFromCsv(FestEvent $event, string $sahodayaId, string $path): array
    {
        return $this->importClusterFromSpreadsheet($event, $sahodayaId, $path);
    }

    /** @return array{imported: int, skipped: int, errors: list<string>} */
    public function importClusterFromSpreadsheet(FestEvent $event, string $sahodayaId, string $path): array
    {
        $parsed = ExcelImport::associativeRows($path);
        $bySchool = [];

        foreach ($parsed['rows'] as $assoc) {
            if (($assoc['reg_no'] ?? '') === '') {
                continue;
            }

            $school = $this->resolveSchool($sahodayaId, $assoc);
            if (! $school) {
                return [
                    'imported' => 0,
                    'skipped'  => 0,
                    'errors'   => ['Unknown school for row with reg_no '.($assoc['reg_no'] ?? '').'. Use school_id or school_prefix.'],
                ];
            }

            $bySchool[$school->id][] = $assoc;
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($bySchool as $schoolId => $rows) {
            $school = Tenant::findOrFail($schoolId);
            $tmp = tempnam(sys_get_temp_dir(), 'fest-import-');
            $out = fopen($tmp, 'w');
            fputcsv($out, ['item_id', 'item_title', 'reg_no', 'team_name', 'role']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['item_id'] ?? '',
                    $r['item_title'] ?? '',
                    $r['reg_no'] ?? '',
                    $r['team_name'] ?? '',
                    $r['role'] ?? 'performer',
                ]);
            }
            fclose($out);

            $result = $this->importFromCsv($event, $school, $tmp, $event->event_type === 'teacher_fest');
            @unlink($tmp);

            $imported += $result['imported'];
            $skipped += $result['skipped'];
            $errors = array_merge($errors, $result['errors']);
        }

        return compact('imported', 'skipped', 'errors');
    }

    /** @param array<string, string> $row */
    private function resolveSchool(string $sahodayaId, array $row): ?Tenant
    {
        if (! empty($row['school_id'])) {
            return Tenant::where('id', $row['school_id'])->where('parent_id', $sahodayaId)->first();
        }

        if (! empty($row['school_prefix'])) {
            return Tenant::where('school_prefix', $row['school_prefix'])->where('parent_id', $sahodayaId)->first();
        }

        return null;
    }
}
