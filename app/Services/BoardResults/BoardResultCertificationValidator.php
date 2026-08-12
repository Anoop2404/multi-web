<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Topper;

/**
 * Completeness checks that must pass before a school can send a result for
 * Principal Verification — plan §8. Returns a flat list of human-readable
 * error strings; an empty list means the result is ready.
 */
class BoardResultCertificationValidator
{
    /** @return list<string> */
    public function errorsBeforeLeadershipReview(BoardResult $boardResult): array
    {
        $errors = [];

        if (! $boardResult->hasResultPdf()) {
            $errors[] = 'Upload the result proof document before sending for Principal Verification.';
        }

        if ((int) $boardResult->total_appeared <= 0) {
            $errors[] = 'Total appeared must be greater than zero.';
        }

        if ((int) $boardResult->pass_count > (int) $boardResult->total_appeared) {
            $errors[] = 'Total passed cannot exceed total appeared.';
        }

        if (((int) $boardResult->distinctions + (int) $boardResult->first_class) > (int) $boardResult->pass_count) {
            $errors[] = 'Distinctions and first-class counts together cannot exceed total passed.';
        }

        $expectedPassPercent = (int) $boardResult->total_appeared > 0
            ? round(((int) $boardResult->pass_count / (int) $boardResult->total_appeared) * 100, 2)
            : 0.0;
        if (abs((float) $boardResult->pass_percent - $expectedPassPercent) > 0.05) {
            $errors[] = "Pass percentage ({$boardResult->pass_percent}%) does not match appeared/passed counts (expected {$expectedPassPercent}%). Re-save the result to recompute it.";
        }

        $overallToppers = $boardResult->toppers()->overallEntries()->get();
        foreach ($overallToppers as $topper) {
            $missing = $this->missingTopperFields($boardResult, $topper);
            if ($missing !== []) {
                $errors[] = "Topper \"{$topper->name}\" is missing: ".implode(', ', $missing).'.';
            }
        }

        $fullA1 = $boardResult->toppers()->fullA1Entries()->with('subjectMarks')->get();
        foreach ($fullA1 as $topper) {
            foreach ($topper->subject_marks as $subject => $marks) {
                if ($marks < 0 || $marks > 100) {
                    $errors[] = "Full A1 entry \"{$topper->name}\" has {$subject} = {$marks}, which is outside the valid 0-100 mark range.";
                }
            }
        }

        $errors = array_merge($errors, $this->duplicateRollNumberErrors($boardResult, Topper::ENTRY_OVERALL));
        $errors = array_merge($errors, $this->duplicateRollNumberErrors($boardResult, Topper::ENTRY_FULL_A1));

        return $errors;
    }

    /** @return list<string> */
    private function missingTopperFields(BoardResult $boardResult, Topper $topper): array
    {
        $missing = [];

        if (blank($topper->name)) {
            $missing[] = 'name';
        }
        if (blank($topper->gender)) {
            $missing[] = 'gender';
        }
        if ($topper->marks_obtained === null) {
            $missing[] = 'marks obtained';
        }
        if ($topper->total_marks === null) {
            $missing[] = 'configured total marks';
        }
        if ((int) $boardResult->class === 12 && blank($topper->stream_id)) {
            $missing[] = 'stream';
        }

        return $missing;
    }

    /** @return list<string> */
    private function duplicateRollNumberErrors(BoardResult $boardResult, string $entryType): array
    {
        $rollNumbers = $boardResult->toppers()
            ->where('entry_type', $entryType)
            ->whereNotNull('roll_no')
            ->where('roll_no', '!=', '')
            ->pluck('roll_no');

        $duplicates = $rollNumbers->duplicates()->unique();

        return $duplicates->map(fn ($roll) => "Duplicate roll number \"{$roll}\" found among ".str_replace('_', ' ', $entryType).' entries.')->values()->all();
    }
}
