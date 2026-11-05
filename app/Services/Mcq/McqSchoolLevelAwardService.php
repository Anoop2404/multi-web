<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\Mcq\McqClassKey;
use App\Support\Mcq\McqSchoolLevelAwardSettings;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * School-scoped KG mark ranking + Promote/Submit for STSE KG Setting 1.
 *
 * Ranks are computed per school + class among present students with marks.
 * Promote locks the exact top cutoff% for district awards when:
 * - ranks 1, 2, 3 each have exactly one student (no ties), and
 * - the cutoff border has no ties that would expand beyond exact quota.
 */
class McqSchoolLevelAwardService
{
    public function schoolCanEnterMarks(McqExam $exam, string $schoolId, ?string $className): bool
    {
        if (! McqSchoolLevelAwardSettings::enabled($exam)) {
            return false;
        }

        if ($exam->results_published) {
            return false;
        }

        if (! McqSchoolLevelAwardSettings::isKgClass($exam, $className)) {
            return false;
        }

        return ! McqSchoolLevelAwardSettings::isClassSubmitted($exam, $schoolId, $className);
    }

    /**
     * @return array{
     *   enabled: bool,
     *   classes: list<string>,
     *   cutoff_percent: float,
     *   by_class: array<string, array<string, mixed>>
     * }
     */
    public function schoolPayload(McqExam $exam, string $schoolId): array
    {
        $cfg = McqSchoolLevelAwardSettings::forExam($exam);
        if (! $cfg['enabled']) {
            return [
                'enabled' => false,
                'classes' => [],
                'cutoff_percent' => 15.0,
                'by_class' => [],
                'tie_error' => McqSchoolLevelAwardSettings::TIE_ERROR,
            ];
        }

        $byClass = [];
        foreach ($cfg['kg']['classes'] as $className) {
            $byClass[$className] = $this->classSummary($exam, $schoolId, $className);
        }

        $maxMarks = [];
        foreach ($cfg['kg']['classes'] as $className) {
            $maxMarks[$className] = (int) $exam->totalQuestionsForClass($className) ?: (int) ($exam->total_questions ?: 0);
        }

        return [
            'enabled' => true,
            'classes' => $cfg['kg']['classes'],
            'cutoff_percent' => (float) $cfg['kg']['cutoff_percent'],
            'by_class' => $byClass,
            'class_max_marks' => $maxMarks,
            'tie_error' => McqSchoolLevelAwardSettings::TIE_ERROR,
        ];
    }

    /**
     * @return array{
     *   class_name: string,
     *   present_count: int,
     *   marked_count: int,
     *   quota: int,
     *   submitted: bool,
     *   can_edit: bool,
     *   can_promote: bool,
     *   validation: array{ok: bool, errors: list<string>},
     *   rows: list<array<string, mixed>>,
     *   awarded_registration_ids: list<int>,
     *   submitted_at: ?string
     * }
     */
    public function classSummary(McqExam $exam, string $schoolId, string $className): array
    {
        $cfg = McqSchoolLevelAwardSettings::forExam($exam);
        $ranked = $this->rankedRows($exam, $schoolId, $className);
        $presentCount = $ranked->count();
        $markedCount = $ranked->filter(fn (array $r) => $r['score'] !== null)->count();
        $quota = $this->quota($presentCount, (float) $cfg['kg']['cutoff_percent']);
        $validation = $this->validateForPromote($ranked, $cfg['kg'], $quota);
        $submitted = McqSchoolLevelAwardSettings::isClassSubmitted($exam, $schoolId, $className);
        $submission = McqSchoolLevelAwardSettings::submissions($exam)[$schoolId][McqClassKey::normalize($className)] ?? null;

        return [
            'class_name' => McqClassKey::normalize($className),
            'present_count' => $presentCount,
            'marked_count' => $markedCount,
            'quota' => $quota,
            'submitted' => $submitted,
            'can_edit' => $this->schoolCanEnterMarks($exam, $schoolId, $className),
            'can_promote' => ! $submitted
                && ! $exam->results_published
                && $presentCount > 0
                && $markedCount >= $presentCount
                && $validation['ok'],
            'validation' => $validation,
            'rows' => $ranked->values()->all(),
            'awarded_registration_ids' => array_values(array_map('intval', $submission['awarded_registration_ids'] ?? [])),
            'submitted_at' => $submission['submitted_at'] ?? null,
        ];
    }

