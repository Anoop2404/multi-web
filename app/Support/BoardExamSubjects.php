<?php

namespace App\Support;

use App\Models\ExamStream;
use App\Services\BoardResults\TopperSubjectMarkService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Class XII stream/subject helpers — reads exclusively from exam_streams master (#137).
 */
class BoardExamSubjects
{
    /** @return array<string, string> */
    public static function class12StreamLabels(?string $sahodayaId = null): array
    {
        self::assertStreamsReady();

        $labels = ExamStream::labelsFor($sahodayaId);
        if ($labels === []) {
            throw new RuntimeException('No active exam streams configured. Seed exam_streams before managing Class XII toppers.');
        }

        return $labels;
    }

    /** @return list<string> */
    public static function subjectsForStream(?string $stream, ?string $sahodayaId = null): array
    {
        if (! $stream) {
            return [];
        }

        self::assertStreamsReady();

        $row = ExamStream::findByCode($stream, $sahodayaId);
        if (! $row) {
            return [];
        }

        return is_array($row->default_subjects) ? array_values($row->default_subjects) : [];
    }

    public static function normalizeStream(?string $stream, ?string $sahodayaId = null): ?string
    {
        if ($stream === null || $stream === '') {
            return null;
        }

        $key = strtolower(str_replace([' ', '-'], '_', $stream));
        $labels = self::class12StreamLabels($sahodayaId);

        return array_key_exists($key, $labels) ? $key : (array_key_exists('other', $labels) ? 'other' : null);
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

    private static function assertStreamsReady(): void
    {
        if (! self::streamsTableReady()) {
            throw new RuntimeException('exam_streams table is missing. Run tenant migrations.');
        }
    }

    private static function streamsTableReady(): bool
    {
        try {
            return Schema::hasTable('exam_streams');
        } catch (\Throwable) {
            return false;
        }
    }
}
