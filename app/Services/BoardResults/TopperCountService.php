<?php

namespace App\Services\BoardResults;

use App\Models\BoardResult;
use App\Models\Topper;
use App\Models\TopperCountConfig;
use App\Models\TopperRankingSetting;
use Illuminate\Validation\ValidationException;

class TopperCountService
{
    public const DEFAULT_TOP_N = 500;

    /** @var array<string, TopperRankingSetting> per-request memoization, keyed by sahodaya_id */
    private array $rankingSettingsCache = [];

    /**
     * @var array<string, bool> per-request "view=rank|percentage" override, keyed by sahodaya_id.
     *      Lets a single report request preview the other mode without touching the persisted
     *      TopperRankingSetting row. This service is bound as a singleton (AppServiceProvider) so
     *      the override set once by the controller is visible everywhere else it's resolved.
     */
    private array $noRankOverride = [];

    private function rankingSettings(string $sahodayaId): TopperRankingSetting
    {
        return $this->rankingSettingsCache[$sahodayaId] ??= TopperRankingSetting::forSahodaya($sahodayaId);
    }

    /** Set (or clear, with null) a per-request no-rank override for this sahodaya, independent of the saved setting. */
    public function setNoRankOverride(string $sahodayaId, ?bool $noRank): void
    {
        if ($noRank === null) {
            unset($this->noRankOverride[$sahodayaId]);

            return;
        }

        $this->noRankOverride[$sahodayaId] = $noRank;
    }

    /** When true, every scope (stream/subject) resolves from the single "overall" config instead of its own override. */
    public function usesCommonRanking(string $sahodayaId): bool
    {
        return (bool) $this->rankingSettings($sahodayaId)->use_common_ranking;
    }

    /** When true, reports should drop rank numbers and just order by percentage descending. */
    public function isNoRankMode(string $sahodayaId): bool
    {
        if (array_key_exists($sahodayaId, $this->noRankOverride)) {
            return $this->noRankOverride[$sahodayaId];
        }

        return (bool) $this->rankingSettings($sahodayaId)->no_rank;
    }

    /**
     * @param  array{0: string, 1: ?int, 2: ?int}  $args  [scope, streamId, subjectId], overridden to
     *                                                     the shared "overall" config when common ranking is on.
     * @return array{0: string, 1: ?int, 2: ?int}
     */
    private function effectiveScope(string $sahodayaId, string $scope, ?int $streamId, ?int $subjectId): array
    {
        if ($this->usesCommonRanking($sahodayaId)) {
            return [TopperCountConfig::SCOPE_OVERALL, null, null];
        }

        return [$scope, $streamId, $subjectId];
    }

    public function resolveCap(
        string $sahodayaId,
        int $class,
        string $scope = TopperCountConfig::SCOPE_OVERALL,
        ?int $streamId = null,
        ?int $subjectId = null,
        ?string $academicYear = null,
    ): int
    {
        [$scope, $streamId, $subjectId] = $this->effectiveScope($sahodayaId, $scope, $streamId, $subjectId);

        $query = $this->scopedConfigQuery($sahodayaId, $class, $scope, $streamId, $subjectId, $academicYear);

        $config = $query
            ->orderByRaw('academic_year is null') // prefer year-specific override
            ->orderByRaw('class is null') // prefer class-specific
            ->orderByRaw('stream_id is null')
            ->orderByRaw('subject_id is null')
            ->first();

        return max(1, (int) ($config?->top_n ?? self::DEFAULT_TOP_N));
    }

