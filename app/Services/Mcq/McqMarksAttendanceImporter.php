<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Services\Spreadsheet\SpreadsheetReader;
use Illuminate\Http\UploadedFile;

/**
 * Excel/CSV import for Talent Search attendance and marks.
 *
 * Reg. numbers (hall_ticket_no) are roll numbers scoped to a class, shared exam-wide
 * across every school — "Roll 1" exists once per class, so a ticket number alone does
 * NOT identify a unique registration. Every row must also carry the candidate's class,
 * and rows are matched on (hall_ticket_no, class) together (see findRegistration()).
 *
 * Attendance columns (header aliases supported):
 *   hall_ticket_no | ticket, class, attendance_status | status, note (optional)
 *
 * Marks columns:
 *   hall_ticket_no | ticket, class, correct, wrong, unanswered
 *   optional: score, marks_per_correct, negative_per_wrong
 *
 * Scoring when score column absent (defaults match McqExamSessionService):
 *   score = correct × marks_per_correct − wrong × negative_per_wrong
 *   max   = (correct + wrong + unanswered) × marks_per_correct
 *   percentage = max > 0 ? max(0, score) / max × 100 : 0
 */
class McqMarksAttendanceImporter
{
    /** @var array<string, list<string>> */
    private const ATTENDANCE_ALIASES = [
        'ticket' => ['hall_ticket_no', 'hall ticket no', 'ticket', 'reg_no', 'reg no', 'registration_no'],
        'class'  => ['class', 'class_name', 'class name', 'student_class'],
        'status' => ['attendance_status', 'attendance status', 'status', 'attendance'],
        'note'   => ['note', 'attendance_note', 'attendance note', 'reason', 'remarks'],
    ];

