<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestItemHead;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\FestEventInvoice;
use App\Models\FestSchedule;
use App\Models\FestSchoolVerification;
use App\Models\SahodayaProfile;
use App\Services\Events\FestInvoiceService;
use App\Services\Events\FestItemFeeResolver;
use App\Services\Events\FestLevelRegistrationService;
use App\Services\Events\FestParticipationLimitService;
use App\Services\Events\FestRegistrationEligibilityService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestRegistrationService;
use App\Services\Events\FestRegistrationImportService;
use App\Services\Audit\PlatformAuditLogger;
use App\Http\Controllers\SchoolAdmin\Concerns\BuildsSchoolFestEventContext;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;
use App\Services\Students\StudentEditLockService;
use App\Services\Students\StudentVerificationGate;
use App\Services\Notifications\SahodayaAdminNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FestRegistrationController extends SchoolAdminController
{
    public function index(Request $request, string $tenantId, string $program = 'kalotsav', string $view = 'registration')
    {
        $meta = SchoolFestProgram::meta($program);
        $program = $meta['slug'];

        if ($view === 'registration' && $request->query('event') && ! $request->route('event')) {
            $prefix = ProgramRouteMap::prefixFromSlug($program);

            return redirect("/school-admin/{$tenantId}/{$prefix}/events/{$request->query('event')}/registration");
        }

        $eventType = $meta['eventType'];
        $sahodayaId = $this->school->parent_id;
        $feeService = app(FestSchoolEventFeeService::class);

        // Singleton fest types (Kalotsav, …) are one-per-year: skip the event list and
        // open the single yearly event's registration directly. Sports is explicitly
        // excluded regardless of the tenant's is_singleton flag (may be stale pre-
        // unification data): schools browse per-sport events as a list, never the
        // season hub — which no longer carries items and would render empty.
        if ($view === 'registration' && ! $request->query('event')
            && $eventType !== 'sports' && FestEvent::isSingletonType($eventType)) {
            if ($single = $this->resolveSingletonSchoolEvent($request, $eventType, $program)) {
                $prefix = ProgramRouteMap::prefixFromSlug($program);

                return redirect("/school-admin/{$tenantId}/{$prefix}/events/{$single->id}/registration");
            }
        }

        $events = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($eventType)
            ->listedForSchool($this->school->id, $eventType)
            // Sports: hide the season hub once per-sport children exist (hub carries no
            // items). Standalone sport events and a childless hub (mid-setup fallback)
            // remain visible so schools never get an empty Sports page.
            ->when($eventType === 'sports', fn ($q) => $q->whereNotExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from('fest_events as sports_children')
                    ->whereColumn('sports_children.parent_event_id', 'fest_events.id'),
            ))
            ->when($request->query('event'), fn ($q) => $q->where('id', $request->query('event')))
            ->when($view === 'results', fn ($q) => $q->where('results_published', true))
            ->with('items')
            ->with('academicYear:id,label,status')
            ->orderByDesc('event_start')
            ->get()
            ->pipe(fn ($events) => app(\App\Services\School\SchoolUserScopeService::class)
                ->filterFestEventsForUser($request->user(), $this->school->id, $program, $events))
            ->map(fn (FestEvent $event) => $this->hydrateEventForSchoolRegistration($event, $feeService));

        if ($view === 'results') {
            $scoreboards = [];
            foreach ($events as $event) {
                $scoreboards[$event->id] = \App\Services\Events\EventContext::for($event)->scoreboardBySchool();
            }

            return $this->inertia('School/Events/Results', [
                'program'     => $program,
                'programMeta' => $meta,
                'events'      => $events,
                'scoreboards' => $scoreboards,
            ]);
        }

        $registrationEventIds = $this->registrationEventIdsForSchoolView($events);

        $registrations = FestRegistration::where('school_id', $this->school->id)
            ->whereIn('event_id', $registrationEventIds)
            ->with(['event', 'item', 'participants.student', 'participants.group'])
            ->get();

        $studentRows = Student::where('tenant_id', $this->school->id)
            ->active()
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        $studentCount = $studentRows->count();
        $lazyThreshold = (int) config('erp.fest_registration_lazy_student_threshold', 300);
        $lazyStudents = $studentCount > $lazyThreshold;
        $focusEventId = $request->query('event') ? (int) $request->query('event') : null;

        // With a large roster (lazy mode) and no event explicitly focused, students
        // are normally left unloaded until the school picks one. When there's only
        // one event on screen there's nothing to pick — auto-focus it so the page
        // doesn't render an empty "No students match" table with no explanation.
        if ($lazyStudents && $focusEventId === null && $events->count() === 1) {
            $focusEventId = $events->first()->id;
        }

        $eligibilityService = app(FestRegistrationEligibilityService::class);
        $studentsByEvent = collect();
        if (! $lazyStudents) {
            $studentsByEvent = $events->mapWithKeys(function (FestEvent $event) use ($studentRows, $eligibilityService) {
                return [
                    $event->id => $eligibilityService->annotateStudents($studentRows, $event, $this->school->id)->values(),
                ];
            });
        } elseif ($focusEventId) {
            $focusEvent = $events->firstWhere('id', $focusEventId);
            if ($focusEvent) {
                $studentsByEvent = collect([
                    $focusEventId => $eligibilityService->annotateStudents($studentRows, $focusEvent, $this->school->id)->values(),
                ]);
            }
        }

        return $this->inertia('School/Events/Registration', [
            'program'       => $program,
            'programMeta'   => $meta,
            'events'        => $events,
            'registrations' => $registrations,
            'schoolRegion'  => $this->schoolRegionContext($eventType),
            'students'      => $studentsByEvent->first() ?? [],
            'studentsByEvent' => $studentsByEvent,
            'lazyLoadStudents' => $lazyStudents,
            'studentCount'  => $studentCount,
            'schoolClasses' => $this->schoolClasses()->values(),
            'eventType'     => $eventType,
            'teachers'      => Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get(['id', 'name', 'reg_no', 'designation']),
            'isTeacherFest' => $eventType === 'teacher_fest',
            'presets'       => config('fest_participation_presets'),
            'studentEditLock' => app(StudentEditLockService::class)->metaForSchool($this->school),
            'focusEventId'    => $focusEventId,
            'profile'         => $this->eventPaymentProfileProp(),
        ]);
    }

    /**
     * Same Sahodaya bank/UPI payment details shown on the annual membership payment
     * page — reused here so schools have one place to look up where to send event
     * and item fee payments.
     *
     * @return array{payment_details_text: string}|null
     */
    private function eventPaymentProfileProp(): ?array
    {
        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();

        return $profile ? array_merge($profile->toArray(), [
            'payment_details_text' => $profile->paymentDetailsText(),
        ]) : null;
    }

    /**
     * Region context for Kalotsav school registration (read-only). Regions are picked
     * in the annual registration flow or assigned by the Sahodaya.
     *
     * @return array{applies: bool, region: ?string, set_url: string}|null
     */
    private function schoolRegionContext(string $eventType): ?array
    {
        if ($eventType !== 'kalolsavam') {
            return null;
        }

        $sahodayaId = $this->school->parent_id;
        $hasRegions = \App\Models\Region::forTenant($sahodayaId)->active()->exists();
        if (! $hasRegions) {
            return null;
        }

        $year = \App\Support\AcademicYear::forSchool($this->school);
        $regionName = \App\Models\SchoolRegionAssignment::forTenant($sahodayaId)
            ->forYear($year)
            ->where('school_id', $this->school->id)
            ->join('regions', 'regions.id', '=', 'school_region_assignments.region_id')
            ->value('regions.name');

        return [
            'applies' => true,
            'region'  => $regionName,
            'set_url' => "/school-admin/{$this->school->id}/registration",
        ];
    }

    public function eligibleStudents(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $studentRows = Student::where('tenant_id', $this->school->id)
            ->active()
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        $annotated = app(FestRegistrationEligibilityService::class)
            ->annotateStudents($studentRows, $event, $this->school->id)
            ->values();

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json(['students' => $annotated]);
        }

        return response()->json(['students' => $annotated]);
    }

    public function eventOverview(Request $request, string $tenantId, FestEvent $event, string $program = 'kalotsav')
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $event = $this->resolveSchoolFestEvent($request, $event, $meta['slug']);

        return $this->inertia('School/Events/EventOverview', array_merge(
            $this->schoolFestEventNavProps($event, $meta['slug']),
            ['stats' => $this->schoolFestEventOverviewStats($event)],
        ));
    }

    public function eventRegistration(Request $request, string $tenantId, FestEvent $event, string $program = 'kalotsav')
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $event = $this->resolveSchoolFestEvent($request, $event, $meta['slug']);
        $feeService = app(FestSchoolEventFeeService::class);
        $hydrated = $this->hydrateEventForSchoolRegistration($event, $feeService);

        $studentRows = Student::where('tenant_id', $this->school->id)
            ->active()
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        $eligibilityService = app(FestRegistrationEligibilityService::class);
        $students = $eligibilityService->annotateStudents($studentRows, $event, $this->school->id)->values();

        $registrations = FestRegistration::where('school_id', $this->school->id)
            ->whereIn('event_id', $this->registrationEventIdsForSchoolView(collect([$event])))
            ->with(['event', 'item', 'participants.student', 'participants.group'])
            ->get();

        return $this->inertia('School/Events/Registration', array_merge(
            $this->schoolFestEventNavProps($event, $meta['slug']),
            [
                'program'         => $meta['slug'],
                'programMeta'     => $meta,
                'events'          => collect([$hydrated]),
                'registrations'   => $registrations,
                'students'        => $students,
                'studentsByEvent' => collect([$event->id => $students]),
                'schoolClasses'   => $this->schoolClasses()->values(),
                'eventType'       => $meta['eventType'],
                'teachers'        => Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get(['id', 'name', 'reg_no', 'designation']),
                'isTeacherFest'   => $meta['eventType'] === 'teacher_fest',
                'presets'         => config('fest_participation_presets'),
                'studentEditLock' => app(StudentEditLockService::class)->metaForSchool($this->school),
                'focusEventId'    => $event->id,
                'singleEventMode' => true,
                'profile'         => $this->eventPaymentProfileProp(),
            ],
        ));
    }

    /**
     * Resolve the single yearly Sahodaya event a school registers into for a singleton
     * fest type. Returns null when there is no open event or the coordinator cannot
     * access exactly one (so the normal list/empty-state renders instead).
     */
    protected function resolveSingletonSchoolEvent(Request $request, string $eventType, string $programSlug): ?FestEvent
    {
        $events = FestEvent::where('tenant_id', $this->school->parent_id)
            ->ofType($eventType)
            ->primaryHub()
            ->listedForSchool($this->school->id, $eventType)
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'event_type', 'level_round', 'conducting_school_id', 'parent_event_id', 'partition_role', 'status', 'event_start'])
            ->pipe(fn ($rows) => app(\App\Services\School\SchoolUserScopeService::class)
                ->filterFestEventsForUser($request->user(), $this->school->id, $programSlug, $rows));

        return $events->count() === 1 ? $events->first() : null;
    }

    protected function resolveSchoolFestEvent(Request $request, FestEvent $event, string $programSlug): FestEvent
    {
        $meta = SchoolFestProgram::meta($programSlug);

        $resolved = FestEvent::query()
            ->whereKey($event->id)
            ->where('tenant_id', $this->school->parent_id)
            ->ofType($meta['eventType'])
            ->listedForSchool($this->school->id, $meta['eventType'])
            ->firstOrFail();

        $allowed = collect([$resolved])->pipe(fn ($rows) => app(\App\Services\School\SchoolUserScopeService::class)
            ->filterFestEventsForUser($request->user(), $this->school->id, $meta['slug'], $rows));

        abort_if($allowed->isEmpty(), 403);

        return $resolved;
    }

    private function registrationEventIdsForSchoolView($events)
    {
        $partitionService = app(\App\Services\Events\FestPartitionService::class);

        return collect($events)
            ->flatMap(function (FestEvent $event) use ($partitionService) {
                $ids = [$event->id];
                if ($partitionService->isPartitionedHub($event)) {
                    $ids = array_merge($ids, $partitionService->partitions($event)->pluck('id')->all());
                }

                return $ids;
            })
            ->unique()
            ->values();
    }

    /**
     * Legacy "Register by Event Head" entry — redirects to event registration
     * (items + athletes + fees are on one page after Head = Event unification).
     */
    public function itemRegistrationEntry(Request $request, string $tenantId, string $program = 'sports-meet')
    {
        $meta = SchoolFestProgram::meta($program);
        abort_unless($meta['eventType'] === 'sports', 404);

        $prefix = ProgramRouteMap::prefixFromSlug($program);
        $eventId = $request->query('event');

        if ($eventId) {
            return redirect("/school-admin/{$tenantId}/{$prefix}/events/{$eventId}/registration");
        }

        return redirect("/school-admin/{$tenantId}/{$prefix}/registration")
            ->with('info', 'Register athletes and items on each sport event.');
    }

    /**
     * Legacy per-head items URL — redirect to the unified registration page.
     */
    public function eventItemRegistration(Request $request, string $tenantId, FestEvent $event, string $program = 'sports-meet')
    {
        $meta = SchoolFestProgram::meta($program);
        abort_unless($meta['eventType'] === 'sports', 404);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $prefix = ProgramRouteMap::prefixFromSlug($program);

        return redirect("/school-admin/{$tenantId}/{$prefix}/events/{$event->id}/registration");
    }

    /** @return array<string, mixed> */
    private function sportsItemRegistrationEventPayload(FestEvent $event): array
    {
        return [
            'id'                         => $event->id,
            'title'                      => $event->title,
            'status'                     => $event->status,
            'schedule_published'         => (bool) ($event->schedule_published ?? false),
            'fee_required'               => (bool) ($event->fee_required ?? false),
            'require_event_registration' => (bool) ($event->require_event_registration ?? false),
            'require_verified_students'  => (bool) ($event->require_verified_students ?? true),
            'results_published'          => (bool) ($event->results_published ?? false),
            'items'                      => $event->getAttribute('items') ?? [],
            'item_group_labels'          => $event->getAttribute('item_group_labels') ?? [],
            'head_navigation'            => $event->getAttribute('head_navigation') ?? [],
            'event_registrations'        => $event->getAttribute('event_registrations') ?? [],
            'school_fee'                 => $event->getAttribute('school_fee'),
            'uses_per_head_billing'      => (bool) ($event->getAttribute('uses_per_head_billing') ?? false),
            'school_head_fees'           => $event->getAttribute('school_head_fees') ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSportsItemRow(
        FestEventItem $item,
        FestEvent $event,
        array $schedule,
        bool $feeRequired,
        FestItemFeeResolver $itemFeeResolver,
        \App\Services\Events\FestItemWindowResolver $windowResolver,
        \App\Services\Events\FestItemRegistrationGate $regGate,
    ): array {
        $item->setRelation('event', $event);

        return [
            'id'                => $item->id,
            'title'             => $item->title,
            'item_code'         => $item->item_code,
            'stage_type'        => $item->stage_type,
            'participant_type'  => $item->participant_type,
            'gender'            => $item->gender,
            'age_group'         => $item->age_group,
            'class_group'       => $item->class_group,
            'kids_band'         => $item->kids_band,
            'head_id'           => $item->head_id,
            'max_per_school'    => $item->max_per_school,
            'min_group_size'    => $item->min_group_size,
            'max_group_size'    => $item->max_group_size,
            'squad_summary'     => $item->squad_summary,
            'eligibility_label' => FestSportsAgeGroup::itemEligibilityLabel($item, $event),
            'item_fee'          => $feeRequired ? $itemFeeResolver->amountForItem($item, $schedule, $event) : null,
            'registration_open' => $regGate->isOpen($item),
            'reg_start'         => $windowResolver->effectiveRegStart($item)?->format('Y-m-d'),
            'reg_end'           => $windowResolver->effectiveRegEnd($item)?->format('Y-m-d'),
            'competition_start' => $windowResolver->effectiveCompetitionStart($item)?->format('Y-m-d'),
            'competition_end'   => $windowResolver->effectiveCompetitionEnd($item)?->format('Y-m-d'),
            'competition_line'  => $windowResolver->competitionLine($item),
        ];
    }

    private function hydrateEventForSchoolRegistration(
        FestEvent $event,
        FestSchoolEventFeeService $feeService,
        ?int $headId = null,
        ?array $headNav = null,
        ?\App\Services\Events\FestHeadItemNavigationService $navService = null,
    ): FestEvent {
        $navService ??= app(\App\Services\Events\FestHeadItemNavigationService::class);
        $limitService = new FestParticipationLimitService($event);
        $usage = $limitService->usageForSchool($this->school->id);
        $schedule = $feeService->resolveSchedule($event);
        $itemFeeResolver = app(FestItemFeeResolver::class);
        $schoolFee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->first();

        if ($feeService->feeRequired($event)) {
            $schoolFee = $feeService->recalculate($event, $this->school->id);
        }

        $event->setAttribute('level_label', config("fest_fees.level_labels.{$event->level_round}", $event->level_round));
        $event->setAttribute('payer_label', config("fest_fees.payer_labels.{$event->level_round}", ''));
        $event->setAttribute('fee_required', $feeService->feeRequired($event));
        $event->setAttribute('quotas', $usage);
        $event->setAttribute('school_fee', $schoolFee ? array_merge(
            $schoolFee->toArray(),
            ['breakdown' => $feeService->breakdown($event, $schoolFee, $schedule)]
        ) : null);

        $usesPerHead = $feeService->usesPerHeadBilling($event);
        $event->setAttribute('uses_per_head_billing', $usesPerHead);
        $event->setAttribute('school_head_fees', []);
        if ($usesPerHead && $feeService->feeRequired($event)) {
            $headFees = $feeService->recalculateAllHeadsForSchool($event, $this->school->id);
            $headsById = FestItemHead::where('event_id', $event->id)->get()->keyBy('id');
            $event->setAttribute('school_head_fees', $headFees->map(function (FestSchoolEventFee $fee) use ($feeService, $event, $schedule, $headsById) {
                $head = $headsById->get($fee->head_id);

                return [
                    'id' => $fee->id,
                    'head_id' => $fee->head_id,
                    'head_name' => $head?->name ?? 'Event head',
                    'school_registration_fee' => (float) $fee->school_registration_fee,
                    'student_registration_fee' => (float) $fee->student_registration_fee,
                    'participation_fee' => (float) $fee->participation_fee,
                    'participation_item_count' => (int) $fee->participation_item_count,
                    'total_due' => (float) $fee->total_due,
                    'amount_paid' => (float) $fee->amount_paid,
                    'outstanding' => (float) $fee->outstandingBalance(),
                    'status' => $fee->status,
                    'breakdown' => $feeService->breakdown($event, $fee, $schedule),
                ];
            })->values()->all());
        }
        $feeRequired = $feeService->feeRequired($event);
        $enabledItems = $event->items->filter(fn ($i) => ($i->is_enabled ?? true));
        if ($headId !== null) {
            $enabledItems = $enabledItems->filter(fn ($i) => (int) ($i->head_id ?? 0) === $headId);
        }
        $windowResolver = app(\App\Services\Events\FestItemWindowResolver::class);
        $regGate = app(\App\Services\Events\FestItemRegistrationGate::class);
        $enabledItems = $enabledItems->values()
            ->map(fn (FestEventItem $item) => $this->serializeSportsItemRow(
                $item,
                $event,
                $schedule,
                $feeRequired,
                $itemFeeResolver,
                $windowResolver,
                $regGate,
            ));
        $event->setAttribute('age_rule_summary', $event->event_type === 'sports'
            ? FestSportsAgeGroup::ageRuleSummary($event)
            : null);
        // Display copy: the casted attribute serializes as a UTC ISO timestamp
        // ("2026-12-30T18:30:00Z" for an IST 2026-12-31 cutoff — wrong day, ugly),
        // so ship a plain Y-m-d string alongside it. The cast stays intact because
        // eligibility/age-group code still reads the Carbon value after this.
        $event->setAttribute('sports_age_cutoff_display', $event->sports_age_cutoff_date?->format('Y-m-d'));
        $event->setAttribute('require_event_registration', (bool) $event->require_event_registration);
        $feeGate = app(\App\Services\Events\FestRegistrationFeeGate::class);
        $eventFeeCleared = $feeGate->isSchoolFeeCleared($event, $this->school->id);
        $downloadGate = app(\App\Services\School\SchoolDocumentDownloadGateService::class)
            ->payload($this->school, $event);
        $event->setAttribute('event_fee_cleared', $eventFeeCleared);
        $event->setAttribute('download_gate', $downloadGate);
        $event->setAttribute('fee_blocks_items', false);
        $event->setAttribute('school_fest_registration_closed', (bool) $this->school->fest_registration_closed);
        $event->setAttribute('registration_locked', (bool) $event->registration_locked);
        $event->setAttribute('allow_student_self_register', (bool) $event->allow_student_self_register);
        $event->setAttribute('require_verified_students', app(StudentVerificationGate::class)->requiredForEvent($event));
        $event->setAttribute('event_registrations', app(\App\Services\Events\FestEventRegistrationService::class)
            ->studentEventRegistrations($event, $this->school->id));
        if ($event->event_type === 'sports') {
            // Head = Event: no head tabs/filters on sports events — leftover
            // FestItemHead rows relinked to the sport event would otherwise render
            // a redundant single-head tab. Items group by age group instead.
            $event->setAttribute('head_navigation', [
                'headItemGroups'  => [],
                'headsForFilter'  => [],
                'hasItemHeads'    => false,
            ]);
            $schedule = $feeService->resolveSchedule($event);
            $event->setAttribute('student_event_reg_fee', (float) ($schedule['per_student_amount'] ?? 0));
        }
        $event->setAttribute('academic_year_label', $event->academicYear?->label);
        $verification = FestSchoolVerification::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->first();
        $event->setAttribute('verification_status', [
            'verification_day'     => $event->verification_day?->format('Y-m-d'),
            'documents_verified'   => (bool) ($verification->documents_verified ?? false),
            'verified_at'          => $verification?->verified_at?->toIso8601String(),
        ]);
        [$grouped, $groupLabels] = $this->groupItemsForEvent($event, $enabledItems);
        $event->setAttribute('items_grouped', $grouped);
        $event->setAttribute('item_group_labels', $groupLabels);
        $event->setAttribute('class_group_labels', FestClassGroupScheme::labels(null, $event));
        $event->setAttribute('class_group_scheme', FestClassGroupScheme::resolveForEvent($event, $schedule));
        $event->unsetRelation('items');
        $event->setAttribute('items', $enabledItems);

        return $event;
    }

    public function store(Request $request, string $tenantId, string $program)
    {
        $event = FestEvent::findOrFail($request->input('event_id'));

        $rules = [
            'event_id'       => 'required|exists:fest_events,id',
            'item_id'        => 'required|exists:fest_event_items,id',
            'team_name'      => 'nullable|string|max:255',
            'coach_name'     => 'nullable|string|max:255',
            'coach_phone'    => 'nullable|string|max:40',
            'manager_name'   => 'nullable|string|max:255',
            'manager_phone'  => 'nullable|string|max:40',
            'student_ids'    => $event->event_type === 'teacher_fest' ? 'nullable|array' : 'required|array|min:1',
            'student_ids.*'  => 'exists:students,id',
            'teacher_ids'    => $event->event_type === 'teacher_fest' ? 'required|array|min:1' : 'nullable|array',
            'teacher_ids.*'  => 'exists:teachers,id',
            'standby_ids'    => 'nullable|array|max:2',
            'standby_ids.*'  => 'exists:students,id',
        ];

        $data = $request->validate($rules);
        $item = FestEventItem::findOrFail($data['item_id']);

        $standbyIds = $data['standby_ids'] ?? [];
        $performerIds = $event->event_type === 'teacher_fest'
            ? array_values(array_unique($data['teacher_ids'] ?? []))
            : array_values(array_diff($data['student_ids'], $standbyIds));

        try {
            $registration = app(\App\Services\Events\FestRegistrationCreateService::class)->createForSchool(
                $event,
                $item,
                $this->school,
                $performerIds,
                $standbyIds,
                $data['team_name'] ?? null,
                teamContacts: [
                    'coach_name' => $data['coach_name'] ?? null,
                    'coach_phone' => $data['coach_phone'] ?? null,
                    'manager_name' => $data['manager_name'] ?? null,
                    'manager_phone' => $data['manager_phone'] ?? null,
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $messages = $e->errors();
            $mapped = [];
            foreach ($messages as $key => $errors) {
                if (in_array($key, ['student_ids', 'teacher_ids', 'standby_ids', 'registration'])) {
                    $mapped["items.{$item->id}"] = $errors;
                } else {
                    $mapped[$key] = $errors;
                }
            }
            throw \Illuminate\Validation\ValidationException::withMessages($mapped);
        }

        app(PlatformAuditLogger::class)->festRegistrationSubmitted($registration->fresh(['event', 'item']));

        $label = $event->event_type === 'teacher_fest' ? 'Teacher registration' : 'Registration';
        $message = match ($registration->status) {
            'waitlisted' => "{$label} added to the waiting list — a seat will open if capacity frees under this Event Head.",
            'pending_approval' => "{$label} submitted and awaiting Sahodaya approval for this Event Head.",
            default => "{$label} submitted for approval.",
        };

        return back()->with('success', $message);
    }

    public function update(Request $request, string $tenantId, FestRegistration $registration, string $program)
    {
        $event = FestEvent::findOrFail($registration->event_id);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($registration->school_id !== $this->school->id, 403);

        $item = FestEventItem::findOrFail($registration->item_id);

        $rules = [
            'team_name'      => 'nullable|string|max:255',
            'coach_name'     => 'nullable|string|max:255',
            'coach_phone'    => 'nullable|string|max:40',
            'manager_name'   => 'nullable|string|max:255',
            'manager_phone'  => 'nullable|string|max:40',
            'student_ids'    => $event->event_type === 'teacher_fest' ? 'nullable|array' : 'required|array|min:1',
            'student_ids.*'  => 'exists:students,id',
            'teacher_ids'    => $event->event_type === 'teacher_fest' ? 'required|array|min:1' : 'nullable|array',
            'teacher_ids.*'  => 'exists:teachers,id',
            'standby_ids'    => 'nullable|array|max:2',
            'standby_ids.*'  => 'exists:students,id',
        ];

        $data = $request->validate($rules);

        $standbyIds = $data['standby_ids'] ?? [];
        $performerIds = $event->event_type === 'teacher_fest'
            ? array_values(array_unique($data['teacher_ids'] ?? []))
            : array_values(array_diff($data['student_ids'], $standbyIds));

        try {
            $registration = app(\App\Services\Events\FestRegistrationCreateService::class)->updateForSchool(
                $registration,
                $event,
                $item,
                $this->school,
                $performerIds,
                $standbyIds,
                $data['team_name'] ?? null,
                teamContacts: [
                    'coach_name' => $data['coach_name'] ?? null,
                    'coach_phone' => $data['coach_phone'] ?? null,
                    'manager_name' => $data['manager_name'] ?? null,
                    'manager_phone' => $data['manager_phone'] ?? null,
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $messages = $e->errors();
            $mapped = [];
            foreach ($messages as $key => $errors) {
                if (in_array($key, ['student_ids', 'teacher_ids', 'standby_ids', 'registration'])) {
                    $mapped["items.{$item->id}"] = $errors;
                } else {
                    $mapped[$key] = $errors;
                }
            }
            throw \Illuminate\Validation\ValidationException::withMessages($mapped);
        }

        app(PlatformAuditLogger::class)->festRegistrationSubmitted($registration->fresh(['event', 'item']));

        return back()->with('success', 'Registration updated.');
    }

    public function uploadEventPayment(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $feeService = app(FestSchoolEventFeeService::class);
        abort_unless($feeService->feeRequired($event), 422, 'This event does not require a fee.');

        $usesPerHead = $feeService->usesPerHeadBilling($event);

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
            'bank_name'       => 'nullable|string|max:100',
            'amount'          => 'nullable|numeric|min:0.01',
            'head_id'         => ($usesPerHead ? 'required' : 'nullable').'|integer|exists:fest_item_heads,id',
        ]);

        if ($usesPerHead) {
            $feeService->attachPaymentForHead(
                $event,
                $this->school->id,
                (int) $data['head_id'],
                $request->file('payment_proof'),
                $request->user()->id,
                $data['transaction_ref'] ?? null,
                $data['bank_name'] ?? null,
                isset($data['amount']) ? (float) $data['amount'] : null,
            );
            $contextLabel = $event->title.' — '.((FestItemHead::find($data['head_id'])?->name) ?? 'head').' fee';
        } else {
            $feeService->attachPayment(
                $event,
                $this->school->id,
                $request->file('payment_proof'),
                $request->user()->id,
                $data['transaction_ref'] ?? null,
                $data['bank_name'] ?? null,
            );
            $contextLabel = $event->title.' event fee';
        }

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            [
                'school_name'    => $this->school->name,
                'context_label'  => $contextLabel,
            ],
            "/sahodaya-admin/{$this->school->parent_id}/events/{$event->id}/fees"
        );

        app(PlatformAuditLogger::class)->festFeeProofUploaded($event, $this->school->id);

        return back()->with('success', $usesPerHead
            ? 'Payment proof uploaded for this Event Head.'
            : 'Payment proof uploaded for this event.');
    }

    public function feeReceipt(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $feeService = app(FestSchoolEventFeeService::class);
        $query = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with('feeReceipt');

        if ($feeService->usesPerHeadBilling($event)) {
            $headId = (int) $request->query('head_id');
            abort_unless($headId > 0, 422, 'Select which Event Head receipt to view.');
            $query->where('head_id', $headId);
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('fest_school_event_fees', 'head_id')) {
            $query->whereNull('head_id');
        }

        $schoolFee = $query->firstOrFail();

        $receipt = $schoolFee->feeReceipt;
        abort_if(! $receipt || $receipt->status !== 'approved', 403, 'Receipt is not yet approved.');

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->when($schoolFee->head_id, function ($q) use ($schoolFee) {
                $q->whereHas('item', fn ($iq) => $iq->where('head_id', $schoolFee->head_id));
            })
            ->with('item')
            ->get();

        return view('receipts.fest-fee-official', [
            'receipt'        => $receipt,
            'schoolFee'      => $schoolFee,
            'breakdown'      => $feeService->breakdown($event, $schoolFee, $feeService->resolveSchedule($event)),
            'registrations'  => $registrations,
            'event'          => $event,
            'school'         => $this->school,
            'sahodaya'       => \App\Models\Tenant::findOrFail($this->school->parent_id),
        ]);
    }

    public function eventInvoice(Request $request, string $tenantId, FestEvent $event, string $program, FestInvoiceService $invoiceService)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $sahodaya = \App\Models\Tenant::findOrFail($this->school->parent_id);
        $invoice = $invoiceService->issueForSchool($event, $this->school);

        $pdf = Pdf::loadView('fest.finance.invoice', $invoiceService->invoiceViewData($event, $invoice, $sahodaya));

        if ($request->boolean('preview')) {
            return $pdf->stream($invoice->invoice_number.'.pdf');
        }

        return $pdf->download($invoice->invoice_number.'.pdf');
    }

    public function withdraw(Request $request, string $tenantId, FestRegistration $registration, string $program)
    {
        $event = FestEvent::findOrFail($registration->event_id);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($registration->school_id !== $this->school->id, 403);

        abort_unless(
            app(FestRegistrationService::class)->canSchoolCancel($registration, $event),
            422,
            'This registration can no longer be cancelled.'
        );

        app(FestRegistrationService::class)->cancel($registration, $event);

        app(PlatformAuditLogger::class)->festRegistrationCancelled($registration->fresh());

        return back()->with('success', 'Registration cancelled.');
    }

    public function festDay(string $tenantId, FestEvent $event, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if(! in_array($event->status, ['ongoing', 'registration_open', 'published'], true), 404);

        $participants = FestParticipant::whereHas('registration', fn ($q) => $q
            ->where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'approved'))
            ->with(['student', 'teacher', 'registration.item'])
            ->get();

        $rows = $participants->map(function (FestParticipant $p) {
            $schedule = FestSchedule::where('participant_id', $p->id)->first();

            return [
                'name'         => $p->student?->name ?? $p->teacher?->name,
                'item'         => $p->registration?->item?->title,
                'chest_no'     => $p->chest_no,
                'level_reg'    => $p->level_registration_number,
                'order'        => $schedule?->sort_order,
                'scheduled_at' => $schedule?->scheduled_at?->toIso8601String(),
                'stage'        => $schedule?->stage,
                'called'       => (bool) $schedule?->called_at,
            ];
        })->values();

        return $this->inertia('School/Events/FestDay', [
            'school'      => $this->school->only('id', 'name'),
            'event'       => $event->only('id', 'title', 'status', 'schedule_published', 'verification_day'),
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'rows'        => $rows,
            'verificationStatus' => $this->verificationStatusForSchool($event),
        ]);
    }

    /** @return array<string, mixed> */
    private function verificationStatusForSchool(FestEvent $event): array
    {
        $record = FestSchoolVerification::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->first();

        return [
            'verification_day'   => $event->verification_day?->format('Y-m-d'),
            'documents_verified' => (bool) ($record->documents_verified ?? false),
            'verified_at'        => $record?->verified_at?->toIso8601String(),
        ];
    }

    public function importTemplate(string $tenantId, string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        $program = $meta['slug'];
        $headers = ['item_id', 'item_title', 'reg_no', 'team_name', 'role'];
        $rows = [
            ['123', 'Mono Act', 'S2024001', '', 'performer'],
            ['123', 'Group Dance', 'S2024002', 'Team Alpha', 'performer'],
            ['123', 'Group Dance', 'S2024003', 'Team Alpha', 'performer'],
        ];

        if ($meta['eventType'] === 'sports') {
            $rows = [
                ['456', 'U14 — 100m Boys', 'S2024001', '', 'performer'],
                ['456', 'U14 — 100m Boys', 'S2024002', '', 'performer'],
                ['789', 'U14 — Football Boys', 'S2024003', 'Team A', 'performer'],
                ['789', 'U14 — Football Boys', 'S2024004', 'Team A', 'performer'],
            ];
        }

        return \App\Support\ExcelExport::download("fest-registration-{$program}-template", $headers, $rows);
    }

    public function importStore(Request $request, string $tenantId, string $program, FestRegistrationImportService $importService)
    {
        $data = $request->validate([
            'event_id' => 'required|exists:fest_events,id',
            'file'     => 'required|file|mimes:csv,txt,xls,xlsx|max:5120',
        ]);

        $event = FestEvent::findOrFail($data['event_id']);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        if ($this->school->fest_registration_closed) {
            return back()->with('error', 'Fest registration is closed for your school.');
        }

        abort_if($event->registration_locked, 422, 'Registration is locked for this event.');
        abort_if(! $event->isRegistrationOpen(), 422, 'Registration is closed for this event.');
        \App\Services\Events\EventLifecycleGate::allowRegistration($event);

        $result = $importService->importFromSpreadsheet(
            $event,
            $this->school,
            $request->file('file')->getRealPath(),
            $event->event_type === 'teacher_fest',
        );

        $message = "Imported {$result['imported']} registration(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped.";
        }
        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' error(s).';
        }

        return back()
            ->with($result['imported'] > 0 ? 'success' : 'error', $message)
            ->with('importErrors', array_slice($result['errors'], 0, 20));
    }

    public function programHub(string $tenantId, string $program = 'kalotsav')
    {
        $meta = SchoolFestProgram::meta($program);
        $hubData = app(\App\Services\Events\ProgramHubDataService::class)
            ->schoolFestHub($this->school, $program);

        return $this->inertia('School/Events/ProgramHub', array_merge($hubData, [
            'program'      => $meta,
            'schoolClasses' => $this->schoolClasses()->values(),
            'studentCount'  => Student::where('tenant_id', $this->school->id)->active()->count(),
            'eventType'     => $meta['eventType'],
            'studentEditLock' => app(StudentEditLockService::class)->metaForSchool($this->school),
        ]));
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    private function groupItemsForEvent(FestEvent $event, \Illuminate\Support\Collection $enabledItems): array
    {
        if ($event->event_type === 'sports') {
            $ageLabels = FestSportsAgeGroup::labels();
            $grouped = [];

            foreach ($enabledItems->groupBy(fn ($i) => $i['age_group'] ?: 'open') as $ageKey => $items) {
                $grouped[$ageKey] = $items->sortBy('title')->values();
            }

            $ordered = [];
            foreach (FestSportsAgeGroup::KEYS as $key) {
                if (! empty($grouped[$key])) {
                    $ordered[$key] = $grouped[$key];
                }
            }
            foreach ($grouped as $key => $items) {
                if (! isset($ordered[$key])) {
                    $ordered[$key] = $items;
                }
            }

            $labels = collect($ordered)->mapWithKeys(fn ($items, $key) => [
                $key => $ageLabels[$key] ?? strtoupper((string) $key),
            ])->all();

            return [$ordered, $labels];
        }

        if ($event->event_type === 'kids_fest') {
            $grouped = [
                'bands' => $enabledItems->sortBy('title')->values(),
            ];

            return [$grouped, ['bands' => 'Events']];
        }

        $grouped = [
            'on_stage'  => $enabledItems->where('stage_type', 'on_stage')->values(),
            'off_stage' => $enabledItems->where('stage_type', 'off_stage')->values(),
            'group'     => $enabledItems->filter(fn ($i) => in_array($i['participant_type'] ?? null, ['group', 'team'], true))->values(),
            'other'     => $enabledItems->filter(fn ($i) => empty($i['stage_type']) && ! in_array($i['participant_type'] ?? null, ['group', 'team'], true))->values(),
        ];

            return [$grouped, [
            'on_stage'  => 'On stage',
            'off_stage' => 'Off stage',
            'group'     => 'Group / team',
            'other'     => 'Other',
        ]];
    }
}