    /**
     * Shared "which config row applies" query used by resolveCap/resolveTieMode/resolveRankStyle.
     * When $academicYear is null (the default, matching every pre-existing call site), only the
     * global (academic_year IS NULL) row is eligible — behavior is unchanged from before the
     * academic_year column existed. When a year is passed, a row explicit to that year is
     * preferred, falling back to the global row if no year-specific override exists.
     */
    private function scopedConfigQuery(
        string $sahodayaId,
        int $class,
        string $scope,
        ?int $streamId,
        ?int $subjectId,
        ?string $academicYear,
    ) {
        $query = TopperCountConfig::query()
            ->where('sahodaya_id', $sahodayaId)
            ->where('scope', $scope)
            ->where(function ($q) use ($class) {
                $q->where('class', $class)->orWhereNull('class');
            });

        if ($academicYear !== null) {
            $query->where(function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)->orWhereNull('academic_year');
            });
        } else {
            $query->whereNull('academic_year');
        }

        if ($scope === TopperCountConfig::SCOPE_STREAM && $streamId) {
            $query->where(function ($q) use ($streamId) {
                $q->where('stream_id', $streamId)->orWhereNull('stream_id');
            });
        } elseif ($scope === TopperCountConfig::SCOPE_SUBJECT && $subjectId) {
            $query->where(function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId)->orWhereNull('subject_id');
            });
        }

        return $query;
    }

    public function assertCanAdd(BoardResult $boardResult, string $sahodayaId, ?Topper $updating = null): void
    {
        $cap = $this->resolveCap($sahodayaId, (int) $boardResult->class);
        $count = Topper::query()
            ->where('board_result_id', $boardResult->id)
            ->overallEntries()
            ->when($updating, fn ($q) => $q->where('id', '!=', $updating->id))
            ->count();

        if ($count >= $cap) {
            throw ValidationException::withMessages([
                'rank' => "Topper limit reached ({$cap}). Adjust Sahodaya Top-N settings or remove an existing topper.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TopperCountConfig
     */
    public function upsert(string $sahodayaId, array $data): TopperCountConfig
    {
        $keys = [
            'sahodaya_id' => $sahodayaId,
            // Omitted/null academic_year keeps writing the global row, exactly as before this
            // column existed — callers must pass it explicitly to create/edit a per-year override.
            'academic_year' => $data['academic_year'] ?? null,
            'class' => $data['class'] ?? null,
            'scope' => $data['scope'] ?? TopperCountConfig::SCOPE_OVERALL,
            'stream_id' => $data['stream_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
        ];

        $tieMode = $data['tie_mode'] ?? TopperCountConfig::TIE_INCLUDE_GROUP;
        if (! in_array($tieMode, [TopperCountConfig::TIE_INCLUDE_GROUP, TopperCountConfig::TIE_HARD_CAP], true)) {
            $tieMode = TopperCountConfig::TIE_INCLUDE_GROUP;
        }

        $rankStyle = $data['rank_style'] ?? TopperCountConfig::RANK_COMPETITION;
        if (! in_array($rankStyle, [
            TopperCountConfig::RANK_COMPETITION,
            TopperCountConfig::RANK_DENSE,
            TopperCountConfig::RANK_SEQUENTIAL,
        ], true)) {
            $rankStyle = TopperCountConfig::RANK_COMPETITION;
        }

        return TopperCountConfig::updateOrCreate($keys, [
            'top_n' => max(1, min(50, (int) ($data['top_n'] ?? self::DEFAULT_TOP_N))),
            'tie_mode' => $tieMode,
            'rank_style' => $rankStyle,
        ]);
    }

    /** Resolve the tie-break mode for a given scope (include_group|hard_cap). */
    public function resolveTieMode(
        string $sahodayaId,
        int $class,
        string $scope = TopperCountConfig::SCOPE_OVERALL,
        ?int $streamId = null,
        ?int $subjectId = null,
        ?string $academicYear = null,
    ): string
    {
        [$scope, $streamId, $subjectId] = $this->effectiveScope($sahodayaId, $scope, $streamId, $subjectId);

        $config = $this->scopedConfigQuery($sahodayaId, $class, $scope, $streamId, $subjectId, $academicYear)
            ->orderByRaw('academic_year is null')
            ->orderByRaw('class is null')
            ->orderByRaw('stream_id is null')
            ->orderByRaw('subject_id is null')
            ->first();

        return $config?->tie_mode ?? TopperCountConfig::TIE_INCLUDE_GROUP;
    }

    /** Resolve rank numbering style for a given scope. */
    public function resolveRankStyle(
        string $sahodayaId,
        int $class,
        string $scope = TopperCountConfig::SCOPE_OVERALL,
        ?int $streamId = null,
        ?int $subjectId = null,
        ?string $academicYear = null,
    ): string
    {
        [$scope, $streamId, $subjectId] = $this->effectiveScope($sahodayaId, $scope, $streamId, $subjectId);

        $config = $this->scopedConfigQuery($sahodayaId, $class, $scope, $streamId, $subjectId, $academicYear)
            ->orderByRaw('academic_year is null')
            ->orderByRaw('class is null')
            ->orderByRaw('stream_id is null')
            ->orderByRaw('subject_id is null')
            ->first();

        return $config?->rank_style ?? TopperCountConfig::RANK_COMPETITION;
    }
}