    /** @var array<string, list<string>> */
    private const MARKS_ALIASES = [
        'ticket'     => ['hall_ticket_no', 'hall ticket no', 'ticket', 'reg_no', 'reg no'],
        'class'      => ['class', 'class_name', 'class name', 'student_class'],
        'correct'    => ['correct', 'correct_count', 'correct count'],
        'wrong'      => ['wrong', 'wrong_count', 'wrong count', 'incorrect'],
        'unanswered' => ['unanswered', 'unanswered_count', 'unanswered count', 'skipped'],
        'score'      => ['score', 'marks', 'total_score'],
        'marks_each' => ['marks_per_correct', 'marks per correct', 'mark_per_question', 'marks_each'],
        'neg_each'   => ['negative_per_wrong', 'negative per wrong', 'negative_mark', 'neg_each'],
    ];

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>, success: bool}
     */
    public function importAttendance(UploadedFile|string $file, McqExam $exam, int $markedByUserId): array
    {
        $path = $file instanceof UploadedFile ? ($file->getRealPath() ?: $file->getPathname()) : $file;
        $imported = 0;
        $errors = [];
        $rowNumber = 0;
        $headerMap = null;
        $headerConsumed = false;

        foreach (SpreadsheetReader::rows($path) as $cols) {
            $rowNumber++;

            if ($headerMap === null) {
                $mapped = $this->mapHeader($cols, self::ATTENDANCE_ALIASES, ['ticket', 'class', 'status']);
                $maybeStatus = strtolower(trim((string) ($cols[$mapped['status'] ?? 2] ?? '')));
                if ($mapped !== null && ! in_array($maybeStatus, ['present', 'absent', 'malpractice', 'withheld', 'pending'], true)) {
                    $headerMap = $mapped;
                    $headerConsumed = true;

                    continue;
                }
                $headerMap = $mapped ?? ['ticket' => 0, 'class' => 1, 'status' => 2, 'note' => 3];
            }

            if ($this->rowIsEmpty($cols)) {
                continue;
            }

            $ticket = trim((string) ($cols[$headerMap['ticket']] ?? ''));
            $class = trim((string) ($cols[$headerMap['class']] ?? ''));
            $status = strtolower(trim((string) ($cols[$headerMap['status']] ?? '')));
            $note = trim((string) ($cols[$headerMap['note'] ?? -1] ?? ''));

            if ($ticket === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Missing hall ticket / reg. no.'];

                continue;
            }

            if ($class === '') {
                $errors[] = ['row' => $rowNumber, 'message' => "Missing class for ticket {$ticket} — reg. numbers repeat across classes, so class is required to identify the candidate."];

                continue;
            }

            if (! in_array($status, ['present', 'absent', 'malpractice', 'withheld'], true)) {
                $errors[] = ['row' => $rowNumber, 'message' => "Invalid attendance status “{$status}” for ticket {$ticket}."];

                continue;
            }

            if (in_array($status, ['malpractice', 'withheld'], true) && $note === '') {
                $errors[] = ['row' => $rowNumber, 'message' => "Note required for {$status} (ticket {$ticket})."];

                continue;
            }

            [$registration, $matchError] = $this->findRegistration($exam, $ticket, $class);

            if (! $registration) {
                $errors[] = ['row' => $rowNumber, 'message' => $matchError];

                continue;
            }

            $registration->update([
                'attendance_status'    => $status,
                'attendance_note'      => $note !== '' ? $note : null,
                'attendance_marked_at' => now(),
                'attendance_marked_by' => $markedByUserId,
            ]);

            if ($registration->blocksScoring() && $registration->mark) {
                $registration->mark()->delete();
                $registration->update(['status' => 'registered', 'submitted_at' => null]);
            }

            $imported++;
        }

        return [
            'imported' => $imported,
            'errors'   => $errors,
            'success'  => $imported > 0 || ($errors === [] && $headerConsumed),
        ];
    }

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>, success: bool}
     */
    public function importMarks(UploadedFile|string $file, McqExam $exam): array
    {
        if ($exam->results_published) {
            return [
                'imported' => 0,
                'errors'   => [['row' => 0, 'message' => 'Results are published. Unpublish before importing marks.']],
                'success'  => false,
            ];
        }

        $path = $file instanceof UploadedFile ? ($file->getRealPath() ?: $file->getPathname()) : $file;
        $imported = 0;
        $errors = [];
        $rowNumber = 0;
        $headerMap = null;
        $gradeService = app(McqGradeService::class);

        foreach (SpreadsheetReader::rows($path) as $cols) {
            $rowNumber++;

            if ($headerMap === null) {
                $mapped = $this->mapHeader($cols, self::MARKS_ALIASES, ['ticket', 'class', 'correct', 'wrong', 'unanswered']);
                $maybeCorrect = $cols[$mapped['correct'] ?? 2] ?? null;
                if ($mapped !== null && ! is_numeric(trim((string) $maybeCorrect))) {
                    $headerMap = $mapped;

                    continue;
                }
                $headerMap = $mapped ?? ['ticket' => 0, 'class' => 1, 'correct' => 2, 'wrong' => 3, 'unanswered' => 4];
            }

            if ($this->rowIsEmpty($cols)) {
                continue;
            }

            $ticket = trim((string) ($cols[$headerMap['ticket']] ?? ''));
            if ($ticket === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Missing hall ticket / reg. no.'];

                continue;
            }

            $class = trim((string) ($cols[$headerMap['class']] ?? ''));
            if ($class === '') {
                $errors[] = ['row' => $rowNumber, 'message' => "Missing class for ticket {$ticket} — reg. numbers repeat across classes, so class is required to identify the candidate."];

                continue;
            }

            [$registration, $matchError] = $this->findRegistration($exam, $ticket, $class);

            if (! $registration) {
                $errors[] = ['row' => $rowNumber, 'message' => $matchError];

                continue;
            }

            if ($registration->blocksScoring()) {
                $errors[] = ['row' => $rowNumber, 'message' => "Ticket {$ticket}: attendance blocks scoring ({$registration->attendance_status})."];

                continue;
            }

            $correct = (int) ($cols[$headerMap['correct']] ?? 0);
            $wrong = (int) ($cols[$headerMap['wrong']] ?? 0);
            $unanswered = (int) ($cols[$headerMap['unanswered']] ?? 0);
            $marksEach = isset($headerMap['marks_each']) && is_numeric($cols[$headerMap['marks_each']] ?? null)
                ? (float) $cols[$headerMap['marks_each']]
                : 1.0;
            $negEach = isset($headerMap['neg_each']) && is_numeric($cols[$headerMap['neg_each']] ?? null)
                ? (float) $cols[$headerMap['neg_each']]
                : 0.0;

            $total = $correct + $wrong + $unanswered;
            $computedScore = round(($correct * $marksEach) - ($wrong * $negEach), 2);
            $maxScore = round($total * $marksEach, 2);

            $score = isset($headerMap['score']) && is_numeric($cols[$headerMap['score']] ?? null)
                ? round((float) $cols[$headerMap['score']], 2)
                : $computedScore;

            $percentage = $maxScore > 0 ? round((max(0, $score) / $maxScore) * 100, 2) : 0;
            $grade = $gradeService->gradeForPercentage($exam, $percentage);

            McqMark::updateOrCreate(
                ['registration_id' => $registration->id],
                [
                    'correct_count'    => $correct,
                    'wrong_count'      => $wrong,
                    'unanswered_count' => $unanswered,
                    'score'            => $score,
                    'percentage'       => $percentage,
                    'grade'            => $grade,
                    'locked_at'        => now(),
                ]
            );
            $registration->update(['status' => 'submitted', 'submitted_at' => now()]);
            $imported++;
        }

        if ($imported > 0) {
            app(McqRankingService::class)->rankExam($exam);
        }

        return [
            'imported' => $imported,
            'errors'   => $errors,
            'success'  => $imported > 0 || $errors === [],
        ];
    }

    /**
     * hall_ticket_no is a roll number scoped to a class (shared exam-wide across every
     * school), so it does not identify a unique registration on its own — every lookup
     * must also match the row's class against the candidate's actual class (or "Teacher"
     * for a teacher registration).
     *
     * @return array{0: ?McqRegistration, 1: ?string}
     */
    private function findRegistration(McqExam $exam, string $ticket, string $classInput): array
    {
        $candidates = McqRegistration::where('exam_id', $exam->id)
            ->where('hall_ticket_no', $ticket)
            ->with(['student.schoolClass', 'teacher'])
            ->get();

        if ($candidates->isEmpty()) {
            return [null, "No registration found for ticket {$ticket}."];
        }

        $target = strtolower(trim(str_ireplace('class', '', $classInput)));

        $matches = $candidates->filter(function (McqRegistration $reg) use ($target) {
            if ($reg->isTeacherRegistration()) {
                return $target === 'teacher';
            }

            $name = strtolower(trim(str_ireplace('class', '', (string) ($reg->student?->schoolClass?->name ?? ''))));

            return $name === $target;
        });

        if ($matches->count() === 1) {
            return [$matches->first(), null];
        }

        if ($matches->isEmpty()) {
            return [null, "Ticket {$ticket}: no candidate in class “{$classInput}” — check the class spelling matches the school's class name exactly."];
        }

        return [null, "Ticket {$ticket}: matched more than one candidate in class “{$classInput}” — this needs manual review."];
    }

    /**
     * @param  list<string|null>  $cols
     * @param  array<string, list<string>>  $aliases
     * @param  list<string>  $required
     * @return array<string, int>|null
     */
    private function mapHeader(array $cols, array $aliases, array $required): ?array
    {
        $normalized = [];
        foreach ($cols as $i => $col) {
            $key = strtolower(trim(str_replace(['_', '-'], ' ', (string) $col)));
            $normalized[$key] = $i;
        }

        $map = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $needle = strtolower(str_replace(['_', '-'], ' ', $name));
                if (isset($normalized[$needle])) {
                    $map[$field] = $normalized[$needle];
                    break;
                }
            }
        }

        foreach ($required as $field) {
            if (! isset($map[$field])) {
                return null;
            }
        }

        return $map;
    }

    /** @param  list<string|null>  $cols */
    private function rowIsEmpty(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }
}
