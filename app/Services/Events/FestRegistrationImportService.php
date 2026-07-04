<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Support\ExcelImport;

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
            $isGroup = in_array($item->participant_type, ['group', 'team'], true);

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
        $limitService = new FestParticipationLimitService($event);
        $eligibilityService = app(FestRegistrationEligibilityService::class);

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
                    $teacher = Teacher::where('tenant_id', $school->id)->where('reg_no', $regNo)->first();
                    if (! $teacher) {
                        $errors[] = "Teacher reg_no {$regNo} not found.";
                        $skipped++;

                        continue 2;
                    }
                    $teacherIds[] = $teacher->id;
                }

                if (count($teacherIds) > 1 && ! in_array($item->participant_type, ['group', 'team'], true)) {
                    $errors[] = "Item {$item->title} allows only one teacher.";
                    $skipped++;

                    continue;
                }

                FestRegistration::create([
                    'event_id'     => $event->id,
                    'item_id'      => $item->id,
                    'school_id'    => $school->id,
                    'status'       => 'submitted',
                    'submitted_at' => now(),
                ])->tap(function (FestRegistration $registration) use ($teacherIds) {
                    foreach ($teacherIds as $teacherId) {
                        FestParticipant::create([
                            'registration_id'  => $registration->id,
                            'teacher_id'       => $teacherId,
                            'participant_type' => 'teacher',
                            'participant_role' => 'performer',
                        ]);
                    }
                });

                $imported++;

                continue;
            }

            $performerIds = [];
            foreach ($performers as $regNo) {
                $student = Student::where('tenant_id', $school->id)->where('reg_no', $regNo)->first();
                if (! $student) {
                    $errors[] = "Student reg_no {$regNo} not found.";
                    $skipped++;

                    continue 2;
                }
                $performerIds[] = $student->id;
            }

            $standbyIds = [];
            foreach ($standbys as $regNo) {
                $student = Student::where('tenant_id', $school->id)->where('reg_no', $regNo)->first();
                if (! $student) {
                    $errors[] = "Standby reg_no {$regNo} not found.";
                    $skipped++;

                    continue 2;
                }
                $standbyIds[] = $student->id;
            }

            $isGroup = in_array($item->participant_type, ['group', 'team'], true);
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

            $limitErrors = $limitService->validateRegistration($item, $school->id, $performerIds, $standbyIds);
            if ($limitErrors) {
                $errors[] = implode(' ', $limitErrors);
                $skipped++;

                continue;
            }

            $eligibilityErrors = $eligibilityService->validateStudents(
                $event,
                $item,
                array_merge($performerIds, $standbyIds)
            );
            if ($eligibilityErrors) {
                $errors[] = implode(' ', $eligibilityErrors);
                $skipped++;

                continue;
            }

            $registration = FestRegistration::create([
                'event_id'     => $event->id,
                'item_id'      => $item->id,
                'school_id'    => $school->id,
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]);

            $groupId = null;
            if ($isGroup) {
                $festGroup = FestGroup::create([
                    'registration_id' => $registration->id,
                    'team_name'       => $group['team_name'],
                ]);
                $groupId = $festGroup->id;
            }

            foreach ($performerIds as $studentId) {
                FestParticipant::create([
                    'registration_id'  => $registration->id,
                    'group_id'         => $groupId,
                    'student_id'       => $studentId,
                    'participant_type' => 'student',
                    'participant_role' => 'performer',
                ]);
            }

            foreach ($standbyIds as $studentId) {
                FestParticipant::create([
                    'registration_id'  => $registration->id,
                    'group_id'         => $groupId,
                    'student_id'       => $studentId,
                    'participant_type' => 'student',
                    'participant_role' => 'standby',
                ]);
            }

            $imported++;
        }

        if ($imported > 0) {
            app(FestSchoolEventFeeService::class)->recalculate($event, $school->id);
        }

        return compact('imported', 'skipped', 'errors');
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
