<?php

namespace App\Support;

use App\Models\Topper;
use Illuminate\Support\Collection;

class BoardExamSubjects
{
    /** @return array<string, string> */
    public static function class12StreamLabels(): array
    {
        return [
            'bio_science'       => 'Bio Science (PCB)',
            'computer_science'  => 'Computer Science (PCM + CS)',
            'commerce'          => 'Commerce',
            'humanities'        => 'Humanities / Arts',
            'other'             => 'Other / Mixed',
        ];
    }

    /** @return list<string> */
    public static function subjectsForStream(?string $stream): array
    {
        return match ($stream) {
            'bio_science' => [
                'English Core',
                'Physics',
                'Chemistry',
                'Biology',
                'Mathematics',
            ],
            'computer_science' => [
                'English Core',
                'Physics',
                'Chemistry',
                'Mathematics',
                'Computer Science',
            ],
            'commerce' => [
                'English Core',
                'Accountancy',
                'Business Studies',
                'Economics',
                'Mathematics',
            ],
            'humanities' => [
                'English Core',
                'History',
                'Geography',
                'Political Science',
                'Economics',
            ],
            default => [
                'English Core',
                'Mathematics',
                'Physics',
                'Chemistry',
                'Biology',
                'Computer Science',
                'Accountancy',
                'Business Studies',
                'Economics',
            ],
        };
    }

    public static function normalizeStream(?string $stream): ?string
    {
        if ($stream === null || $stream === '') {
            return null;
        }

        $key = strtolower(str_replace([' ', '-'], '_', $stream));

        return array_key_exists($key, self::class12StreamLabels()) ? $key : 'other';
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
     * Highest scorer per subject across all toppers.
     *
     * @return list<array{subject: string, name: string, marks: int, stream: ?string}>
     */
    public static function subjectWiseLeaders(Collection $toppers): array
    {
        $leaders = [];

        foreach ($toppers as $topper) {
            if (! $topper instanceof Topper) {
                continue;
            }

            foreach ($topper->subject_marks ?? [] as $subject => $marks) {
                $marks = (int) $marks;
                if (! isset($leaders[$subject]) || $marks > $leaders[$subject]['marks']) {
                    $leaders[$subject] = [
                        'subject' => $subject,
                        'name'    => $topper->name,
                        'marks'   => $marks,
                        'stream'  => $topper->stream,
                    ];
                }
            }
        }

        ksort($leaders);

        return array_values($leaders);
    }
}
