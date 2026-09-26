<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Jobs\BuildCertificateZipChunkJob;
use App\Jobs\RenderCertificateChunkJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateBatch;
use App\Models\FestCertificatePrint;
use App\Models\FestCertificateSchoolMark;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Events\FestCertificateService;
use App\Services\Events\FestEventNotifier;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestPageActivity;
use App\Support\PdfGenerator;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class FestCertificateController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        // A whole hub has thousands of certificates; building every payload plus the grouped
        // views below is the heaviest read in the module.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->withParticipationItems($this->certificatesForEvent($event), $event);
        $schoolResults = $this->schoolResultsStatus($event);
        $marks = $this->downloadMarks($event);

        return $this->inertia('Sahodaya/Events/Certificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $this->slimCertificates($certificates),
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'winnersByItem' => $this->winnersByItem($certificates, $event),
            'winnersBySchool' => $this->withDownloadMarks($this->withSchoolResults($this->winnersBySchool($certificates, $event), $schoolResults), $marks, 'winner'),
            'participationBySchool' => $this->withDownloadMarks($this->withPrintState($this->withSchoolResults($this->participationBySchool($certificates, $event), $schoolResults)), $marks, 'participation'),
            'recentBatches' => $this->recentBatchesForEvent($event),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
            'certificateSignatories' => $this->signatoriesForUi($event),
            'signatoryLabelSuggestions' => $this->signatoryLabelSuggestions(),
        ] + $this->participationHoldProps($event)));
    }

    /**
     * Dedicated Merit certificates workspace — same underlying data as index(), scoped
     * to cert_type=winner, with its own item-wise/school-wise filtering and bulk actions
     * on the frontend rather than sharing the combined page's tabs.
     */
    public function meritCertificates(string $tenantId, FestEvent $event)
    {
        // A whole hub has thousands of certificates; building every payload plus the grouped
        // views below is the heaviest read in the module.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->certificatesForEvent($event, 'winner');

        return $this->inertia('Sahodaya/Events/MeritCertificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $this->slimCertificates($certificates),
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'recentBatches' => $this->recentBatchesForEvent($event, 'winner'),
            'certificateSignatories' => $this->signatoriesForUi($event),
            'signatoryLabelSuggestions' => $this->signatoryLabelSuggestions(),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
        ] + $this->participationHoldProps($event)));
    }

    /** Dedicated Participation certificates workspace — same idea as meritCertificates(). */
    public function participationCertificatesPage(string $tenantId, FestEvent $event)
    {
        if (app(FestCertificateService::class)->holdsParticipation($event)) {
            abort_if($event->tenant_id !== $this->sahodaya->id, 403);

            return redirect("/sahodaya-admin/{$tenantId}/events/{$event->rootEvent()->id}/certificates/participants")
                ->with('info', 'Participation certificates are issued once per student from the parent event — showing them here.');
        }

        // A whole hub has thousands of certificates; building every payload plus the grouped
        // views below is the heaviest read in the module.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $certificates = $this->withParticipationItems($this->certificatesForEvent($event, 'participation'), $event);

        return $this->inertia('Sahodaya/Events/ParticipationCertificates', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'certificates' => $this->slimCertificates($certificates),
            'publishedItems' => $this->publishedItemsForEvent($event),
            'schools' => $this->schoolsFromCertificates($certificates),
            'recentBatches' => $this->recentBatchesForEvent($event, 'participation'),
            'certificateSignatories' => $this->signatoriesForUi($event),
            'signatoryLabelSuggestions' => $this->signatoryLabelSuggestions(),
            'staleCount' => $certificates->filter(fn ($c) => $c['is_stale'] ?? false)->count(),
        ]));
    }

    /**
     * Only what the certificate list pages actually read. certificatesForEvent() merges each
     * certificate's full payloadFor() shape (participant, registration, event, item, mark
     * ... as whole Eloquent models with their loaded relations) into every row, which the
     * grouped views below need server-side but which serialized straight into the Inertia
     * page was ~30 KB per certificate -- a ~3,000-certificate hub rendered a page payload of
     * ~98 MB and exhausted memory building the root view (production, 2026-09-25).
     *
     * @param  Collection<int, array<string, mixed>>  $certificates
     * @return Collection<int, array<string, mixed>>
     */
    private function slimCertificates(Collection $certificates): Collection
    {
        return $certificates->map(function (array $c) {
            $school = $c['registration']?->school ?? $c['participant']?->registration?->school;

            return [
                'id' => $c['id'],
                'uuid' => $c['uuid'],
                'cert_type' => $c['cert_type'],
                'is_stale' => $c['is_stale'],
                'is_rendered' => $c['is_rendered'],
                'rendered_at' => $c['rendered_at'],
                'student' => ['name' => $c['student']?->name ?? $c['participant']?->student?->name],
                'item' => ! empty($c['item']) ? ['id' => $c['item']->id, 'title' => $c['item']->title] : null,
                'items' => $c['participation_items'] ?? null,
                'mark' => $c['mark'] ? ['position' => $c['mark']->position] : null,
                'registration' => ['school' => $school ? ['id' => $school->id, 'name' => $school->name] : null],
            ];
        })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function certificatesForEvent(FestEvent $event, ?string $certType = null): Collection
    {
        $participantIds = FestParticipant::where(function ($q) use ($event) {
            $q->whereIn('event_id', $event->reportableEventIds())
                ->orWhereHas('registration', fn ($rq) => $rq->whereIn('event_id', $event->reportableEventIds()));
        })->pluck('id');

        $service = app(FestCertificateService::class);

        // A phase/region child event holds participation certificates back -- the parent
        // issues one per person for the whole hub.
        if ($service->holdsParticipation($event)) {
            if ($certType === 'participation') {
                return collect();
            }
            $certType ??= null;
        }

        $certificates = $service->dedupeParticipationPerPerson(
            Certificate::where('entity_type', FestParticipant::class)
                ->whereIn('entity_id', $participantIds)
                ->when($certType, fn ($q) => $q->where('cert_type', $certType))
                ->when(! $certType && $service->holdsParticipation($event), fn ($q) => $q->where('cert_type', '!=', 'participation'))
                ->orderByDesc('generated_at')
                ->get()
        );

        // Batched instead of one payloadFor() (2 queries each) per row — see
        // FestCertificateService::payloadsFor().
        $payloads = $service->payloadsFor($certificates);

        return $certificates->map(fn ($c) => array_merge(
            [
                'id' => $c->id,
                'uuid' => $c->verification_uuid,
                'cert_type' => $c->cert_type,
                'is_stale' => $c->is_stale,
                'is_rendered' => $c->file_path !== null,
                'rendered_at' => $c->rendered_at,
            ],
            $payloads->get($c->id) ?? []
        ));
    }

    private function publishedItemsForEvent(FestEvent $event): Collection
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return FestEventItem::whereIn('event_id', $event->reportableEventIds())
            ->whereNotNull('results_published_at')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code', 'class_group', 'category', 'gender', 'participant_type'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'item_code' => $item->item_code,
                'category_label' => FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels),
                'gender_label' => $this->itemGenderLabel($item),
                'type_label' => $this->itemTypeLabel($item),
            ])
            ->values();
    }

    private function itemGenderLabel(FestEventItem $item): ?string
    {
        return FestItemCategoryLabel::genderLabel($item->gender);
    }

    private function itemTypeLabel(FestEventItem $item): string
    {
        return FestItemCategoryLabel::typeLabel($item->participant_type);
    }

    /** @param  Collection<int, array<string, mixed>>  $certificates */
    private function schoolsFromCertificates(Collection $certificates): Collection
    {
        return $certificates->map(fn ($c) => [
            'id' => $c['registration']?->school?->id ?? $c['participant']?->registration?->school?->id,
            'name' => $c['registration']?->school?->name ?? $c['participant']?->registration?->school?->name ?? 'Unknown School',
        ])
            ->filter(fn ($s) => ! empty($s['id']))
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function recentBatchesForEvent(FestEvent $event, ?string $certType = null): Collection
    {
        return CertificateBatch::where('event_id', $event->id)
            ->when($certType, fn ($q) => $q->where('cert_type', $certType))
            ->latest()
            ->limit(10)
            ->get([
                'id', 'batch_type', 'scope_description', 'status', 'total_count',
                'processed_count', 'succeeded_count', 'failed_count', 'created_at', 'completed_at',
            ]);
    }

    private function winnersByItem(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesByItem($certificates, 'winner', $currentEvent);
    }

    private function winnersBySchool(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesBySchool($certificates, 'winner', $currentEvent);
    }

    /**
     * A participation certificate is one per person (anchored to an arbitrary one of their
     * FestParticipant rows — see FestCertificateService::generateParticipationForEvent()),
     * handed out per school, so there is deliberately no grouped-by-item participation
     * view: grouping by the anchor's item misfiled multi-item students under whichever item
     * they happened to register first. Each row carries the person's full item list
     * (withParticipationItems()) — what the certificate itself prints.
     */
    private function participationBySchool(Collection $certificates, FestEvent $currentEvent): Collection
    {
        return $this->groupCertificatesBySchool($certificates, 'participation', $currentEvent);
    }

    /**
     * Per school: how many of the items it has approved registrations in have their
     * results published (FestEventItem.results_published_at — the per-item flag, same as
     * publishedItemsForEvent(); the event-wide results_published only flips once the whole
     * fest is final). A school with none pending has every merit result it will ever get,
     * and every grade its participation certificates print, so its certificates are safe
     * to bulk-download.
     *
     * @return Collection<string, array{total: int, published: int, pending: list<string>}> keyed by school id
     */
    private function schoolResultsStatus(FestEvent $event): Collection
    {
        $registrations = FestRegistration::query()
            ->whereIn('event_id', $event->reportableEventIds())
            ->where('status', 'approved')
            ->whereNotNull('item_id')
            ->whereNotNull('school_id')
            ->distinct()
            ->get(['school_id', 'item_id']);

        if ($registrations->isEmpty()) {
            return collect();
        }

        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
        $items = FestEventItem::whereIn('id', $registrations->pluck('item_id')->unique())
            ->with('event:id,tenant_id')
            ->get(['id', 'event_id', 'title', 'class_group', 'category', 'age_group', 'results_published_at'])
            ->keyBy('id');

        return $registrations->groupBy('school_id')->map(function (Collection $rows) use ($items, $classGroupLabels, $artsCategoryLabels) {
            $schoolItems = $rows->pluck('item_id')->unique()->map(fn ($id) => $items->get($id))->filter();
            $pending = $schoolItems->reject(fn (FestEventItem $item) => $item->results_published_at !== null);

            return [
                'total' => $schoolItems->count(),
                'published' => $schoolItems->count() - $pending->count(),
                'pending' => $pending
                    ->map(function (FestEventItem $item) use ($classGroupLabels, $artsCategoryLabels) {
                        $category = FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels);

                        return $category ? "{$item->title} ({$category})" : $item->title;
                    })
                    ->sort()
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups  groupCertificatesBySchool() rows
     * @param  Collection<string, array{total: int, published: int, pending: list<string>}>  $schoolResults
     */
    /**
     * The manual downloaded ticks for the event, keyed "certType|schoolId".
     *
     * A participation certificate is one per person and common to the parent event and its
     * children, so its tick is shared across the whole event family: it is read from every
     * event in the root's tree (including ticks made on a regional leg before this was
     * shared) and written against the root. Merit certificates differ per leg, so their
     * tick stays on the event it was made on.
     *
     * @return Collection<string, FestCertificateSchoolMark>
     */
    private function downloadMarks(FestEvent $event): Collection
    {
        $family = $event->rootEvent()->reportableEventIds();

        return FestCertificateSchoolMark::where(function ($q) use ($event, $family) {
            $q->where(fn ($w) => $w->where('cert_type', 'winner')->where('event_id', $event->id))
                ->orWhere(fn ($w) => $w->where('cert_type', 'participation')->whereIn('event_id', $family));
        })
            ->orderBy('marked_at')
            ->get()
            ->keyBy(fn ($m) => $m->cert_type.'|'.$m->school_id);
    }

    /**
     * Adds 'complete' (every item on the student's participation certificate has published
     * results -- so its grades are final and it is safe to print even while the school has
     * other items pending) and 'printed' (already sent to print by a "Print complete
     * students" run) to each participation row of the by-school groups.
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function withPrintState(Collection $groups): Collection
    {
        $itemIds = $groups->flatMap(fn (array $g) => collect($g['winners'])->flatMap(fn (array $w) => collect($w['items'] ?? [])->pluck('id')))->unique()->values();
        $published = FestEventItem::whereIn('id', $itemIds)->whereNotNull('results_published_at')->pluck('id')->flip();
        $printed = FestCertificatePrint::whereIn('certificate_id', $groups->flatMap(fn (array $g) => collect($g['winners'])->pluck('id')))->pluck('certificate_id')->flip();

        return $groups->map(function (array $group) use ($published, $printed) {
            $group['winners'] = collect($group['winners'])->map(function (array $w) use ($published, $printed) {
                $items = collect($w['items'] ?? []);
                $w['complete'] = $items->isNotEmpty() && $items->every(fn ($i) => $published->has($i['id']));
                $w['pending_items'] = $items->reject(fn ($i) => $published->has($i['id']))->pluck('title')->values()->all();
                $w['printed'] = $printed->has($w['id']);

                return $w;
            })->values();

            return $group;
        });
    }

    /** All participation rows for the event, by school, with print state (and the school's own results status). */
    private function participationPrintGroups(FestEvent $event): Collection
    {
        $certificates = $this->withParticipationItems($this->certificatesForEvent($event, 'participation'), $event);

        return $this->withPrintState($this->participationBySchool($certificates, $event));
    }

    /**
     * "Print complete students": prints only the students whose every item has published
     * results and who have not been printed before -- for schools that still have other
     * items pending -- records them as printed under one run id, and ticks a school as
     * downloaded once ALL its students are printed. Returns the run's print + report URLs.
     */
    public function printComplete(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $validated = $request->validate(['school_id' => 'nullable|string|max:64']);
        $schoolId = $validated['school_id'] ?? null;

        $groups = $this->participationPrintGroups($event)
            ->when($schoolId, fn ($c) => $c->filter(fn (array $g) => (string) $g['school_id'] === $schoolId));

        $toPrint = $groups->flatMap(fn (array $g) => collect($g['winners'])
            ->filter(fn (array $w) => $w['complete'] && ! $w['printed'])
            ->map(fn (array $w) => ['certificate_id' => $w['id'], 'school_id' => (string) $g['school_id']]));

        if ($toPrint->isEmpty()) {
            return response()->json(['message' => 'No complete students are left to print — every student with all results published has already been printed.'], 422);
        }

        $run = (string) \Illuminate\Support\Str::uuid();
        $rootId = $event->rootEvent()->id;
        $now = now();

        \Illuminate\Support\Facades\DB::transaction(function () use ($toPrint, $run, $rootId, $now, $request, $groups) {
            foreach ($toPrint->chunk(500) as $chunk) {
                FestCertificatePrint::insertOrIgnore($chunk->map(fn (array $r) => [
                    'event_id' => $rootId,
                    'certificate_id' => $r['certificate_id'],
                    'school_id' => $r['school_id'],
                    'run_uuid' => $run,
                    'printed_by_user_id' => $request->user()?->id,
                    'printed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }

            // A school whose every student is now printed is done -- tick it downloaded.
            $printedIds = $toPrint->pluck('certificate_id')->flip();
            foreach ($groups as $group) {
                $all = collect($group['winners']);
                if ($group['school_id'] && $all->every(fn (array $w) => $w['printed'] || $printedIds->has($w['id']))) {
                    FestCertificateSchoolMark::updateOrCreate(
                        ['event_id' => $rootId, 'school_id' => (string) $group['school_id'], 'cert_type' => 'participation'],
                        ['marked_by_user_id' => $request->user()?->id, 'marked_at' => $now],
                    );
                }
            }
        });

        $base = "/sahodaya-admin/{$tenantId}/events/{$event->id}/certificates";

        return response()->json([
            'run' => $run,
            'count' => $toPrint->count(),
            'schools' => $toPrint->pluck('school_id')->unique()->count(),
            'print_url' => "{$base}/print-all?run={$run}".($request->boolean('plain') ? '&plain=1' : ''),
            'report_url' => "{$base}/print-complete/report?run={$run}",
        ]);
    }

    /**
     * Portrait bulk report for one print run: per school, the students printed in the run
     * and the students still pending (with the items awaiting results), one school per page.
     */
    public function printCompleteReport(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $run = (string) $request->query('run');
        $runRows = FestCertificatePrint::where('run_uuid', $run)->get();
        abort_if($runRows->isEmpty(), 404, 'Unknown print run.');
        abort_unless(in_array((int) $runRows->first()->event_id, $event->rootEvent()->reportableEventIds(), true), 404);

        $inRun = $runRows->pluck('certificate_id')->flip();
        $runSchools = $runRows->pluck('school_id')->unique()->flip();

        $groups = $this->participationPrintGroups($event)->filter(fn (array $g) => $runSchools->has((string) $g['school_id']))->values();

        $students = \App\Models\Student::with('schoolClass:id,name')
            ->whereIn('id', $groups->flatMap(fn (array $g) => collect($g['winners'])->pluck('student_id'))->filter()->unique())
            ->get(['id', 'school_class_id'])->keyBy('id');

        $describe = fn (array $w, array $items) => [
            'name' => $w['name'],
            'class' => $students->get($w['student_id'])?->schoolClass?->name,
            'items' => $items,
        ];

        $schools = $groups->map(function (array $g) use ($inRun, $describe) {
            $winners = collect($g['winners']);
            $sort = fn ($rows) => $rows->sortBy(fn ($r) => mb_strtolower($r['name']))->values()->all();

            return [
                'name' => $g['school_name'],
                'printed' => $sort($winners->filter(fn ($w) => $inRun->has($w['id']))
                    ->map(fn ($w) => $describe($w, collect($w['items'] ?? [])->pluck('title')->all()))),
                // Not printed yet: still waiting on the listed items' results.
                'pending' => $sort($winners->filter(fn ($w) => ! $w['printed'] && ! $inRun->has($w['id']))
                    ->map(fn ($w) => $describe($w, $w['pending_items']))),
                'earlier' => $winners->filter(fn ($w) => $w['printed'] && ! $inRun->has($w['id']))->count(),
            ];
        })->sortBy('name')->values()->all();

        $filename = \App\Support\ReportFilename::build('certificate-print-report', $event->title, $runRows->first()->printed_at);

        return \App\Support\PdfChromeHeaderFooter::download('fest.reports.certificate-print-run', [
            'event' => $event,
            'schools' => $schools,
            'printedAt' => $runRows->first()->printed_at,
            'orgName' => $this->sahodaya->name,
            'logoSrc' => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
        ], $filename, $request->boolean('inline') || $request->boolean('preview'), 'Certificate Print Report', $event->title);
    }

    /**
     * Tells the certificates pages a child event holds participation certificates back, and
     * where they live (the parent's participation workspace).
     *
     * @return array{participationHeld: bool, participationParentUrl: ?string}
     */
    private function participationHoldProps(FestEvent $event): array
    {
        $held = app(FestCertificateService::class)->holdsParticipation($event);

        return [
            'participationHeld' => $held,
            'participationParentUrl' => $held ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->rootEvent()->id}/certificates/participants" : null,
        ];
    }

    /**
     * Standing per-school student list for participation certificates: every student with
     * their class, items and status -- printed (and when), ready to print (all results
     * published, not printed yet) or awaiting results (and which items). Independent of any
     * one print run, so the not-yet-printed students can be kept and printed together
     * later ("Print complete students" never reprints a printed student).
     *
     * @param  ?string  $status  all | printed | unprinted (ready + awaiting)
     * @return list<array{name: string, counts: array<string, int>, students: list<array<string, mixed>>}>
     */
    private function printStatusData(FestEvent $event, ?string $schoolId, string $status): array
    {
        $groups = $this->participationPrintGroups($event)
            ->when($schoolId, fn ($c) => $c->filter(fn (array $g) => (string) $g['school_id'] === $schoolId))
            ->values();

        $studentIds = $groups->flatMap(fn (array $g) => collect($g['winners'])->pluck('student_id'))->filter()->unique()->values();
        $classNames = \App\Models\Student::with('schoolClass:id,name')
            ->whereIn('id', $studentIds)
            ->get(['id', 'school_class_id'])
            ->mapWithKeys(fn ($st) => [$st->id => trim((string) $st->schoolClass?->name)]);

        // Class -> the fest's own category code (C1, C2, ...), read from the event's category
        // scheme labels ("Category 1 — Classes 3 & 4" => classes 3 and 4 are C1) -- the same
        // categories the items and results use, not the school stage names (Primary, ...).
        $categoryByClass = [];
        foreach (FestClassGroupScheme::labels(null, $event->rootEvent()) as $label) {
            if (preg_match('/Category\s*(\d+)/i', (string) $label, $cat) && preg_match('/Classes?\s*(.+)$/i', (string) $label, $classes)) {
                preg_match_all('/\d+/', $classes[1], $numbers);
                foreach ($numbers[0] as $number) {
                    $categoryByClass[(string) (int) $number] = 'C'.$cat[1];
                }
            }
        }

        // Fest ID = the registration number the student competes under (same field the
        // student-wise reports print as Fest ID); first one found across their entries.
        $festIds = FestParticipant::whereIn('student_id', $studentIds)
            ->whereNotNull('level_registration_number')
            ->whereIn('event_id', $event->rootEvent()->reportableEventIds())
            ->orderBy('id')
            ->pluck('level_registration_number', 'student_id');

        return $groups->map(function (array $g) use ($status, $classNames, $categoryByClass, $festIds) {
            $students = collect($g['winners'])->map(function (array $w) use ($classNames, $categoryByClass, $festIds) {
                // By class number; if the class isn't listed in the scheme, fall back to the
                // category of their (non-open) items.
                $classNumber = preg_match('/\d+/', (string) $classNames->get($w['student_id'], ''), $m) ? (string) (int) $m[0] : null;
                $category = $classNumber !== null ? ($categoryByClass[$classNumber] ?? null) : null;
                if ($category === null) {
                    $category = collect($w['items'] ?? [])
                        ->map(fn ($i) => (string) ($i['category_label'] ?? ''))
                        ->filter(fn ($label) => preg_match('/Category\s*(\d+)/i', $label) && ! preg_match('/group|open/i', $label))
                        ->map(fn ($label) => 'C'.preg_replace('/\D+/', '', (string) preg_replace('/^.*?Category\s*(\d+).*$/i', '$1', $label)))
                        ->first() ?? '';
                }

                return [
                    'name' => $w['name'],
                    'fest_id' => $festIds->get($w['student_id']),
                    'category' => $category,
                    'items' => collect($w['items'] ?? [])->pluck('title')->all(),
                    // Every item they entered has published results.
                    'complete' => (bool) $w['complete'],
                    'status' => $w['printed'] ? 'printed' : ($w['complete'] ? 'ready' : 'awaiting'),
                ];
            })
                // Complete students first, the rest after; each block alphabetical.
                ->sortBy(fn ($r) => ($r['complete'] ? '0' : '1').mb_strtolower($r['name']))
                ->values();

            $counts = [
                'total' => $students->count(),
                'printed' => $students->where('status', 'printed')->count(),
                'ready' => $students->where('status', 'ready')->count(),
                'awaiting' => $students->where('status', 'awaiting')->count(),
            ];

            $shown = match ($status) {
                'printed' => $students->where('status', 'printed'),
                'unprinted' => $students->where('status', '!=', 'printed'),
                default => $students,
            };

            return ['name' => $g['school_name'], 'counts' => $counts, 'students' => $shown->values()->all()];
        })
            ->filter(fn (array $s) => $s['students'] !== [])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function printStatusRequest(Request $request, FestEvent $event): array
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $status = in_array($request->query('status'), ['printed', 'unprinted'], true) ? $request->query('status') : 'all';
        $schoolId = $request->query('school_id') ? (string) $request->query('school_id') : null;

        return [$this->printStatusData($event, $schoolId, $status), $status];
    }

    public function printStatusPdf(Request $request, string $tenantId, FestEvent $event)
    {
        [$schools, $status] = $this->printStatusRequest($request, $event);

        return \App\Support\PdfChromeHeaderFooter::download('fest.reports.certificate-print-status', [
            'event' => $event,
            'schools' => $schools,
            'status' => $status,
            'orgName' => $this->sahodaya->name,
            'logoSrc' => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
        ], \App\Support\ReportFilename::build('certificate-print-status', $event->title, now(), [$status]),
            $request->boolean('inline') || $request->boolean('preview'), 'Certificate Print Status', $event->title);
    }

    public function printStatusXls(Request $request, string $tenantId, FestEvent $event)
    {
        [$schools, $status] = $this->printStatusRequest($request, $event);
        $rows = [];
        foreach ($schools as $school) {
            foreach ($school['students'] as $i => $student) {
                $rows[] = [
                    strtoupper($school['name']),
                    $i + 1,
                    $student['name'],
                    $student['fest_id'],
                    $student['category'],
                    implode(', ', $student['items']),
                    $student['complete'] ? 'Complete' : '',
                    '', // Verification -- ticked by hand
                ];
            }
        }

        return \App\Support\ExcelExport::download(
            pathinfo(\App\Support\ReportFilename::build('certificate-print-status', $event->title, now(), [$status], 'xls'), PATHINFO_FILENAME),
            ['School', 'Sl No', 'Student', 'Fest ID', 'Category', 'Items', 'Complete', 'Verification'],
            $rows,
            \App\Support\ExcelExport::generatedOnNote(),
        );
    }

    private function withDownloadMarks(Collection $groups, Collection $marks, string $certType): Collection
    {
        return $groups->map(function (array $group) use ($marks, $certType) {
            $mark = $marks->get($certType.'|'.$group['school_id']);

            return $group + [
                'downloaded' => $mark !== null,
                'downloaded_at' => $mark?->marked_at?->toIso8601String(),
            ];
        });
    }

    /** Manually tick / untick a school's certificates of one type as downloaded. */
    public function markSchoolDownloaded(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $validated = $request->validate([
            'school_id' => 'required|string|max:64',
            'cert_type' => 'required|in:winner,participation',
            'downloaded' => 'required|boolean',
        ]);

        $isParticipation = $validated['cert_type'] === 'participation';
        $root = $event->rootEvent();
        $key = [
            'event_id' => $isParticipation ? $root->id : $event->id,
            'school_id' => $validated['school_id'],
            'cert_type' => $validated['cert_type'],
        ];

        if ($validated['downloaded']) {
            FestCertificateSchoolMark::updateOrCreate($key, [
                'marked_by_user_id' => $request->user()?->id,
                'marked_at' => now(),
            ]);
        } else {
            // Participation: clear it wherever in the family it was ticked.
            FestCertificateSchoolMark::where('school_id', $key['school_id'])
                ->where('cert_type', $key['cert_type'])
                ->when($isParticipation,
                    fn ($q) => $q->whereIn('event_id', $root->reportableEventIds()),
                    fn ($q) => $q->where('event_id', $event->id))
                ->delete();
        }

        return back();
    }

    private function withSchoolResults(Collection $groups, Collection $schoolResults): Collection
    {
        return $groups->map(fn (array $group) => $group + [
            'results' => $schoolResults->get($group['school_id']) ?? ['total' => 0, 'published' => 0, 'pending' => []],
        ]);
    }

    /**
     * Adds 'participation_items' (every item the person's aggregated certificate lists, as
     * [id, title, category_label]) to each participation certificate row; winner rows are
     * left untouched since they are always exactly their own single item.
     *
     * @param  Collection<int, array<string, mixed>>  $certificates
     * @return Collection<int, array<string, mixed>>
     */
    private function withParticipationItems(Collection $certificates, FestEvent $event): Collection
    {
        if (! $certificates->contains(fn ($c) => ($c['cert_type'] ?? null) === 'participation')) {
            return $certificates;
        }

        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);
        $itemsByPerson = app(FestCertificateService::class)->participationItemsByPerson($event)
            ->map(fn (Collection $items) => $items->map(fn (FestEventItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'category_label' => FestItemCategoryLabel::shortLabel($item, $classGroupLabels, $artsCategoryLabels),
            ])->values()->all());

        return $certificates->map(function (array $c) use ($itemsByPerson) {
            if (($c['cert_type'] ?? null) !== 'participation' || empty($c['participant'])) {
                return $c;
            }

            $c['participation_items'] = $itemsByPerson->get(FestCertificateService::participationPersonKey($c['participant']), []);

            return $c;
        });
    }

    /**
     * Distinguishes same-titled items (e.g. three separate "Book Review" items, one per
     * class-group category) that would otherwise be indistinguishable in the grouped-by-
     * item admin view — see FestItemCategoryLabel's own docblock for why class_group
     * takes priority. shortLabel(), not resolve() — this is exactly the "compact,
     * certificate-context" use case its own docblock calls out.
     */
    private function groupCertificatesByItem(Collection $certificates, string $certType, FestEvent $event): Collection
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return $certificates
            ->filter(fn ($c) => ($c['cert_type'] ?? null) === $certType && ! empty($c['item']))
            ->groupBy(fn ($c) => $c['item']->id)
            ->map(function ($group) use ($classGroupLabels, $artsCategoryLabels) {
                $first = $group->first();

                return [
                    'item_id' => $first['item']->id,
                    'item_title' => $first['item']->title,
                    'item_code' => $first['item']->item_code,
                    'category_label' => FestItemCategoryLabel::shortLabel($first['item'], $classGroupLabels, $artsCategoryLabels),
                    'gender_label' => $this->itemGenderLabel($first['item']),
                    'type_label' => $this->itemTypeLabel($first['item']),
                    'winners' => $group->sortBy(fn ($c) => $c['mark']?->position ?? $c['position'] ?? 99)
                        ->map(fn ($c) => [
                            'id' => $c['id'],
                            'uuid' => $c['uuid'],
                            'name' => $c['student']?->name ?? $c['participant']?->student?->name ?? 'Participant',
                            'position' => $c['mark']?->position ?? $c['position'] ?? null,
                            'is_rendered' => $c['is_rendered'] ?? false,
                            'is_stale' => $c['is_stale'] ?? false,
                        ])
                        ->values(),
                ];
            })
            ->sortBy('item_title')
            ->values()
            // Sl No against each ITEM row in this grouped-by-item listing (not the
            // students inside it) — added after the sort above so numbering matches the
            // on-screen order.
            ->map(fn ($group, $index) => array_merge($group, ['sl_no' => $index + 1]))
            ->values();
    }

    private function groupCertificatesBySchool(Collection $certificates, string $certType, FestEvent $event): Collection
    {
        $classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
        $artsCategoryLabels = config('fest_item_taxonomy.arts_category', []);

        return $certificates
            ->filter(fn ($c) => ($c['cert_type'] ?? null) === $certType && ! empty($c['item']))
            ->groupBy(fn ($c) => $c['registration']?->school_id ?? $c['participant']?->registration?->school_id ?? 0)
            ->map(function ($group) use ($classGroupLabels, $artsCategoryLabels, $certType) {
                $first = $group->first();
                $school = $first['registration']?->school ?? $first['participant']?->registration?->school;

                $rows = $group->map(fn ($c) => [
                    'id' => $c['id'],
                    'uuid' => $c['uuid'],
                    'student_id' => $c['student']?->id ?? $c['participant']?->student_id,
                    'name' => $c['student']?->name ?? $c['participant']?->student?->name ?? 'Participant',
                    'item_title' => $c['item']?->title ?? '',
                    'category_label' => FestItemCategoryLabel::shortLabel($c['item'], $classGroupLabels, $artsCategoryLabels),
                    // Participation: every item on the person's one certificate, not
                    // just the anchor row's item.
                    'items' => $c['participation_items'] ?? null,
                    'position' => $c['mark']?->position ?? $c['position'] ?? null,
                    'is_rendered' => $c['is_rendered'] ?? false,
                    'is_stale' => $c['is_stale'] ?? false,
                ]);

                return [
                    'school_id' => $school?->id ?? 0,
                    'school_name' => $school?->name ?? 'Unknown School',
                    // Participation has no positions — alphabetical, for handing out.
                    'winners' => ($certType === 'participation'
                        ? $rows->sortBy(fn ($r) => mb_strtolower($r['name']))
                        : $rows->sortBy(fn ($r) => $r['position'] ?? 99))
                        ->values(),
                ];
            })
            ->sortBy('school_name')
            ->values();
    }

    public function tally(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $tally = app(FestCertificateService::class)->certificateTally($event);

        return $this->inertia('Sahodaya/Events/CertificateTally', $this->withEventActivity($event, FestPageActivity::CERTIFICATES, [
            'event' => $event,
            'rows' => $tally['rows'],
            'totals' => $tally['totals'],
            'summary' => $tally['summary'],
            'childEvents' => $this->scopedChildEventOptions($event),
        ]));
    }

    /**
     * Downloadable medal tally (?format=pdf|xls): per item, the gold / silver / bronze medals
     * actually handed out — one per person, so every member of a placed team gets one and a
     * tie gives each tied person one — with an all-items total. Portrait and deliberately
     * plain; no participation columns. The Excel file adds medals by category. Same data as
     * the tally page (certificateTally()'s medals_1/2/3).
     */
    public function tallyMedalReport(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $tally = app(FestCertificateService::class)->certificateTally($event);

        // category_label is the scheme's name ("Category 1 — Classes 3 & 4"), not the raw key.
        $rows = collect($tally['rows'])
            ->map(fn (array $row) => ['category_label' => (string) ($row['category_label'] ?: 'Open / all categories')] + $row)
            ->sort(fn (array $a, array $b) => strnatcasecmp($a['category_label'], $b['category_label']) ?: strnatcasecmp($a['title'], $b['title']))
            ->values();
        $totals = $tally['totals'];

        // e.g. medal-tally_kalotsavam-2026-27_sargadhara-tirur-region_2026-09-19.pdf
        $filenameBase = \App\Support\ReportFilename::buildForEvent('medal-tally', $event, organizationName: $this->sahodaya->name);

        if ($request->query('format') === 'xls') {
            $itemRows = $rows->map(fn (array $row, int $i) => [
                $i + 1,
                $row['title'].($row['is_team'] ? ' (Team)' : ''),
                $row['category_label'],
                $row['medals_1'],
                $row['medals_2'],
                $row['medals_3'],
                $row['medals_1'] + $row['medals_2'] + $row['medals_3'],
            ])->push(['', 'Total', '', $totals['medals_1'], $totals['medals_2'], $totals['medals_3'], $totals['medals_total']]);

            $categoryRows = collect($tally['summary'])->map(fn (array $row) => [
                $row['category_label'], $row['medals_1'], $row['medals_2'], $row['medals_3'], $row['medals_total'],
            ])->push(['Total', $totals['medals_1'], $totals['medals_2'], $totals['medals_3'], $totals['medals_total']]);

            return \App\Support\ExcelExport::downloadMultiSheet(pathinfo($filenameBase, PATHINFO_FILENAME), [
                'Medal tally' => [
                    'headers' => ['Sl No', 'Item', 'Category', 'Gold', 'Silver', 'Bronze', 'Total medals'],
                    'rows' => $itemRows,
                ],
                'By category' => [
                    'headers' => ['Category', 'Gold', 'Silver', 'Bronze', 'Total medals'],
                    'rows' => $categoryRows,
                ],
            ], \App\Support\ExcelExport::generatedOnNote());
        }

        $html = view('fest.reports.item-medal-tally', [
            'event' => $event,
            'orgName' => $this->sahodaya->name,
            'logoSrc' => \App\Support\TenantBranding::logoEmbedSrc($this->sahodaya),
            'rows' => $rows,
            'totals' => $totals,
        ])->render();

        return PdfGenerator::download($html, $filenameBase, $request->boolean('inline'));
    }

    public function generate(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $created = app(FestCertificateService::class)->generateForEvent($event, $itemId);

        $audit->festEvent($event, FestPageActivity::CERTIFICATES, 'fest.certificates.generated', count($created).' certificate(s) generated', [
            'count' => count($created),
            'item_id' => $itemId,
        ]);

        try {
            app(FestEventNotifier::class)->certificatesAvailable($event, count($created));
        } catch (\Throwable) {
            // ignore notification failures
        }

        return back()->with('success', count($created).' certificate(s) generated.');
    }

    /**
     * Sets or clears the event's certificate_date override — FestCertificateService::
     * resolveFieldValues() falls back to event_end/event_start (then now()) when this is
     * null. Doesn't proactively mark existing certificates stale; that follows the same
     * lazy, scheduled staleness check every other upstream-data change already relies on
     * (see contentHash()'s docblock) rather than a special case for this one field.
     */
    public function updateCertificateDate(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $validated = $request->validate(['certificate_date' => 'nullable|date']);

        $event->update(['certificate_date' => $validated['certificate_date'] ?? null]);

        return back()->with('success', $validated['certificate_date']
            ? 'Certificate date updated.'
            : 'Certificate date cleared — back to the event\'s own dates.');
    }

    /**
     * Sets this event's certificate signatories (venue convenor, host principal, ...): a
     * free list of {label, name, designation, school, signature image}. Who signs differs
     * per host venue, so it can't be baked into a shared template; the template decides
     * where each labelled block is printed (Certificate templates -> Signature blocks) and
     * matches it to an entry here by the label.
     */
    public function updateSignatories(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $validated = $request->validate([
            'signatories'                    => 'nullable|array|max:12',
            'signatories.*.label'            => 'nullable|required_with:signatories.*.name,signatories.*.designation,signatories.*.school,signatories.*.signature,signatories.*.signature_path|string|max:80',
            'signatories.*.name'             => 'nullable|string|max:120',
            'signatories.*.designation'      => 'nullable|string|max:120',
            'signatories.*.school'           => 'nullable|string|max:160',
            'signatories.*.signature'        => 'nullable|image|max:1024',
            'signatories.*.signature_path'   => 'nullable|string|max:255',
            'signatories.*.remove_signature' => 'nullable|boolean',
        ]);

        // An existing image path may only be kept if it is one this event already holds --
        // never trust a client-supplied storage path.
        $held = collect($event->certificate_signatories ?? [])->pluck('signature_path')->filter()->all();
        $disk = TenantStorage::uploadDisk();
        $seen = [];
        $entries = [];

        foreach ($validated['signatories'] ?? [] as $i => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $key = CertificateTemplate::signatureKey($label);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $path = in_array($row['signature_path'] ?? null, $held, true) ? $row['signature_path'] : null;
            if ($request->hasFile("signatories.$i.signature")) {
                $path = $request->file("signatories.$i.signature")->store('sahodaya/'.$this->sahodaya->id.'/certificate-signatures', $disk);
                if ($path === false) {
                    return back()->withErrors(['signatories' => "Could not store the signature image for \"{$label}\" — storage is unavailable. Nothing was saved."]);
                }
            } elseif (! empty($row['remove_signature'])) {
                $path = null;
            }

            $entries[] = [
                'key'            => $key,
                'label'          => $label,
                'name'           => $row['name'] ?? null,
                'designation'    => $row['designation'] ?? null,
                'school'         => $row['school'] ?? null,
                'signature_path' => $path,
            ];
        }

        $event->update(['certificate_signatories' => $entries ?: null]);

        return back()->with('success', 'Signatories saved. Existing certificates refresh the next time they are regenerated.');
    }

    /** @return list<array<string, mixed>> */
    private function signatoriesForUi(FestEvent $event): array
    {
        return collect($event->certificate_signatories ?? [])->map(fn ($s) => [
            'label'          => $s['label'] ?? '',
            'name'           => $s['name'] ?? '',
            'designation'    => $s['designation'] ?? '',
            'school'         => $s['school'] ?? '',
            'signature_path' => $s['signature_path'] ?? null,
            'signature_url'  => ! empty($s['signature_path'])
                ? TenantStorage::logoUrl($this->sahodaya, $s['signature_path'])
                : null,
        ])->values()->all();
    }

    /**
     * Labels already used as signature blocks on this Sahodaya's templates, so the event
     * form can offer them (the label is what ties an entry to a template's block).
     *
     * @return list<string>
     */
    private function signatoryLabelSuggestions(): array
    {
        return CertificateTemplate::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->get(['layout_json'])
            ->flatMap(fn ($t) => collect($t->layout_json['signature_blocks'] ?? [])->pluck('label'))
            ->filter()
            ->push('Venue Convenor')
            ->unique()
            ->values()
            ->all();
    }

    public function downloadZip(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->query('cert_type');
        $certIds = $request->query('certificate_ids')
            ? array_filter(array_map('intval', explode(',', (string) $request->query('certificate_ids'))))
            : null;
        $groupBy = in_array($request->query('group_by'), ['item', 'school'], true) ? $request->query('group_by') : null;

        $service = app(FestCertificateService::class);
        [$certificates, $payloads] = $service->exportScope($event, $publishedOnly, $itemId, $schoolId, $certType, $certIds);

        abort_if($certificates->isEmpty(), 404, $publishedOnly ? 'No published winner certificates to download.' : 'No certificates to download.');

        $zipPath = storage_path('app/tmp/fest-certs-'.$event->id.'-'.time().'.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $plain = $request->boolean('plain');

        // Only needed for group_by=item — resolved once for the whole export rather than
        // per certificate, matching groupCertificatesByItem()'s own cost-avoidance.
        $classGroupLabels = $groupBy === 'item' ? FestClassGroupScheme::labels(null, $event->rootEvent()) : [];
        $artsCategoryLabels = $groupBy === 'item' ? config('fest_item_taxonomy.arts_category', []) : [];

        $entryName = function (array $payload) use ($service, $groupBy, $classGroupLabels, $artsCategoryLabels) {
            $name = FestCertificateService::archiveFileName($payload['student']?->name, $payload['certificate']->verification_uuid);
            if ($groupBy) {
                $folder = $service->archiveGroupFolder($payload, $groupBy, $classGroupLabels, $artsCategoryLabels) ?? 'Other';
                $name = FestCertificateService::sanitizeArchiveSegment($folder).'/'.$name;
            }

            return $name;
        };

        // Already-rendered PDFs go straight in; the rest render through the converter
        // with a rolling window of concurrent requests (PdfGenerator::renderEach())
        // instead of one blocking round-trip per certificate — this direct download is
        // what a per-school ZIP uses while a long render run is still occupying the queue.
        // A full (embedded-image) context is only built for a certificate about to be
        // rendered, never up front for the whole scope.
        $misses = [];
        foreach ($certificates as $certificate) {
            $payload = $payloads->get($certificate->id);
            $pdf = $service->cachedPdf($certificate, $plain);
            if ($pdf === null) {
                $misses[$certificate->id] = $payload;

                continue;
            }
            $zip->addFromString($entryName($payload), $pdf);
        }

        $buildContext = $service->exportContextBuilder(embedAssets: true, plain: $plain);
        PdfGenerator::renderEach(
            (function () use ($misses, $service, $plain, $buildContext) {
                foreach ($misses as $certificateId => $payload) {
                    yield $certificateId => $service->pdfDocument($buildContext($payload['certificate'], $payload), $plain);
                }
            })(),
            function ($certificateId, $pdf) use ($zip, $misses, $entryName) {
                if ($pdf instanceof \Throwable) {
                    throw $pdf;
                }
                $zip->addFromString($entryName($misses[$certificateId]), $pdf);
            },
        );

        $zip->close();

        $filename = str($event->title)->slug()
            .($publishedOnly ? '-published-winners' : ($certType ? '-'.$certType : '-certificates'))
            .($groupBy ? '-by-'.$groupBy : '')
            .($request->boolean('plain') ? '-plain' : '').'.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend();
    }

    /**
     * Async counterpart to downloadZip() above, for the scopes big enough to blow past the
     * web request/proxy timeout — the whole-event and merit/participation-only dropdown
     * options, the school-filtered ZIP, and ad-hoc bulk selection. The small per-item/
     * per-school download-zip links in the grouped views stay on the synchronous route,
     * since those scopes are naturally small (a handful to a few dozen certificates).
     */
    public function queueZipExport(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->input('cert_type') ?: null;
        $certIds = $request->input('certificate_ids')
            ? array_values(array_filter(array_map('intval', explode(',', (string) $request->input('certificate_ids')))))
            : null;
        $plain = $request->boolean('plain');
        $groupBy = in_array($request->input('group_by'), ['item', 'school'], true) ? $request->input('group_by') : null;

        $service = app(FestCertificateService::class);
        $certificates = $service->resolveCertificateScope($event, $itemId, $schoolId, $certType, $certIds);

        // Mirrors downloadZip()/exportPayloadsForEvent()'s own publishedOnly filter, just
        // up front — so total_count (and the empty-scope 404 below) reflect the same set
        // the job will actually export, not the pre-filter winner count.
        if ($publishedOnly) {
            $payloads = $service->payloadsFor($certificates);
            $certificates = $service->publishedOnlyWinners($certificates, $payloads);
        }

        abort_if($certificates->isEmpty(), 404, $publishedOnly ? 'No published winner certificates to download.' : 'No certificates to download.');

        $this->deleteSupersededBatches($event, 'zip_export', $certType, $itemId, $schoolId, $certIds, $publishedOnly, $groupBy, $plain);

        $batchRow = CertificateBatch::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'batch_type' => 'zip_export',
            'cert_type' => $certType,
            'published_only' => $publishedOnly,
            'group_by' => $groupBy,
            'plain' => $plain,
            'item_id' => $itemId,
            'school_id' => $schoolId,
            'certificate_ids_json' => $certIds,
            'scope_description' => $this->describeScope($event, $itemId, $schoolId, $certType, $certIds, $publishedOnly).($groupBy ? ' (grouped by '.$groupBy.')' : ''),
            'total_count' => $certificates->count(),
            'status' => CertificateBatch::STATUS_PROCESSING,
            'created_by_user_id' => $request->user()?->id,
            'started_at' => now(),
        ]);

        $resultFilename = str($event->title)->slug()
            .($publishedOnly ? '-published-winners' : ($certType ? '-'.$certType : '-certificates'))
            .($groupBy ? '-by-'.$groupBy : '')
            .($plain ? '-plain' : '').'.zip';

        // Bus::chain(), not Bus::batch() — chunks must append to the same on-disk ZIP
        // sequentially (see BuildCertificateZipChunkJob's docblock), never concurrently.
        $sahodayaId = $this->sahodaya->id;
        $eventId = $event->id;
        $chunks = $certificates->pluck('id')->chunk(40)->values();
        $jobs = $chunks->map(fn ($chunk, $index) => new BuildCertificateZipChunkJob(
            $batchRow->id,
            $sahodayaId,
            $eventId,
            $chunk->values()->all(),
            $index === $chunks->count() - 1,
            $plain,
            $resultFilename,
            $groupBy,
        ))->all();

        Bus::chain($jobs)
            ->catch(function (\Throwable $e) use ($batchRow) {
                $batchRow->update([
                    'status' => CertificateBatch::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 2000),
                    'completed_at' => now(),
                ]);
            })
            ->dispatch();

        return back()
            ->with('success', "Preparing a ZIP of {$certificates->count()} certificate(s) in the background.")
            ->with('certificate_batch_id', $batchRow->id);
    }

    public function downloadZipResult(string $tenantId, FestEvent $event, CertificateBatch $batch)
    {
        abort_if($batch->tenant_id !== $this->sahodaya->id || $batch->event_id !== $event->id, 403);
        abort_if($batch->batch_type !== 'zip_export', 404);
        abort_if(
            ! in_array($batch->status, [CertificateBatch::STATUS_COMPLETED, CertificateBatch::STATUS_COMPLETED_WITH_ERRORS], true)
                || ! $batch->file_path,
            404,
            'This export is not ready yet.'
        );

        return TenantStorage::downloadPrivate($batch->file_path, $batch->storage_disk, $batch->result_filename);
    }

    public function printAll(Request $request, string $tenantId, FestEvent $event)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $publishedOnly = $request->boolean('published_only');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->query('cert_type');
        $certIds = $request->query('certificate_ids')
            ? array_filter(array_map('intval', explode(',', (string) $request->query('certificate_ids'))))
            : null;

        // A "Print complete students" run: print exactly the certificates it recorded.
        if ($run = $request->query('run')) {
            $runRows = FestCertificatePrint::where('run_uuid', (string) $run)->get(['certificate_id', 'event_id']);
            abort_if($runRows->isEmpty() || ! in_array((int) $runRows->first()->event_id, $event->rootEvent()->reportableEventIds(), true), 404, 'Unknown print run.');
            $certIds = $runRows->pluck('certificate_id')->map(fn ($id) => (int) $id)->all();
            $certType = 'participation';
        }

        $payloads = app(FestCertificateService::class)->exportPayloadsForEvent(
            $event,
            embedAssets: false,
            plain: $request->boolean('plain'),
            publishedOnly: $publishedOnly,
            itemId: $itemId,
            schoolId: $schoolId,
            certType: $certType,
            certIds: $certIds
        );

        abort_if($payloads->isEmpty(), 404, 'No certificates to print.');

        return view('fest.certificate-print-all', [
            'event' => $event,
            'certificates' => $payloads,
        ]);
    }

    public function generateAndRenderBatch(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $schoolId = $this->schoolIdFrom($request);
        $certType = $request->input('cert_type') ?: null;
        $certIds = $request->input('certificate_ids')
            ? array_values(array_filter(array_map('intval', explode(',', (string) $request->input('certificate_ids')))))
            : null;

        $certificates = app(FestCertificateService::class)->resolveCertificateScope($event, $itemId, $schoolId, $certType, $certIds);

        abort_if($certificates->isEmpty(), 404, 'No certificates match this scope — generate the certificate rows first.');

        $batch = $this->dispatchRenderBatch($request, $event, $certificates, 'generate', $itemId, $schoolId, $certType, $certIds);

        return back()
            ->with('success', "Rendering {$certificates->count()} certificate(s) in the background.")
            ->with('certificate_batch_id', $batch->id);
    }

    public function regenerateStale(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $participantIds = FestParticipant::where(function ($q) use ($event) {
            $q->whereIn('event_id', $event->reportableEventIds())
                ->orWhereHas('registration', fn ($rq) => $rq->whereIn('event_id', $event->reportableEventIds()));
        })->pluck('id');

        $certificates = Certificate::where('entity_type', FestParticipant::class)
            ->whereIn('entity_id', $participantIds)
            ->where('is_stale', true)
            ->get();

        abort_if($certificates->isEmpty(), 404, 'No stale certificates to regenerate.');

        $batch = $this->dispatchRenderBatch($request, $event, $certificates, 'regenerate_stale', null, null, null, null);

        return back()
            ->with('success', "Regenerating {$certificates->count()} stale certificate(s) in the background.")
            ->with('certificate_batch_id', $batch->id);
    }

    /**
     * Creates the tracking row, chunks the certificate set into ~30-id slices (see
     * RenderCertificateChunkJob), and dispatches them as one Bus::batch() run — per-chunk
     * failure isolation and progress counters come from Laravel's own job_batches
     * machinery rather than anything bespoke here.
     */
    private function dispatchRenderBatch(
        Request $request,
        FestEvent $event,
        Collection $certificates,
        string $batchType,
        ?int $itemId,
        ?string $schoolId,
        ?string $certType,
        ?array $certIds,
    ): CertificateBatch {
        $this->deleteSupersededBatches($event, $batchType, $certType, $itemId, $schoolId, $certIds);

        $batchRow = CertificateBatch::create([
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'batch_type' => $batchType,
            'cert_type' => $certType,
            'item_id' => $itemId,
            'school_id' => $schoolId,
            'certificate_ids_json' => $certIds,
            'scope_description' => $this->describeScope($event, $itemId, $schoolId, $certType, $certIds),
            'total_count' => $certificates->count(),
            'status' => CertificateBatch::STATUS_PROCESSING,
            'created_by_user_id' => $request->user()?->id,
            'started_at' => now(),
        ]);

        $tenantId = $this->sahodaya->id;
        $jobs = $certificates->pluck('id')->chunk(30)
            ->map(fn ($chunk) => new RenderCertificateChunkJob($batchRow->id, $chunk->values()->all(), $tenantId))
            ->all();

        $laravelBatch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function () use ($batchRow) {
                $batchRow->refresh();
                $batchRow->update([
                    'status' => $batchRow->failed_count > 0
                        ? CertificateBatch::STATUS_COMPLETED_WITH_ERRORS
                        : CertificateBatch::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            })
            ->catch(function ($_, \Throwable $e) use ($batchRow) {
                $batchRow->update([
                    'status' => CertificateBatch::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 2000),
                    'completed_at' => now(),
                ]);
            })
            ->name('certificate-batch-'.$batchRow->id)
            ->dispatch();

        $batchRow->update(['queued_job_batch_id' => $laravelBatch->id]);

        return $batchRow;
    }

    /**
     * Removes any FINISHED batch(es) that match the exact same signature as the one
     * about to be created — re-running "the same kind" of operation (e.g. "Render whole
     * event" a second time, or "Merit winners only (ZIP)" again) replaces its own entry
     * in Recent render & export runs instead of piling up alongside every earlier run of
     * it. Deliberately narrow — matches every scope-defining column, including an exact
     * (order-independent) match on an ad-hoc certificate_ids_json selection — so a
     * genuinely different scope (a different item, a different bulk selection) is never
     * touched. Only CertificateBatch::TERMINAL_STATUSES rows are candidates: a batch
     * that's still STATUS_PROCESSING is left alone, since deleting its row out from
     * under the still-running job would silently abandon it rather than replace it. A
     * superseded zip_export's underlying storage file is deleted too — nothing else
     * references it once the batch row is gone, so leaving it would just orphan it.
     */
    private function deleteSupersededBatches(
        FestEvent $event,
        string $batchType,
        ?string $certType,
        ?int $itemId,
        ?string $schoolId,
        ?array $certIds,
        bool $publishedOnly = false,
        ?string $groupBy = null,
        bool $plain = false,
    ): void {
        $candidates = CertificateBatch::where('event_id', $event->id)
            ->where('batch_type', $batchType)
            ->where('cert_type', $certType)
            ->where('published_only', $publishedOnly)
            ->where('group_by', $groupBy)
            ->where('plain', $plain)
            ->where('item_id', $itemId)
            ->where('school_id', $schoolId)
            ->whereIn('status', CertificateBatch::TERMINAL_STATUSES)
            ->get();

        $normalizedCertIds = $certIds ? collect($certIds)->sort()->values()->all() : null;

        foreach ($candidates as $candidate) {
            $candidateCertIds = $candidate->certificate_ids_json
                ? collect($candidate->certificate_ids_json)->sort()->values()->all()
                : null;

            if ($candidateCertIds !== $normalizedCertIds) {
                continue;
            }

            if ($candidate->batch_type === 'zip_export' && $candidate->file_path) {
                try {
                    Storage::disk($candidate->storage_disk ?? TenantStorage::uploadDisk())->delete($candidate->file_path);
                } catch (\Throwable) {
                    // Best-effort cleanup — an orphaned storage object isn't worth
                    // failing the new run over.
                }
            }

            $candidate->delete();
        }
    }

    /**
     * School ids are tenant UUIDs. These used to be read with an (int) cast, which turned
     * "6b240f41-…" into 6 — and MySQL's string→number comparison then matched every school
     * whose id starts with "6" — while a letter-first id became 0, silently dropping the
     * filter so a single school's print/ZIP/render ran over the whole event.
     */
    private function schoolIdFrom(Request $request): ?string
    {
        $schoolId = trim((string) $request->input('school_id', ''));

        return $schoolId !== '' ? $schoolId : null;
    }

    private function describeScope(FestEvent $event, ?int $itemId, ?string $schoolId, ?string $certType, ?array $certIds, bool $publishedOnly = false): string
    {
        if (! empty($certIds)) {
            $description = count($certIds).' selected certificate(s)';
        } elseif ($itemId) {
            $item = FestEventItem::find($itemId);
            $description = 'Item: '.($item?->title ?? "#{$itemId}");
        } elseif ($schoolId) {
            $school = Tenant::find($schoolId);
            $description = 'School: '.($school?->name ?? $schoolId);
        } elseif ($certType) {
            $description = ucfirst($certType).' certificates — whole event';
        } else {
            $description = 'Whole event';
        }

        // published_only isn't its own cert_type — it filters winner certs down to
        // publish-visible ones (see publishedOnlyWinners()) — so without this suffix,
        // "Merit winners only (ZIP)" and "All certificates (ZIP)" produced the identical
        // "Whole event" description, indistinguishable in the Recent runs list and (more
        // importantly) in dispatchRenderBatch()/queueZipExport()'s own superseded-batch
        // lookup, which would otherwise treat the two as the same run and delete one for
        // the other.
        return $publishedOnly ? $description.' (published winners only)' : $description;
    }

    public function batchProgress(string $tenantId, FestEvent $event, CertificateBatch $batch)
    {
        abort_if($batch->tenant_id !== $this->sahodaya->id || $batch->event_id !== $event->id, 403);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'batch_type' => $batch->batch_type,
            'scope' => $batch->scope_description,
            'total_count' => $batch->total_count,
            'processed_count' => $batch->processed_count,
            'succeeded_count' => $batch->succeeded_count,
            'failed_count' => $batch->failed_count,
            'error' => $batch->error,
        ]);
    }

    public function batches(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        return response()->json(
            CertificateBatch::where('event_id', $event->id)->latest()->limit(10)->get()
        );
    }

    public function previewSample(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // embedAssets: false — viewed on-site in a normal browser tab, so the cheaper
        // site-relative /storage/... URLs resolve fine (see renderContext()'s docblock).
        $context = $this->buildPreviewContext($request, $event, embedAssets: false);

        return view('fest.certificate-print', $context);
    }

    public function previewSamplePdf(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // embedAssets: true — DomPDF (and the external Chromium service) render the HTML
        // outside the site's own browser origin, so a relative /storage/... URL for the
        // background/logo/seal never resolves; every image must be a self-contained
        // base64 data URI instead, same as every other real PDF-producing path
        // (downloadZip(), RenderCertificateChunkJob) already does.
        $context = $this->buildPreviewContext($request, $event, embedAssets: true);
        $isLandscape = ($context['overlayLayout']['orientation'] ?? 'landscape') !== 'portrait';
        [$pageWidthMm, $pageHeightMm] = FestCertificateService::customPageDimensionsMm($context['overlayLayout'] ?? []);
        $html = view('fest.certificate-print', $context)->render();

        return PdfGenerator::download($html, 'certificate-preview.pdf', true, $isLandscape, pageWidthMm: $pageWidthMm, pageHeightMm: $pageHeightMm);
    }

    /**
     * Renders the real person most likely to expose an overflowing field — most distinct
     * items for a participation certificate (the unbounded item_title list), longest name
     * for a winner certificate — through the exact same renderContext() pipeline real
     * certificates use, rather than CertificateTemplateController::preview()'s canned
     * "Sample Student Name" values. Lets an admin catch a layout problem before
     * committing to a full bulk render.
     */
    private function buildPreviewContext(Request $request, FestEvent $event, bool $embedAssets): array
    {
        $certType = $request->query('cert_type', 'participation');
        $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;

        $service = app(FestCertificateService::class);

        // Explicit override so an admin (or this preview screen itself, later) can check
        // one specific person's certificate rather than only ever seeing the automatic
        // worst-case pick — useful once the worst case itself looks right and you want
        // to spot-check someone with a very different item count/name length instead.
        $participant = $request->query('participant_id')
            ? FestParticipant::find((int) $request->query('participant_id'))
            : $service->worstCaseParticipantForPreview($event, $certType, $itemId);

        abort_if(! $participant, 404, 'No eligible participants yet to preview — register participants for this event first.');

        $template = $service->resolveTemplate($event, $certType === 'winner' ? $itemId : null, $certType);

        $certificate = new Certificate([
            'entity_type' => FestParticipant::class,
            'entity_id' => $participant->id,
            'cert_type' => $certType,
            'template_id' => $template?->id,
            'verification_uuid' => 'PREVIEW-'.$participant->id,
        ]);

        $templateCache = [];
        $participantsCache = [];
        $context = $service->renderContext($certificate, null, $templateCache, $participantsCache, embedAssets: $embedAssets);
        $context['isSample'] = true;
        $context['qr_src'] = null;

        return $context;
    }
}