    /**
     * Rank present students for one school + class (score desc). Ties share rank.
     *
     * @return Collection<int, array{
     *   registration_id: int,
     *   student_id: ?int,
     *   student_name: ?string,
     *   hall_ticket_no: ?string,
     *   class_name: string,
     *   score: ?float,
     *   percentage: ?float,
     *   grade: ?string,
     *   school_rank: ?int,
     *   attendance_status: ?string
     * }>
     */
    public function rankedRows(McqExam $exam, string $schoolId, string $className): Collection
    {
        $classKey = McqClassKey::normalize($className);

        $registrations = McqRegistration::query()
            ->where('exam_id', $exam->id)
            ->where('school_id', $schoolId)
            ->active()
            ->where('attendance_status', 'present')
            ->with(['student.schoolClass:id,name', 'mark'])
            ->get()
            ->filter(function (McqRegistration $reg) use ($classKey) {
                $name = $reg->student?->schoolClass?->name;

                return $name !== null && McqClassKey::normalize($name) === $classKey;
            })
            ->values();

        $sorted = $registrations
            ->sortByDesc(fn (McqRegistration $reg) => [
                $reg->mark?->score !== null ? (float) $reg->mark->score : -INF,
                (int) ($reg->mark?->correct_count ?? 0),
                (string) ($reg->student?->name ?? ''),
            ])
            ->values();

        $rank = 0;
        $lastKey = null;
        $position = 0;
        $rows = collect();

        foreach ($sorted as $reg) {
            $position++;
            $score = $reg->mark?->score !== null ? (float) $reg->mark->score : null;
            $schoolRank = null;

            if ($score !== null) {
                $key = [$score, (int) ($reg->mark?->correct_count ?? 0)];
                if ($lastKey !== $key) {
                    $rank = $position;
                    $lastKey = $key;
                }
                $schoolRank = $rank;
            }

            $rows->push([
                'registration_id' => (int) $reg->id,
                'student_id' => $reg->student_id ? (int) $reg->student_id : null,
                'student_name' => $reg->student?->name,
                'hall_ticket_no' => $reg->hall_ticket_no,
                'class_name' => $classKey,
                'score' => $score,
                'percentage' => $reg->mark?->percentage !== null ? (float) $reg->mark->percentage : null,
                'grade' => $reg->mark?->grade,
                'school_rank' => $schoolRank,
                'attendance_status' => $reg->attendance_status,
            ]);
        }

        return $rows;
    }

