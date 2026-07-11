<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;

/**
 * Orchestrates post-publish computation: Ranking → API → Awards → Certificates → subject_stats.
 */
class BoardResultPublishPipeline
{
    public function __construct(
        private RankingEngine $ranking,
        private AcademicPerformanceIndexEngine $api,
        private AwardsEngine $awards,
        private TopperCertificateService $certificates,
        private SubjectStatsNormalizer $subjectStats,
    ) {}

    /**
     * @return array{
     *   ranking: array{scopes: list<string>, rows: int},
     *   api: array{rows: int},
     *   awards: array{awards: int, achievements?: int},
     *   certificates: int
     * }
     */
    public function run(string $sahodayaId, string $academicYear, ?BoardResult $boardResult = null): array
    {
        $ranking = $this->ranking->recompute($sahodayaId, $academicYear);
        $api = $this->api->recompute($sahodayaId, $academicYear);
        $awards = $this->awards->recompute($sahodayaId, $academicYear);

        $certificates = 0;
        if ($boardResult) {
            $this->subjectStats->rebuild($boardResult->fresh(['toppers']));
            $certificates = $this->certificates->issueForBoardResult(
                $boardResult->fresh(['toppers']),
                $sahodayaId
            );
        }

        return compact('ranking', 'api', 'awards', 'certificates');
    }
}
