<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Support\Mcq\McqResultReleaseSettings;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Carbon;

/**
 * Shared publish / hold / auto-release for Talent Search and model exams.
 */
class McqResultPublishService
{
    public function __construct(
        private McqRankingService $ranking,
        private McqCertificateService $certificates,
    ) {}

    /**
     * Rank + publish results; optionally auto-issue certificates.
     *
     * @return array{ranked: int, certificates_issued: int}
     */
    public function publish(McqExam $exam, ?string $auditActorLabel = null): array
    {
        $ranked = $this->ranking->rankExam($exam);

        $exam->update([
            'results_published' => true,
            'results_published_at' => now(),
        ]);
        $exam->refresh();

        $issued = 0;
        if (McqResultReleaseSettings::certificatesEnabled($exam)
            && McqResultReleaseSettings::autoIssueOnPublish($exam)
        ) {
            $issued = $this->certificates->issueBulk($exam);
        }

        if ($exam->tenant_id && ! $exam->isSchoolModelExam()) {
            app(McqExamNotifier::class)->resultsPublished($exam);
        }

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.results.published',
            ($auditActorLabel ? "{$auditActorLabel}: " : '')."Results published for {$exam->title}",
            ['ranked' => $ranked, 'certificates_issued' => $issued],
        );

        return ['ranked' => $ranked, 'certificates_issued' => $issued];
    }

    public function unpublish(McqExam $exam, ?string $auditActorLabel = null): void
    {
        $exam->update(['results_published' => false]);

        app(PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.results.unpublished',
            ($auditActorLabel ? "{$auditActorLabel}: " : '')."Results unpublished for {$exam->title}",
        );
    }

    /**
     * Publish exams whose auto_release_at has passed.
     *
     * @return int number of exams released
     */
    public function releaseDueExams(?Carbon $now = null): int
    {
        $now ??= now();
        $released = 0;

        McqExam::query()
            ->where('results_published', false)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->whereNotNull('settings_json')
            ->orderBy('id')
            ->chunkById(50, function ($exams) use ($now, &$released) {
                foreach ($exams as $exam) {
                    $at = McqResultReleaseSettings::autoReleaseAt($exam);
                    if (! $at || $at->gt($now)) {
                        continue;
                    }

                    $this->publish($exam, 'Auto-release');
                    $released++;
                }
            });

        return $released;
    }
}
