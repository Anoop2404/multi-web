<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestGroup;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\FestEventInvoice;
use App\Models\FestSchedule;
use App\Services\Events\FestInvoiceService;
use App\Services\Events\FestItemFeeResolver;
use App\Services\Events\FestLevelRegistrationService;
use App\Services\Events\FestParticipationLimitService;
use App\Services\Events\FestRegistrationEligibilityService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestRegistrationService;
use App\Services\Events\FestRegistrationImportService;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\FestSportsAgeGroup;
use App\Support\SchoolFestProgram;
use App\Services\Students\StudentEditLockService;
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
        $eventType = $meta['eventType'];
        $sahodayaId = $this->school->parent_id;
        $feeService = app(FestSchoolEventFeeService::class);

        $events = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($eventType)
            ->visibleToSchool($this->school->id)
            ->when($request->query('event'), fn ($q) => $q->where('id', $request->query('event')))
            ->when($view === 'results', fn ($q) => $q->where('results_published', true))
            ->when($view === 'registration', fn ($q) => $q->whereIn('status', ['published', 'registration_open', 'ongoing']))
            ->with('items')
            ->with('academicYear:id,label,status')
            ->orderByDesc('event_start')
            ->get()
            ->map(function (FestEvent $event) use ($feeService) {
                $limitService = new FestParticipationLimitService($event);
                $usage = $limitService->usageForSchool($this->school->id);
                $schedule = $feeService->resolveSchedule($event);
                $itemFeeResolver = app(FestItemFeeResolver::class);
                $schoolFee = FestSchoolEventFee::where('event_id', $event->id)
                    ->where('school_id', $this->school->id)
                    ->first();

                if (! $schoolFee && $feeService->feeRequired($event)) {
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
                $feeRequired = $feeService->feeRequired($event);
                $enabledItems = $event->items->filter(fn ($i) => ($i->is_enabled ?? true))->values()
                    ->map(function (FestEventItem $item) use ($event, $schedule, $feeRequired, $itemFeeResolver) {
                        $row = $item->toArray();
                        $row['eligibility_label'] = FestSportsAgeGroup::itemEligibilityLabel($item, $event);
                        $row['item_fee'] = $feeRequired
                            ? $itemFeeResolver->amountForItem($item, $schedule, $event)
                            : null;

                        return $row;
                    });
                $event->setAttribute('age_rule_summary', $event->event_type === 'sports'
                    ? FestSportsAgeGroup::ageRuleSummary($event)
                    : null);
                $event->setAttribute('sports_age_cutoff_date', $event->sports_age_cutoff_date?->format('Y-m-d'));
                $event->setAttribute('academic_year_label', $event->academicYear?->label);
                [$grouped, $groupLabels] = $this->groupItemsForEvent($event, $enabledItems);
                $event->setAttribute('items_grouped', $grouped);
                $event->setAttribute('item_group_labels', $groupLabels);
                $event->setAttribute('items', $enabledItems);

                return $event;
            });

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

        $registrations = FestRegistration::where('school_id', $this->school->id)
            ->whereIn('event_id', $events->pluck('id'))
            ->with(['event', 'item', 'participants.student', 'participants.group'])
            ->get();

        $studentRows = Student::where('tenant_id', $this->school->id)
            ->active()
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        $eligibilityService = app(FestRegistrationEligibilityService::class);
        $studentsByEvent = $events->mapWithKeys(function (FestEvent $event) use ($studentRows, $eligibilityService) {
            return [
                $event->id => $eligibilityService->annotateStudents($studentRows, $event)->values(),
            ];
        });

        return $this->inertia('School/Events/Registration', [
            'program'       => $program,
            'programMeta'   => $meta,
            'events'        => $events,
            'registrations' => $registrations,
            'students'      => $studentsByEvent->first() ?? [],
            'studentsByEvent' => $studentsByEvent,
            'schoolClasses' => $this->schoolClasses()->values(),
            'eventType'     => $eventType,
            'teachers'      => Teacher::where('tenant_id', $this->school->id)->active()->orderBy('name')->get(['id', 'name', 'reg_no', 'designation']),
            'isTeacherFest' => $eventType === 'teacher_fest',
            'presets'       => config('fest_participation_presets'),
            'studentEditLock' => app(StudentEditLockService::class)->metaForSchool($this->school),
            'focusEventId'    => $request->query('event') ? (int) $request->query('event') : null,
        ]);
    }

    public function store(Request $request, string $tenantId, string $program)
    {
        $event = FestEvent::findOrFail($request->input('event_id'));
        $isTeacherFest = $event->event_type === 'teacher_fest';

        $rules = [
            'event_id'       => 'required|exists:fest_events,id',
            'item_id'        => 'required|exists:fest_event_items,id',
            'team_name'      => 'nullable|string|max:255',
            'student_ids'    => $isTeacherFest ? 'nullable|array' : 'required|array|min:1',
            'student_ids.*'  => 'exists:students,id',
            'teacher_ids'    => $isTeacherFest ? 'required|array|min:1' : 'nullable|array',
            'teacher_ids.*'  => 'exists:teachers,id',
            'standby_ids'    => 'nullable|array|max:2',
            'standby_ids.*'  => 'exists:students,id',
        ];

        $data = $request->validate($rules);

        $item = FestEventItem::findOrFail($data['item_id']);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);
        abort_if($item->event_id !== $event->id, 403);
        abort_if($item->is_enabled === false, 422, 'This item is not open for registration.');

        if ($this->school->fest_registration_closed) {
            return back()->with('error', 'Fest registration is closed for your school.');
        }

        if ($event->registration_locked) {
            return back()->with('error', 'Registration is locked for this event.');
        }

        abort_if(! $event->isRegistrationOpen(), 422, 'Registration is closed for this event.');
        \App\Services\Events\EventLifecycleGate::allowRegistration($event);

        if ($isTeacherFest) {
            $performerIds = array_values(array_unique($data['teacher_ids'] ?? []));
            if (count($performerIds) > 1 && ! in_array($item->participant_type, ['group', 'team'], true)) {
                return back()->with('error', 'This item allows only one teacher.');
            }

            $registration = FestRegistration::create([
                'event_id'     => $data['event_id'],
                'item_id'      => $data['item_id'],
                'school_id'    => $this->school->id,
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($performerIds as $teacherId) {
                abort_if(Teacher::where('id', $teacherId)->where('tenant_id', $this->school->id)->doesntExist(), 403);
                FestParticipant::create([
                    'registration_id'  => $registration->id,
                    'teacher_id'       => $teacherId,
                    'participant_type' => 'teacher',
                    'participant_role' => 'performer',
                ]);
            }

            app(FestSchoolEventFeeService::class)->recalculate($event, $this->school->id);

            app(PlatformAuditLogger::class)->festRegistrationSubmitted($registration->fresh(['event', 'item']));

            return back()->with('success', 'Teacher registration submitted for approval.');
        }

        $standbyIds = $data['standby_ids'] ?? [];
        $performerIds = array_values(array_diff($data['student_ids'], $standbyIds));

        $isGroup = in_array($item->participant_type, ['group', 'team'], true);
        if ($isGroup) {
            $request->validate(['team_name' => 'required|string|max:255']);
            $count = count($performerIds);
            $error = $item->validateSquadCount($count);
            if ($error) {
                throw ValidationException::withMessages([
                    'items.'.$data['item_id'] => [$error],
                ]);
            }
        } elseif (count($performerIds) > 1) {
            throw ValidationException::withMessages([
                'items.'.$data['item_id'] => ['This item allows only one participant.'],
            ]);
        }

        $limitErrors = (new FestParticipationLimitService($event))
            ->validateRegistration($item, $this->school->id, $performerIds, $standbyIds);
        if ($limitErrors) {
            throw ValidationException::withMessages([
                'items.'.$data['item_id'] => $limitErrors,
            ]);
        }

        $eligibilityErrors = app(FestRegistrationEligibilityService::class)
            ->validateStudents($event, $item, array_merge($performerIds, $standbyIds));
        if ($eligibilityErrors) {
            throw ValidationException::withMessages([
                'items.'.$data['item_id'] => $eligibilityErrors,
            ]);
        }

        $registration = FestRegistration::create([
            'event_id'     => $data['event_id'],
            'item_id'      => $data['item_id'],
            'school_id'    => $this->school->id,
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        $groupId = null;
        if ($isGroup) {
            $group = FestGroup::create([
                'registration_id' => $registration->id,
                'team_name'       => $data['team_name'],
            ]);
            $groupId = $group->id;
        }

        foreach ($performerIds as $studentId) {
            abort_if(Student::where('id', $studentId)->where('tenant_id', $this->school->id)->doesntExist(), 403);
            FestParticipant::create([
                'registration_id'   => $registration->id,
                'group_id'          => $groupId,
                'student_id'        => $studentId,
                'participant_type'  => 'student',
                'participant_role'  => 'performer',
            ]);
        }

        foreach ($standbyIds as $studentId) {
            abort_if(Student::where('id', $studentId)->where('tenant_id', $this->school->id)->doesntExist(), 403);
            FestParticipant::create([
                'registration_id'   => $registration->id,
                'group_id'          => $groupId,
                'student_id'        => $studentId,
                'participant_type'  => 'student',
                'participant_role'  => 'standby',
            ]);
        }

        app(FestLevelRegistrationService::class)->syncRegistration($registration->fresh(['participants']));

        app(FestSchoolEventFeeService::class)->recalculate($event, $this->school->id);

        app(PlatformAuditLogger::class)->festRegistrationSubmitted($registration->fresh(['event', 'item']));

        return back()->with('success', 'Registration submitted for approval.');
    }

    public function uploadEventPayment(Request $request, string $tenantId, FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
            'bank_name'       => 'nullable|string|max:100',
        ]);

        $feeService = app(FestSchoolEventFeeService::class);
        abort_unless($feeService->feeRequired($event), 422, 'This event does not require a fee.');

        $feeService->attachPayment(
            $event,
            $this->school->id,
            $request->file('payment_proof'),
            $request->user()->id,
            $data['transaction_ref'] ?? null,
            $data['bank_name'] ?? null,
        );

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            [
                'school_name'    => $this->school->name,
                'context_label'  => $event->title.' event fee',
            ],
            "/sahodaya-admin/{$this->school->parent_id}/events/{$event->id}/fees"
        );

        app(PlatformAuditLogger::class)->festFeeProofUploaded($event, $this->school->id);

        return back()->with('success', 'Payment proof uploaded for this event.');
    }

    public function feeReceipt(FestEvent $event, string $program)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $schoolFee = FestSchoolEventFee::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->with('feeReceipt')
            ->firstOrFail();

        $receipt = $schoolFee->feeReceipt;
        abort_if(! $receipt || $receipt->status !== 'approved', 403, 'Receipt is not yet approved.');

        $registrations = FestRegistration::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->with('item')
            ->get();

        $feeService = app(FestSchoolEventFeeService::class);

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

    public function eventInvoice(FestEvent $event, string $program, FestInvoiceService $invoiceService)
    {
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        $sahodaya = \App\Models\Tenant::findOrFail($this->school->parent_id);
        $invoice = FestEventInvoice::where('event_id', $event->id)
            ->where('school_id', $this->school->id)
            ->first();

        if (! $invoice) {
            $invoice = $invoiceService->issueForSchool($event, $this->school);
        }

        return Pdf::loadView('fest.finance.invoice', [
            'event'    => $event,
            'invoice'  => $invoice,
            'sahodaya' => $sahodaya,
        ])->download($invoice->invoice_number.'.pdf');
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

    public function festDay(FestEvent $event, string $program)
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
            'event'       => $event->only('id', 'title', 'status', 'schedule_published'),
            'program'     => $meta['slug'],
            'programMeta' => $meta,
            'rows'        => $rows,
        ]);
    }

    public function importTemplate(string $program)
    {
        $meta = SchoolFestProgram::meta($program);
        $program = $meta['slug'];
        $headers = ['item_id', 'item_title', 'reg_no', 'team_name', 'role'];
        $sample = ['123', 'Mono Act', 'S2024001', '', 'performer'];

        return response()->streamDownload(function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fputcsv($out, ['123', 'Group Dance', 'S2024002', 'Team Alpha', 'performer']);
            fputcsv($out, ['123', 'Group Dance', 'S2024003', 'Team Alpha', 'performer']);
            fclose($out);
        }, "fest-registration-{$program}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function importStore(Request $request, string $tenantId, string $program, FestRegistrationImportService $importService)
    {
        $data = $request->validate([
            'event_id' => 'required|exists:fest_events,id',
            'file'     => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $event = FestEvent::findOrFail($data['event_id']);
        abort_if($event->tenant_id !== $this->school->parent_id, 403);

        if ($this->school->fest_registration_closed) {
            return back()->with('error', 'Fest registration is closed for your school.');
        }

        abort_if($event->registration_locked, 422, 'Registration is locked for this event.');
        abort_if(! $event->isRegistrationOpen(), 422, 'Registration is closed for this event.');
        \App\Services\Events\EventLifecycleGate::allowRegistration($event);

        $result = $importService->importFromCsv(
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
