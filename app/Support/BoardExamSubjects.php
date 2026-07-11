<?php

namespace App\Support;

use App\Models\ExamStream;
use App\Models\Topper;
use App\Services\BoardResults\TopperSubjectMarkService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge for Class XII streams/subjects.
 * Prefers exam_streams master; falls back to built-in defaults when table missing.
 */
class BoardExamSubjects
{
    /** @return array<string, string> */
    public static function class12StreamLabels(?string $sahodayaId = null): array
    {
        if (self::streamsTableReady()) {
            $labels = ExamStream::labelsFor($sahodayaId);
            if ($labels !== []) {
                return $labels;
            }
        }

        return self::fallbackLabels();
    }

    /** @return list<string> */
    public static function subjectsForStream(?string $stream, ?string $sahodayaId = null): array
    {
        if ($stream && self::streamsTableReady()) {
            $row = ExamStream::findByCode($stream, $sahodayaId);
            if ($row && is_array($row->default_subjects) && $row->default_subjects !== []) {
                return $row->default_subjects;
            }
        }

        return match ($stream) {
            'bio_science' => ['English Core', 'Physics', 'Chemistry', 'Biology', 'Mathematics'],
            'computer_science' => ['English Core', 'Physics', 'Chemistry', 'Mathematics', 'Computer Science'],
            'commerce' => ['English Core', 'Accountancy', 'Business Studies', 'Economics', 'Mathematics'],
            'humanities' => ['English Core', 'History', 'Geography', 'Political Science', 'Economics'],
            default => ['English Core', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Accountancy', 'Business Studies', 'Economics'],
        };
    }

    public static function normalizeStream(?string $stream, ?string $sahodayaId = null): ?string
    {
        if ($stream === null || $stream === '') {
            return null;
        }

        $key = strtolower(str_replace([' ', '-'], '_', $stream));
        $labels = self::class12StreamLabels($sahodayaId);

        return array_key_exists($key, $labels) ? $key : 'other';
    }

    public static function resolveStreamId(?string $streamKey, ?string $sahodayaId = null): ?int
    {
        if (! $streamKey || ! self::streamsTableReady()) {
            return null;
        }

        return ExamStream::findByCode($streamKey, $sahodayaId)?->id;
    }

    /** @param  array<string, mixed>  $raw */
    public static function normalizeSubjectMarks(array $raw): array
    {
        $clean = [];

        foreach ($raw as $subject => $mark) {
            $subject = trim((string) $subject);
            if ($subject === '' || $mark === '' || $mark === null) {
                continue;
            }

            $value = is_numeric($mark) ? (int) $mark : null;
            if ($value === null || $value < 0 || $value > 100) {
                continue;
            }

            $clean[$subject] = $value;
        }

        return $clean;
    }

    /**
     * @return list<array{subject: string, name: string, marks: int|float, stream: ?string, subject_id?: ?int}>
     */
    public static function subjectWiseLeaders(Collection $toppers): array
    {
        return app(TopperSubjectMarkService::class)->subjectWiseLeaders($toppers);
    }

    private static function streamsTableReady(): bool
    {
        try {
            return Schema::hasTable('exam_streams');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, string> */
    private static function fallbackLabels(): array
    {
        return [
            'bio_science' => 'Bio Science (PCB)',
            'computer_science' => 'Computer Science (PCM + CS)',
            'commerce' => 'Commerce',
            'humanities' => 'Humanities / Arts',
            'other' => 'Other / Mixed',
        ];
    }
}
