<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\BoardResultMarksConfig;
use App\Models\ExamStream;
use App\Models\SahodayaRegistrationWindow;
use App\Models\TopperCountConfig;
use App\Models\TopperRankingSetting;
use App\Services\BoardResults\BoardResultAcademicYearService;
use App\Services\BoardResults\BoardResultMarksConfigService;
use App\Services\BoardResults\TopperCountService;
use App\Support\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * One consolidated, per-academic-year settings surface for Board Results, replacing the
 * previously scattered set of edit points (Masters page marks-config, Verification page
 * topper-cap popover, Students > Registration windows page's board-entry dates).
 * See docs/BOARD_RESULTS_UX_REDESIGN_PLAN.md §3.3 / §3.3a for the full rationale.
 *
 * Design choices carried over from that plan (confirmed with the Sahodaya admin owner):
 *  - Ranking-wide toggles (common ranking / no-rank) stay global/structural, not year-scoped
 *    — this controller surfaces them but writes through the existing
 *    SahodayaTopperController::updateRankingSettings endpoint, no duplicate logic here.
 *  - TopperCountConfig / BoardResultMarksConfig rows are year-scoped additively: omitting a
 *    year (or ticking "apply to all years") keeps writing the pre-existing global (NULL-year)
 *    row, so nothing already configured changes behavior unless an admin opts in per year.
 *  - No historical backfill: past years with no explicit row simply keep resolving to the
 *    global fallback via TopperCountService/BoardResultMarksConfigService.
 */
class BoardResultSettingsController extends SahodayaAdminController
{
    public function index(Request $request, BoardResultAcademicYearService $years)
    {
        $academicYear = $request->string('academic_year')->trim()->toString()
            ?: AcademicYear::forSahodaya($this->sahodaya->id);

        $streams = ExamStream::query()
            ->forSahodaya($this->sahodaya->id)
            ->where('is_active', true)
            ->orderByRaw('sahodaya_id is null')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'label', 'code']);

        $window = SahodayaRegistrationWindow::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $academicYear)
            ->first();

        $topperConfigs = TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where(function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)->orWhereNull('academic_year');
            })
            ->orderBy('class')
            ->get();

        $marksConfigs = BoardResultMarksConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where(function ($q) use ($academicYear) {
                $q->where('academic_year', $academicYear)->orWhereNull('academic_year');
            })
            ->get();

        $classXYearRow = $marksConfigs->first(fn (BoardResultMarksConfig $c) => (int) $c->class === 10 && $c->academic_year === $academicYear);
        $classXGlobalRow = $marksConfigs->first(fn (BoardResultMarksConfig $c) => (int) $c->class === 10 && $c->academic_year === null);
        $classXConfig = $classXYearRow ?? $classXGlobalRow;

        $streamMarksConfigs = $marksConfigs->where('class', 12)->whereNotNull('stream_id');
        $streamTotalMarks = $streams->mapWithKeys(function (ExamStream $s) use ($streamMarksConfigs, $academicYear) {
            $yearRow = $streamMarksConfigs->first(fn (BoardResultMarksConfig $c) => (int) $c->stream_id === $s->id && $c->academic_year === $academicYear);
            $globalRow = $streamMarksConfigs->first(fn (BoardResultMarksConfig $c) => (int) $c->stream_id === $s->id && $c->academic_year === null);
            $row = $yearRow ?? $globalRow;

            return [$s->id => [
                'total_marks' => $row?->total_marks ?? BoardResultMarksConfig::DEFAULT_TOTAL_MARKS,
                'is_year_specific' => $yearRow !== null,
            ]];
        });

        $rankingSettings = TopperRankingSetting::forSahodaya($this->sahodaya->id);

        return $this->inertia('Sahodaya/BoardResults/Settings', [
            'academicYear' => $academicYear,
            'academicYearOptions' => $years->entryYearOptions($this->sahodaya->id),
            'entryWindow' => [
                'enabled' => $window?->board_entry_enabled,
                'starts_at' => $window?->board_entry_starts_at?->toDateString(),
                'ends_at' => $window?->board_entry_ends_at?->toDateString(),
            ],
            'streams' => $streams,
            'topperConfigs' => $topperConfigs,
            'defaultTopN' => TopperCountService::DEFAULT_TOP_N,
            'classXTotalMarks' => $classXConfig?->total_marks ?? BoardResultMarksConfig::DEFAULT_TOTAL_MARKS,
            'classXIsYearSpecific' => $classXYearRow !== null,
            'streamTotalMarks' => $streamTotalMarks,
            'rankingSettings' => $rankingSettings->only(['use_common_ranking', 'no_rank']),
        ]);
    }

    /**
     * The literal "if not date, don't allow" requirement: an admin cannot save this year as
     * enabled without also giving it a start and end date. Disabling never requires dates.
     */
    public function updateEntryWindow(Request $request)
    {
        $data = $request->validate([
            'academic_year' => 'required|string|max:10',
            'enabled' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($data['enabled'] && (! $data['starts_at'] || ! $data['ends_at'])) {
            throw ValidationException::withMessages([
                'starts_at' => 'A start and end date are required to enable board-result data entry for this year.',
            ]);
        }

        $existing = SahodayaRegistrationWindow::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $data['academic_year'])
            ->first();

        SahodayaRegistrationWindow::updateOrCreate(
            ['sahodaya_id' => $this->sahodaya->id, 'academic_year' => $data['academic_year']],
            [
                'academic_year_id' => AcademicYear::recordIdForLabel($data['academic_year']),
                'board_entry_enabled' => $data['enabled'],
                'board_entry_starts_at' => $data['starts_at'] ?? null,
                'board_entry_ends_at' => $data['ends_at'] ?? null,
                // This endpoint owns only the board-entry window. Everything else on the shared
                // row (student add/edit windows, registration dates) is preserved untouched —
                // unlike the legacy StudentRegistrationWindowController, which used to always
                // overwrite these same two board_entry_* columns whenever *it* saved.
                'add_open' => $existing?->add_open,
                'add_close' => $existing?->add_close,
                'edit_open' => $existing?->edit_open,
                'edit_close' => $existing?->edit_close,
                'registration_starts_at' => $existing?->registration_starts_at,
                'registration_ends_at' => $existing?->registration_ends_at,
            ],
        );

        return back()->with('success', $data['enabled']
            ? 'Board result data entry window saved and enabled for '.$data['academic_year'].'.'
            : 'Board result data entry disabled for '.$data['academic_year'].'.');
    }

    public function updateMarksConfig(Request $request, BoardResultMarksConfigService $marksConfig)
    {
        $data = $request->validate([
            'academic_year' => 'required|string|max:10',
            'apply_to_all_years' => 'nullable|boolean',
            'class_x_total_marks' => 'required|integer|min:1|max:5000',
            'streams' => 'nullable|array',
            'streams.*.stream_id' => 'required|integer',
            'streams.*.total_marks' => 'required|integer|min:1|max:5000',
        ]);

        $year = ($data['apply_to_all_years'] ?? false) ? null : $data['academic_year'];

        $marksConfig->upsert($this->sahodaya->id, 10, null, $data['class_x_total_marks'], $year);

        foreach ($data['streams'] ?? [] as $row) {
            $marksConfig->upsert($this->sahodaya->id, 12, (int) $row['stream_id'], (int) $row['total_marks'], $year);
        }

        return back()->with('success', $year
            ? "Marks settings saved for {$year} only."
            : 'Marks settings saved as the default for every academic year.');
    }

    public function updateTopperCap(Request $request, TopperCountService $counts)
    {
        $data = $request->validate([
            'academic_year' => 'required|string|max:10',
            'apply_to_all_years' => 'nullable|boolean',
            'class' => 'nullable|integer|in:10,12',
            'scope' => 'nullable|string|in:overall,stream,subject',
            'top_n' => 'required|integer|min:1|max:50',
            'tie_mode' => 'nullable|string|in:include_group,hard_cap',
            'rank_style' => 'nullable|string|in:competition,dense,sequential',
            'stream_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $data['academic_year'] = ($data['apply_to_all_years'] ?? false) ? null : $data['academic_year'];

        $config = $counts->upsert($this->sahodaya->id, $data);

        return back()->with('success', "Top-N set to {$config->top_n}.");
    }

    /** Clones a prior year's explicit marks-config + topper-cap rows onto a new year in one step. */
    public function copyFromPreviousYear(Request $request, BoardResultMarksConfigService $marksConfig)
    {
        $data = $request->validate([
            'from_year' => 'required|string|max:10',
            'to_year' => 'required|string|max:10|different:from_year',
        ]);

        $copiedMarks = 0;
        BoardResultMarksConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $data['from_year'])
            ->get()
            ->each(function (BoardResultMarksConfig $row) use ($data, $marksConfig, &$copiedMarks) {
                $marksConfig->upsert($this->sahodaya->id, $row->class, $row->stream_id, $row->total_marks, $data['to_year']);
                $copiedMarks++;
            });

        $copiedCaps = 0;
        TopperCountConfig::query()
            ->where('sahodaya_id', $this->sahodaya->id)
            ->where('academic_year', $data['from_year'])
            ->get()
            ->each(function (TopperCountConfig $row) use ($data, &$copiedCaps) {
                TopperCountConfig::updateOrCreate(
                    [
                        'sahodaya_id' => $this->sahodaya->id,
                        'academic_year' => $data['to_year'],
                        'class' => $row->class,
                        'scope' => $row->scope,
                        'stream_id' => $row->stream_id,
                        'subject_id' => $row->subject_id,
                    ],
                    [
                        'top_n' => $row->top_n,
                        'tie_mode' => $row->tie_mode,
                        'rank_style' => $row->rank_style,
                    ],
                );
                $copiedCaps++;
            });

        if ($copiedMarks === 0 && $copiedCaps === 0) {
            return back()->with('success', "No year-specific settings found on {$data['from_year']} to copy — {$data['to_year']} will keep using this Sahodaya's global defaults.");
        }

        return back()->with('success', "Copied {$copiedMarks} marks setting(s) and {$copiedCaps} topper cap setting(s) from {$data['from_year']} to {$data['to_year']}.");
    }
}
