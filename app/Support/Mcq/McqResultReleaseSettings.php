<?php

namespace App\Support\Mcq;

use App\Models\McqExam;
use Illuminate\Support\Carbon;

/**
 * Result release + certificate settings stored under settings_json.
 *
 * Keys:
 * - result_release.visibility: score | score_grade | review
 * - result_release.auto_release_at: ISO8601|null
 * - certificates.enabled
 * - certificates.auto_issue_on_publish
 * - certificates.eligibility: participation | pass | grade_bands
 * - certificates.eligible_grades: list of grade labels (when grade_bands)
 */
final class McqResultReleaseSettings
{
    public const VISIBILITY_SCORE = 'score';

    public const VISIBILITY_SCORE_GRADE = 'score_grade';

    public const VISIBILITY_REVIEW = 'review';

    public const VISIBILITIES = [
        self::VISIBILITY_SCORE,
        self::VISIBILITY_SCORE_GRADE,
        self::VISIBILITY_REVIEW,
    ];

    public const RELEASE_MODE_ON_PUBLISH = 'on_publish';

    public const RELEASE_MODE_AFTER_COMPLETION = 'after_completion';

    public const RELEASE_MODES = [
        self::RELEASE_MODE_ON_PUBLISH,
        self::RELEASE_MODE_AFTER_COMPLETION,
    ];

    public const CERT_ELIGIBILITY_PARTICIPATION = 'participation';

    public const CERT_ELIGIBILITY_PASS = 'pass';

    public const CERT_ELIGIBILITY_GRADE_BANDS = 'grade_bands';

    public const CERT_ELIGIBILITIES = [
        self::CERT_ELIGIBILITY_PARTICIPATION,
        self::CERT_ELIGIBILITY_PASS,
        self::CERT_ELIGIBILITY_GRADE_BANDS,
    ];