    public function quota(int $presentCount, float $cutoffPercent): int
    {
        if ($presentCount <= 0 || $cutoffPercent <= 0) {
            return 0;
        }

        return (int) round($presentCount * ($cutoffPercent / 100));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ranked
     * @param  array<string, mixed>  $kg
     * @return array{ok: bool, errors: list<string>, has_rank_ties: bool, has_cutoff_ties: bool}
     */
    public function validateForPromote(Collection $ranked, array $kg, int $quota): array
    {
        $errors = [];
        $marked = $ranked->filter(fn (array $r) => $r['score'] !== null)->values();
        $presentCount = $ranked->count();

        if ($presentCount === 0) {
            return [
                'ok' => false,
                'errors' => ['No present students in this class.'],
                'has_rank_ties' => false,
                'has_cutoff_ties' => false,
            ];
        }

        if ($marked->count() < $presentCount) {
            return [
                'ok' => false,
                'errors' => ['Enter marks for every present student before Promote / Submit.'],
                'has_rank_ties' => false,
                'has_cutoff_ties' => false,
            ];
        }

        $uniqueRanks = array_values(array_map('intval', $kg['unique_top_ranks'] ?? [1, 2, 3]));
        $hasRankTies = false;

        foreach ($uniqueRanks as $neededRank) {
            if ($presentCount < $neededRank) {
                continue;
            }
            $atRank = $marked->where('school_rank', $neededRank);
            if ($atRank->count() !== 1) {
                $hasRankTies = true;
                break;
            }
        }

        $hasCutoffTies = false;
        if (! empty($kg['block_cutoff_ties']) && $quota > 0) {
            // Border tie: everyone at/above the quota-th score exceeds exact quota.
            $cutoffScore = (float) $marked[$quota - 1]['score'];
            $atOrAbove = $marked->filter(fn (array $r) => (float) $r['score'] >= $cutoffScore)->count();
            if ($atOrAbove > $quota) {
                $hasCutoffTies = true;
            }
        } elseif ($quota === 0 && $presentCount > 0) {
            // Tiny cohort: 15% rounds to 0 — still allow school ranks 1–3, but no district list.
            // Promote is allowed when rank ties are clear; district award list will be empty.
        }

        if ($hasRankTies || $hasCutoffTies) {
            $errors[] = McqSchoolLevelAwardSettings::TIE_ERROR;
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'has_rank_ties' => $hasRankTies,
            'has_cutoff_ties' => $hasCutoffTies,
        ];
    }

    /**
     * @return list<int> awarded registration ids (exact quota, empty if quota 0)
     */
    public function promote(McqExam $exam, string $schoolId, string $className, int $userId): array
    {
        if (! McqSchoolLevelAwardSettings::enabled($exam)) {
            throw ValidationException::withMessages([
                'promote' => 'School-level awards are not enabled for this exam.',
            ]);
        }

        if ($exam->results_published) {
            throw ValidationException::withMessages([
                'promote' => 'Results are published. Unpublish before changing school award submissions.',
            ]);
        }

        if (! McqSchoolLevelAwardSettings::isKgClass($exam, $className)) {
            throw ValidationException::withMessages([
                'promote' => 'This class is not configured for school-level KG awards.',
            ]);
        }

        $classKey = McqClassKey::normalize($className);

        if (McqSchoolLevelAwardSettings::isClassSubmitted($exam, $schoolId, $classKey)) {
            throw ValidationException::withMessages([
                'promote' => 'This class is already submitted. Ask Sahodaya to unlock if marks need re-entry.',
            ]);
        }

        $cfg = McqSchoolLevelAwardSettings::forExam($exam);
        $ranked = $this->rankedRows($exam, $schoolId, $classKey);
        $quota = $this->quota($ranked->count(), (float) $cfg['kg']['cutoff_percent']);
        $validation = $this->validateForPromote($ranked, $cfg['kg'], $quota);

        if (! $validation['ok']) {
            throw ValidationException::withMessages([
                'promote' => $validation['errors'][0] ?? McqSchoolLevelAwardSettings::TIE_ERROR,
            ]);
        }

        $awarded = $ranked
            ->filter(fn (array $r) => $r['score'] !== null)
            ->take($quota)
            ->pluck('registration_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $rankMap = [];
        foreach ([1, 2, 3] as $r) {
            $row = $ranked->firstWhere('school_rank', $r);
            if ($row) {
                $rankMap['rank_'.$r] = (int) $row['registration_id'];
            }
        }

        // Persist school ranks onto marks for reporting while leaving exam-wide
        // publish flow free to re-rank later if needed.
        foreach ($ranked as $row) {
            if ($row['school_rank'] === null) {
                continue;
            }
            $reg = McqRegistration::with('mark')->find($row['registration_id']);
            if ($reg?->mark) {
                $reg->mark->update(['rank' => $row['school_rank']]);
            }
        }

        $settings = $exam->settings_json ?? [];
        $block = is_array($settings['school_level_awards'] ?? null) ? $settings['school_level_awards'] : [];
        $subs = is_array($block['submissions'] ?? null) ? $block['submissions'] : [];
        $subs[$schoolId] = is_array($subs[$schoolId] ?? null) ? $subs[$schoolId] : [];
        $subs[$schoolId][$classKey] = array_merge($rankMap, [
            'status' => 'submitted',
            'submitted_at' => now()->toIso8601String(),
            'submitted_by' => $userId,
            'quota' => $quota,
            'present_count' => $ranked->count(),
            'awarded_registration_ids' => $awarded,
        ]);
        $block['submissions'] = $subs;
        $settings['school_level_awards'] = $block;
        $exam->update(['settings_json' => $settings]);

        app(PlatformAuditLogger::class)->mcq(
            $exam->fresh(),
            'mcq.school_level_award.submitted',
            "School {$schoolId} submitted {$classKey} school-level awards (quota {$quota})",
            [
                'school_id' => $schoolId,
                'class' => $classKey,
                'quota' => $quota,
                'awarded_count' => count($awarded),
            ],
        );

        return $awarded;
    }

    /** Unlock a school+class submission so marks can be re-edited (Sahodaya or after failed attempt path). */
    public function unlock(McqExam $exam, string $schoolId, string $className, int $userId): void
    {
        $classKey = McqClassKey::normalize($className);
        $settings = $exam->settings_json ?? [];
        $block = is_array($settings['school_level_awards'] ?? null) ? $settings['school_level_awards'] : [];
        $subs = is_array($block['submissions'] ?? null) ? $block['submissions'] : [];

        if (! isset($subs[$schoolId][$classKey])) {
            return;
        }

        unset($subs[$schoolId][$classKey]);
        if ($subs[$schoolId] === []) {
            unset($subs[$schoolId]);
        }

        $block['submissions'] = $subs;
        $settings['school_level_awards'] = $block;
        $exam->update(['settings_json' => $settings]);

        app(PlatformAuditLogger::class)->mcq(
            $exam->fresh(),
            'mcq.school_level_award.unlocked',
            "Unlocked school-level awards for school {$schoolId} class {$classKey}",
            ['school_id' => $schoolId, 'class' => $classKey, 'by' => $userId],
        );
    }

    public function assertMarkEditable(McqExam $exam, McqRegistration $registration): void
    {
        if (! McqSchoolLevelAwardSettings::enabled($exam)) {
            return;
        }

        $registration->loadMissing('student.schoolClass');
        $className = $registration->student?->schoolClass?->name;

        if (! McqSchoolLevelAwardSettings::isKgClass($exam, $className)) {
            return;
        }

        if (McqSchoolLevelAwardSettings::isClassSubmitted($exam, (string) $registration->school_id, $className)) {
            throw ValidationException::withMessages([
                'marks' => 'Marks are locked for this class after Promote / Submit. Unlock the submission to re-edit.',
            ]);
        }
    }

    /**
     * Sahodaya overview of school KG submissions (for Results / ops UI).
     *
     * @return list<array{
     *   school_id: string,
     *   school_name: string,
     *   class_name: string,
     *   status: string,
     *   quota: int,
     *   awarded_count: int,
     *   submitted_at: ?string
     * }>
     */
    public function submissionOverview(McqExam $exam): array
    {
        if (! McqSchoolLevelAwardSettings::enabled($exam)) {
            return [];
        }

        $subs = McqSchoolLevelAwardSettings::submissions($exam);
        if ($subs === []) {
            return [];
        }

        $schoolIds = array_keys($subs);
        $schools = \App\Models\Tenant::whereIn('id', $schoolIds)->get(['id', 'name'])->keyBy('id');
        $rows = [];

        foreach ($subs as $schoolId => $byClass) {
            if (! is_array($byClass)) {
                continue;
            }
            foreach ($byClass as $className => $entry) {
                if (! is_array($entry) || ($entry['status'] ?? null) !== 'submitted') {
                    continue;
                }
                $awarded = $entry['awarded_registration_ids'] ?? [];
                $rows[] = [
                    'school_id' => (string) $schoolId,
                    'school_name' => $schools->get($schoolId)?->name ?? (string) $schoolId,
                    'class_name' => (string) $className,
                    'status' => 'submitted',
                    'quota' => (int) ($entry['quota'] ?? 0),
                    'awarded_count' => is_array($awarded) ? count($awarded) : 0,
                    'submitted_at' => $entry['submitted_at'] ?? null,
                ];
            }
        }

        usort($rows, fn ($a, $b) => [$a['school_name'], $a['class_name']] <=> [$b['school_name'], $b['class_name']]);

        return $rows;
    }
}