    /** @return array{visibility: string, release_mode: string, auto_release_at: ?string} */
    public static function resultRelease(McqExam $exam): array
    {
        $block = $exam->settings_json['result_release'] ?? [];

        $visibility = $block['visibility'] ?? self::VISIBILITY_SCORE_GRADE;
        if (! in_array($visibility, self::VISIBILITIES, true)) {
            $visibility = self::VISIBILITY_SCORE_GRADE;
        }

        $releaseMode = $block['release_mode'] ?? self::RELEASE_MODE_ON_PUBLISH;
        if (! in_array($releaseMode, self::RELEASE_MODES, true)) {
            $releaseMode = self::RELEASE_MODE_ON_PUBLISH;
        }

        $auto = $block['auto_release_at'] ?? null;
        if (is_string($auto) && trim($auto) !== '') {
            try {
                $auto = Carbon::parse($auto)->toIso8601String();
            } catch (\Throwable) {
                $auto = null;
            }
        } else {
            $auto = null;
        }

        return [
            'visibility' => $visibility,
            'release_mode' => $releaseMode,
            'auto_release_at' => $auto,
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   auto_issue_on_publish: bool,
     *   eligibility: string,
     *   eligible_grades: list<string>
     * }
     */
    public static function certificates(McqExam $exam): array
    {
        $block = $exam->settings_json['certificates'] ?? [];
        $explicit = array_key_exists('enabled', $block);

        // Talent Search historically always allowed certificates after publish.
        // Model exams opt in explicitly (privacy / high-stakes default off).
        $enabled = $explicit
            ? (bool) $block['enabled']
            : McqProductMode::isTalentSearch($exam);

        $eligibility = $block['eligibility'] ?? self::CERT_ELIGIBILITY_PARTICIPATION;
        if (! in_array($eligibility, self::CERT_ELIGIBILITIES, true)) {
            $eligibility = self::CERT_ELIGIBILITY_PARTICIPATION;
        }

        $grades = $block['eligible_grades'] ?? [];
        if (! is_array($grades)) {
            $grades = [];
        }
        $grades = array_values(array_filter(array_map(
            static fn ($g) => is_scalar($g) ? trim((string) $g) : '',
            $grades,
        )));

        return [
            'enabled' => $enabled,
            'auto_issue_on_publish' => (bool) ($block['auto_issue_on_publish'] ?? false),
            'eligibility' => $eligibility,
            'eligible_grades' => $grades,
        ];
    }

    public static function visibility(McqExam $exam): string
    {
        return self::resultRelease($exam)['visibility'];
    }

    public static function releaseMode(McqExam $exam): string
    {
        return self::resultRelease($exam)['release_mode'];
    }

    /**
     * Determines whether results (scores/feedback) are accessible to students.
     * When release_mode is `after_completion`, any student with status `submitted` sees their result immediately.
     * Otherwise, results require results_published = true (manual publish or auto-release schedule).
     */
    public static function isResultVisible(McqExam $exam, ?\App\Models\McqRegistration $registration = null): bool
    {
        if ((bool) $exam->results_published) {
            return true;
        }

        if (self::releaseMode($exam) === self::RELEASE_MODE_AFTER_COMPLETION) {
            return $registration ? $registration->status === 'submitted' : true;
        }

        return false;
    }

    public static function showsGrade(McqExam $exam): bool
    {
        return in_array(self::visibility($exam), [self::VISIBILITY_SCORE_GRADE, self::VISIBILITY_REVIEW], true);
    }

    public static function showsReview(McqExam $exam): bool
    {
        return self::visibility($exam) === self::VISIBILITY_REVIEW;
    }

    public static function certificatesEnabled(McqExam $exam): bool
    {
        return self::certificates($exam)['enabled'];
    }

    public static function autoIssueOnPublish(McqExam $exam): bool
    {
        return self::certificates($exam)['auto_issue_on_publish'];
    }

    public static function autoReleaseAt(McqExam $exam): ?Carbon
    {
        $raw = self::resultRelease($exam)['auto_release_at'];
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalize admin form input into settings_json patches.
     *
     * @param  array<string, mixed>  $input
     * @return array{result_release: array<string, mixed>, certificates: array<string, mixed>}
     */
    public static function normalizeInput(array $input, McqExam $exam): array
    {
        $currentRelease = self::resultRelease($exam);
        $currentCerts = self::certificates($exam);

        $visibility = $input['result_visibility'] ?? $currentRelease['visibility'];
        if (! in_array($visibility, self::VISIBILITIES, true)) {
            $visibility = self::VISIBILITY_SCORE_GRADE;
        }

        $releaseMode = $input['results_release_mode'] ?? $input['release_mode'] ?? $currentRelease['release_mode'];
        if (! in_array($releaseMode, self::RELEASE_MODES, true)) {
            $releaseMode = self::RELEASE_MODE_ON_PUBLISH;
        }

        $autoRelease = $input['results_auto_release_at'] ?? null;
        if (is_string($autoRelease) && trim($autoRelease) !== '') {
            try {
                $autoRelease = Carbon::parse($autoRelease)->toIso8601String();
            } catch (\Throwable) {
                $autoRelease = null;
            }
        } else {
            $autoRelease = null;
        }

        $eligibility = $input['certificate_eligibility'] ?? $currentCerts['eligibility'];
        if (! in_array($eligibility, self::CERT_ELIGIBILITIES, true)) {
            $eligibility = self::CERT_ELIGIBILITY_PARTICIPATION;
        }

        $grades = $input['certificate_eligible_grades'] ?? $currentCerts['eligible_grades'];
        if (! is_array($grades)) {
            $grades = [];
        }
        $grades = array_values(array_filter(array_map(
            static fn ($g) => is_scalar($g) ? trim((string) $g) : '',
            $grades,
        )));

        $enabled = array_key_exists('certificates_enabled', $input)
            ? (bool) $input['certificates_enabled']
            : $currentCerts['enabled'];

        $autoIssue = array_key_exists('certificates_auto_issue', $input)
            ? (bool) $input['certificates_auto_issue']
            : $currentCerts['auto_issue_on_publish'];

        return [
            'result_release' => [
                'visibility' => $visibility,
                'release_mode' => $releaseMode,
                'auto_release_at' => $autoRelease,
            ],
            'certificates' => [
                'enabled' => $enabled,
                'auto_issue_on_publish' => $autoIssue,
                'eligibility' => $eligibility,
                'eligible_grades' => $grades,
            ],
        ];
    }

    /** Validation rules fragment for admin update forms. */
    public static function validationRules(): array
    {
        return [
            'result_visibility' => 'nullable|in:'.implode(',', self::VISIBILITIES),
            'results_release_mode' => 'nullable|in:'.implode(',', self::RELEASE_MODES),
            'results_auto_release_at' => 'nullable|date',
            'certificates_enabled' => 'nullable|boolean',
            'certificates_auto_issue' => 'nullable|boolean',
            'certificate_eligibility' => 'nullable|in:'.implode(',', self::CERT_ELIGIBILITIES),
            'certificate_eligible_grades' => 'nullable|array',
            'certificate_eligible_grades.*' => 'string|max:20',
            'certificate_template_id' => 'nullable|integer|exists:mcq_certificate_templates,id',
        ];
    }

    /** @return array<string, mixed> payload for Inertia exam props */
    public static function forInertia(McqExam $exam): array
    {
        $release = self::resultRelease($exam);
        $certs = self::certificates($exam);

        return [
            'result_visibility' => $release['visibility'],
            'results_release_mode' => $release['release_mode'],
            'results_auto_release_at' => $release['auto_release_at'],
            'certificates_enabled' => $certs['enabled'],
            'certificates_auto_issue' => $certs['auto_issue_on_publish'],
            'certificate_eligibility' => $certs['eligibility'],
            'certificate_eligible_grades' => $certs['eligible_grades'],
            'certificate_template_id' => $exam->certificate_template_id,
        ];
    }

    public static function visibilityLabel(string $visibility): string
    {
        return match ($visibility) {
            self::VISIBILITY_SCORE => 'Score only',
            self::VISIBILITY_REVIEW => 'Score, grade & per-question review',
            default => 'Score & grade',
        };
    }

    public static function releaseModeLabel(string $mode): string
    {
        return match ($mode) {
            self::RELEASE_MODE_AFTER_COMPLETION => 'Immediately after completion of exam',
            default => 'When results are published (manual release)',
        };
    }
}
