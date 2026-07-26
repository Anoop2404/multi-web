<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCombinationRule;
use App\Models\FestEventClassGroup;
use App\Models\FestEventItem;
use App\Models\FestEvent;
use App\Models\FestGradeConfig;
use App\Models\FestPointRule;
use App\Models\FestStage;
use App\Models\FestVenue;
use App\Models\FestVolunteer;
use App\Models\FestSchoolVerification;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Support\Fest\FestEventSettingsPayload;
use App\Support\FestPageActivity;
use App\Support\FestClassGroupScheme;
use App\Support\FestSportsAgeGroup;
use App\Services\Events\EventContext;
use App\Services\Events\FestCloneService;
use App\Services\Events\FestEventFeeResolver;
use App\Services\Events\FestJudgeGateService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestLifecycleService;
use App\Services\Events\FestMandatoryItemService;
use App\Support\TenantStorage;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;

class FestEventSettingsController extends SahodayaAdminController
{
    public function settings(string $tenantId, FestEvent $event, ?string $tab = null)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $allowed = ['lifecycle', 'locks', 'venues', 'combo', 'grades', 'points', 'participation', 'eligibility', 'fees', 'registration', 'numbering', 'volunteers', 'records', 'clone'];
        $initialTab = ($tab && in_array($tab, $allowed, true)) ? $tab : 'lifecycle';

        if ($initialTab === 'eligibility' && $event->event_type !== 'sports') {
            $initialTab = 'lifecycle';
        }

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name']);

        $schoolNames = $schools->pluck('name', 'id');

        $feeService = app(FestSchoolEventFeeService::class);
        $schedule = $feeService->resolveSchedule($event);
        $classGroupScheme = FestClassGroupScheme::resolveForEvent($event, $schedule);

        $itemHeads = \App\Models\FestItemHead::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id', 'name', 'reg_start', 'reg_end', 'competition_start', 'competition_end',
                'default_item_fee', 'extra_item_fee',
                'school_registration_fee', 'student_registration_fee', 'team_registration_fee',
                'included_items_per_student', 'included_teams',
                'verification_policy', 'approval_policy',
                'max_participants', 'max_teams',
            ]);

        $ledgerAccount = app(\App\Services\Ledger\LedgerAccountSetupService::class)
            ->festLedgerMeta($event, $this->sahodaya->id);

        return $this->inertia('Sahodaya/Events/Settings', [
            'event'        => $event->load(['items.head']),
            'itemHeads'    => $itemHeads,
            'ledgerAccount'=> [
                'code'       => $ledgerAccount['code'],
                'name'       => $ledgerAccount['name'],
                'head_id'    => $ledgerAccount['head_id'],
                'ledger_url' => $ledgerAccount['ledger_url'],
            ],
            'feeSchedule'  => $schedule,
            'numberingSettings' => app(\App\Services\Events\FestNumberingService::class)->settings($event),
            'feeModels'    => config('fest_fees.fee_models'),
            'classGroupScheme' => $classGroupScheme,
            // Named, Sahodaya-wide category schemes (replaces the old fixed
            // cbse/sahodaya/cluster/custom dropdown) — auto-seeded once per tenant from
            // those same presets/live Class Master data so nothing already configured
            // breaks. See FestClassCategoryScheme::ensureDefaultsForTenant().
            'classCategorySchemes' => (function () {
                \App\Models\FestClassCategoryScheme::ensureDefaultsForTenant($this->sahodaya->id);

                $schemes = \App\Models\FestClassCategoryScheme::forTenant($this->sahodaya->id)
                    ->with('groups:id,scheme_id,key,label,description,classes,sort_order')
                    ->orderBy('sort_order')->orderBy('name')->get();

                // Annotate each scheme with which events currently reference it, so the UI
                // can warn before a delete ("3 events use this — you'll need to reassign
                // them to a different scheme") instead of silently orphaning that reference.
                // fee_settings is a JSON column read into PHP rather than queried with a raw
                // JSON path — event counts per tenant are small enough that this is simpler
                // and more portable than a DB-specific JSON where clause.
                $eventsBySchemeId = FestEvent::where('tenant_id', $this->sahodaya->id)
                    ->get(['id', 'title', 'fee_settings'])
                    ->groupBy(fn ($e) => (string) ($e->fee_settings['class_group_scheme'] ?? ''));

                foreach ($schemes as $scheme) {
                    $usingEvents = $eventsBySchemeId->get((string) $scheme->id, collect());
                    $scheme->events_count = $usingEvents->count();
                    $scheme->event_titles = $usingEvents->pluck('title')->values();
                }

                return $schemes;
            })(),
            'classGroupLabels' => FestClassGroupScheme::labels($classGroupScheme, $event),
            'defaultClassGroupFees' => FestClassGroupScheme::defaultFees($classGroupScheme, $event),
            // LEGACY — only still populated so an event that already saved the literal
            // 'custom' string keeps its old per-event categories visible/editable. New
            // category setups are created under classCategorySchemes above instead.
            'customClassGroups' => FestEventClassGroup::where('event_id', $event->id)
                ->orderBy('sort_order')->orderBy('label')->get(),
            'defaultParticipantTypeFees' => config('fest_fees.default_participant_type_fees'),
            'ageGroupLabels' => FestSportsAgeGroup::labels($this->sahodaya->id),
            'defaultAgeGroupFees' => FestSportsAgeGroup::defaultFees($this->sahodaya->id),
            'venues'       => FestVenue::where('event_id', $event->id)->with('region:id,name')->orderBy('name')->get(),
            'regions'      => \App\Models\Region::forTenant($this->sahodaya->id)->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'stages'       => FestStage::where('event_id', $event->id)
                ->with('venue:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'comboRules'   => FestCombinationRule::where('event_id', $event->id)->get()->map(fn ($r) => [
                ...$r->toArray(),
                'school_name' => $r->school_id ? ($schoolNames[$r->school_id] ?? $r->school_id) : null,
            ]),
            'gradeConfigs' => FestGradeConfig::where('event_id', $event->id)->with('item')->get(),
            'pointRules'   => FestPointRule::where('event_id', $event->id)->orderBy('grade')->orderBy('position')->get(),
            'rankPoints'   => app(FestRankPointService::class)->listForEvent($event),
            'groupRankPoints' => app(FestRankPointService::class)->listForEvent($event, true),
            'volunteers'   => FestVolunteer::where('event_id', $event->id)->orderBy('name')->get(),
            'schools'      => $schools,
            'judgeGate'    => app(FestJudgeGateService::class)->status($event),
            'lifecycle'    => FestLifecycleService::for($event)->checklist(),
            'suggestedStatus' => FestLifecycleService::for($event)->suggestedStatus(),
            'classGroups'  => FestClassGroupScheme::labels(null, $event),
            'initialTab'   => $initialTab,
            'participationPolicy' => \App\Models\FestParticipationPolicy::where('event_id', $event->id)->whereNull('class_group')->first(),
            'participationPresets' => app(\App\Services\Events\FestParticipationPolicyService::class)->presetOptions(),
            'ageRuleSummary' => $event->event_type === 'sports' ? FestSportsAgeGroup::ageRuleSummary($event) : null,
            'suggestedAgeCutoff' => $event->event_type === 'sports'
                ? FestSportsAgeGroup::cutoffDate($event)->format('Y-m-d')
                : null,
            'defaultCutoffLabel' => $this->defaultCutoffLabel($event),
            'ageGroupHelp' => $event->event_type === 'sports' ? $this->ageGroupHelp($event) : [],
            'schoolVerifications' => $this->schoolVerificationRows($event, $schools),
            'mandatoryGaps' => app(FestMandatoryItemService::class)->schoolsWithMissing($event),
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::settingsTab($initialTab)),
            'clusterRequireStudentVerification' => (bool) (
                SahodayaProfile::where('tenant_id', $this->sahodaya->id)->value('require_student_verification') ?? true
            ),
            // Event-level policy/notification settings (approval policy, capacity caps,
            // notification triggers) — see LocksTab.vue and
            // docs/KALOTSAV_ITEM_CATEGORY_REPLACES_HEAD_PLAN.md §5 #3. Unlike the
            // per-head notification editor in FestItemHeadOpsController (sports only),
            // this is available for every event type since FestEvent::notificationEnabledFor()
            // now backs the notification gate for events with no Event Head.
            'notificationTriggers' => FestEvent::NOTIFICATION_TRIGGERS,
            'eligibleNotificationUsers' => \App\Models\User::role(['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'])
                ->where('tenant_id', $this->sahodaya->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function schoolVerificationRows(FestEvent $event, $schools): array
    {
        $records = FestSchoolVerification::where('event_id', $event->id)
            ->get()
            ->keyBy('school_id');

        return $schools->map(function (Tenant $school) use ($records) {
            $record = $records->get($school->id);

            return [
                'school_id'           => $school->id,
                'school_name'         => $school->name,
                'documents_verified'  => (bool) ($record?->documents_verified ?? false),
                'verified_at'         => $record?->verified_at?->toIso8601String(),
                'notes'               => $record?->notes ?? null,
            ];
        })->values()->all();
    }

    public function updateLifecycleSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'verification_day' => 'nullable|date',
            'manual_pdf'       => 'nullable|file|mimes:pdf|max:10240',
            'remove_manual'    => 'nullable|boolean',
        ]);

        $updates = [
            'verification_day' => filled($data['verification_day'] ?? null) ? $data['verification_day'] : null,
        ];

        if ($request->boolean('remove_manual')) {
            $updates['manual_pdf_path'] = null;
        } elseif ($request->hasFile('manual_pdf')) {
            $updates['manual_pdf_path'] = TenantStorage::storeUploadedFile(
                $request->file('manual_pdf'),
                "fest-manuals/{$event->id}"
            );
        }

        $event->update($updates);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('lifecycle'),
            'fest.settings.lifecycle_saved',
            'Lifecycle settings saved',
            [
                'verification_day' => $event->verification_day?->format('Y-m-d'),
                'manual_pdf'       => filled($event->manual_pdf_path),
            ],
        );

        return back()->with('success', 'Lifecycle settings saved.');
    }

    public function updateEligibilitySettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->event_type !== 'sports', 422, 'Age cutoff applies to sports meets only.');

        $data = $request->validate([
            'sports_age_cutoff_date' => 'nullable|date',
        ]);

        $event->update([
            'sports_age_cutoff_date' => filled($data['sports_age_cutoff_date'] ?? null)
                ? $data['sports_age_cutoff_date']
                : null,
        ]);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('eligibility'),
            'fest.settings.age_cutoff_saved',
            'Sports age reference date saved',
            ['sports_age_cutoff_date' => $event->sports_age_cutoff_date?->format('Y-m-d')],
        );

        return back()->with('success', 'Age cutoff settings saved.');
    }

    /** @return list<array{key: string, label: string, under: int, minBirth: string}> */
    private function ageGroupHelp(FestEvent $event): array
    {
        $labels = FestSportsAgeGroup::labels($event->tenant_id);
        $groups = FestSportsAgeGroup::orderedAgeGroups($event->tenant_id);

        return collect($groups)->map(function (string $key) use ($event, $labels) {
            $under = FestSportsAgeGroup::underAge($key, $event->tenant_id);
            $minBirth = FestSportsAgeGroup::birthDateOnOrAfter($key, $event);

            return [
                'key'      => $key,
                'label'    => $labels[$key] ?? strtoupper($key),
                'under'    => $under,
                'minBirth' => $minBirth?->format('j M Y') ?? '—',
            ];
        })->all();
    }

    private function defaultCutoffLabel(FestEvent $event): string
    {
        if ($event->event_type !== 'sports') {
            return '';
        }

        $temp = new FestEvent([
            'event_type' => 'sports',
            'event_start' => $event->event_start,
            'event_end' => $event->event_end,
            'registration_close' => $event->registration_close,
            'sports_age_cutoff_date' => null,
        ]);

        $cutoff = FestSportsAgeGroup::cutoffDate($temp);

        return $cutoff->format('j M Y');
    }

    public function updateSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'scoring_locked'                      => 'nullable|boolean',
            'appeals_open'                        => 'nullable|boolean',
            'chest_reveal_mode'                   => 'nullable|in:immediate,stage_entry',
            'require_judge_scores_before_publish' => 'nullable|boolean',
            'require_all_marks_before_publish'    => 'nullable|boolean',
            'schedule_published'                  => 'nullable|boolean',
            'appeal_fee_amount'                   => 'nullable|numeric|min:0',
            'certificate_collection_open'         => 'nullable|boolean',
            'registration_locked'                 => 'nullable|boolean',
            'record_tracking_enabled'             => 'nullable|boolean',
            'default_record_prize_label'          => 'nullable|string|max:120',
            'student_verification_mode'           => 'nullable|in:inherit,required,optional',
            // Opt-in, per-event — see FestSchoolEventFeeService::itemPaymentAllocation()/
            // isPaidForRegistration() and docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.3.
            // Only has any effect for item_catalog/per_item billing; harmless to enable
            // on other fee models since itemPaymentAllocation() returns [] for those.
            'strict_item_payment_gating'          => 'nullable|boolean',
            // Event-level approval policy / capacity caps — read by FestEvent::requiresManualApproval()
            // as the fallback once an item has no Event Head (Kalotsav items assigned a plain
            // category instead). See docs/KALOTSAV_ITEM_CATEGORY_REPLACES_HEAD_PLAN.md §5 #3.
            'approval_policy'                     => 'nullable|in:auto,manual',
            'max_participants'                    => 'nullable|integer|min:0',
            'max_teams'                            => 'nullable|integer|min:0',
        ]);

        $data = FestEventSettingsPayload::applyDefaults($data);
        $verificationMode = $data['student_verification_mode'] ?? null;
        unset($data['student_verification_mode']);

        $event->update($data);
        $this->applyStudentVerificationMode($event, $verificationMode);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('locks'),
            'fest.settings.updated',
            'Event locks & gates saved',
        );

        return back()->with('success', 'Event settings saved.');
    }

    /**
     * Event-level notification gating — the equivalent of
     * FestItemHeadController::updateNotifications() but for the event itself, so it works
     * for events with no Event Head (Kalotsav items assigned a plain category). See
     * FestEventNotifier::suppressed() and docs/KALOTSAV_ITEM_CATEGORY_REPLACES_HEAD_PLAN.md §5 #3.
     */
    public function updateNotifications(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'disabled_triggers' => 'nullable|array',
            'disabled_triggers.*' => 'string|in:'.implode(',', FestEvent::NOTIFICATION_TRIGGERS),
            'extra_recipient_user_ids' => 'nullable|array',
            'extra_recipient_user_ids.*' => 'integer',
        ]);

        $disabledTriggers = array_values(array_unique($data['disabled_triggers'] ?? []));

        // Extra recipients must be existing platform users in this Sahodaya — never
        // free-text emails. Silently drop anything that doesn't resolve to a real,
        // appropriately-roled user rather than trusting the submitted id list as-is.
        $requestedIds = array_map('intval', $data['extra_recipient_user_ids'] ?? []);
        $validUserIds = $requestedIds === [] ? [] : \App\Models\User::role(['sahodaya_admin', 'sahodaya_staff', 'event_coordinator'])
            ->where('tenant_id', $this->sahodaya->id)
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $event->update([
            'notification_settings' => [
                'disabled_triggers' => $disabledTriggers,
                'extra_recipient_user_ids' => $validUserIds,
            ],
        ]);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('locks'),
            'fest.settings.notifications_updated',
            'Event notification settings updated',
            ['disabled_triggers' => $disabledTriggers, 'extra_recipient_count' => count($validUserIds)],
        );

        return back()->with('success', 'Notification settings saved.');
    }

    public function updateFeeSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'fee_model' => 'required|in:none,sports_composite,cksc_tiered,item_catalog,flat_school,per_item,per_student',
            'school_registration_flat' => 'nullable|numeric|min:0',
            'included_items_per_student' => 'nullable|integer|min:0|max:50',
            'first_item' => 'nullable|numeric|min:0',
            'additional_item' => 'nullable|numeric|min:0',
            'charge_standbys' => 'nullable|boolean',
            'team_standby_fee_amount' => 'nullable|numeric|min:0',
            'school_registration' => 'nullable|array',
            'school_registration.secondary' => 'nullable|numeric|min:0',
            'school_registration.senior_secondary' => 'nullable|numeric|min:0',
            'flat_amount' => 'nullable|numeric|min:0',
            'per_item_amount' => 'nullable|numeric|min:0',
            'per_student_amount' => 'nullable|numeric|min:0',
            'school_fee_cap' => 'nullable|numeric|min:0',
            'school_fee_min' => 'nullable|numeric|min:0',
            'include_school_registration' => 'nullable|boolean',
            // Accepts a numeric FestClassCategoryScheme id (the new named schemes) or, for
            // back-compat with events saved before that existed, the legacy string keys.
            'class_group_scheme' => ['nullable', function ($attribute, $value, $fail) {
                if ($value === null || $value === '') {
                    return;
                }
                if (in_array($value, ['cbse', 'sahodaya', 'cluster', 'custom'], true)) {
                    return;
                }
                if (! ctype_digit((string) $value) || ! \App\Models\FestClassCategoryScheme::forTenant($this->sahodaya->id)->whereKey($value)->exists()) {
                    $fail('The selected class category scheme is invalid.');
                }
            }],
            'class_group_fees' => 'nullable|array',
            'class_group_fees.*' => 'nullable|numeric|min:0',
            'age_group_fees' => 'nullable|array',
            'age_group_fees.*' => 'nullable|numeric|min:0',
            'participant_type_fees' => 'nullable|array',
            'participant_type_fees.group' => 'nullable|numeric|min:0',
            'participant_type_fees.team' => 'nullable|numeric|min:0',
            'default_item_fee' => 'nullable|numeric|min:0',
            'require_fee_before_registration' => 'nullable|boolean',
            'require_verified_students' => 'nullable|boolean',
            'head_fees' => 'nullable|array',
            'head_fees.*.id' => 'required|exists:fest_item_heads,id',
            'head_fees.*.default_item_fee' => 'nullable|numeric|min:0',
            'head_fees.*.extra_item_fee' => 'nullable|numeric|min:0',
            'head_fees.*.school_registration_fee' => 'nullable|numeric|min:0',
            'head_fees.*.student_registration_fee' => 'nullable|numeric|min:0',
            'head_fees.*.team_registration_fee' => 'nullable|numeric|min:0',
            'head_fees.*.included_items_per_student' => 'nullable|integer|min:0|max:50',
            'head_fees.*.included_teams' => 'nullable|integer|min:0|max:50',
            'head_fees.*.verification_policy' => 'nullable|in:verified_only,all_students',
            'head_fees.*.approval_policy' => 'nullable|in:auto,manual',
            'head_fees.*.max_participants' => 'nullable|integer|min:0',
            'head_fees.*.max_teams' => 'nullable|integer|min:0',
            'sport_event_fees' => 'nullable|array',
            'sport_event_fees.school_registration_fee' => 'nullable|numeric|min:0',
            'sport_event_fees.student_registration_fee' => 'nullable|numeric|min:0',
            'sport_event_fees.team_registration_fee' => 'nullable|numeric|min:0',
            'sport_event_fees.default_item_fee' => 'nullable|numeric|min:0',
            'sport_event_fees.extra_item_fee' => 'nullable|numeric|min:0',
            'sport_event_fees.included_items_per_student' => 'nullable|integer|min:0|max:50',
            'sport_event_fees.included_teams' => 'nullable|integer|min:0|max:50',
            'sport_event_fees.verification_policy' => 'nullable|in:verified_only,all_students',
            'sport_event_fees.approval_policy' => 'nullable|in:auto,manual',
            'sport_event_fees.max_participants' => 'nullable|integer|min:0',
            'sport_event_fees.max_teams' => 'nullable|integer|min:0',
            'item_fees' => 'nullable|array',
            'item_fees.*.id' => 'required|exists:fest_event_items,id',
            'item_fees.*.fee_amount' => 'nullable|numeric|min:0',
        ]);

        if ($event->event_type === 'sports') {
            $data['fee_model'] = 'sports_composite';
        }

        $feeSettings = array_merge(
            app(FestEventFeeResolver::class)->normalizeEventFeeSettings($data, $this->sahodaya->id),
            array_filter([
                'require_fee_before_registration' => array_key_exists('require_fee_before_registration', $data)
                    ? (bool) $data['require_fee_before_registration'] : null,
                'require_verified_students' => array_key_exists('require_verified_students', $data)
                    ? (bool) $data['require_verified_students'] : null,
                // normalizeEventFeeSettings() above only threads class_group_scheme through for
                // fee_model === 'item_catalog' — for every other billing model (which is most of
                // them, including this event's own Composite/sports_composite) it's entirely
                // absent from that method's return value, and since $feeSettings replaces the
                // whole fee_settings column wholesale, saving fee settings on a non-item_catalog
                // event would have silently wiped out whichever class category scheme was set
                // here. Re-applying it on top, independent of fee_model, is what makes class
                // categories actually persist for events like this one. Blank/invalid stays null
                // (omitted) so "inherit Sahodaya default" keeps working rather than getting
                // force-pinned to a value on every save.
                'class_group_scheme' => \App\Support\FestClassGroupScheme::isValid($data['class_group_scheme'] ?? null)
                    ? $data['class_group_scheme'] : null,
            ], fn ($v) => $v !== null),
        );

        $event->update(['fee_settings' => $feeSettings]);

        // Sports Head = Event: store composite fees on the FestEvent itself.
        if ($event->event_type === 'sports') {
            $eventFee = $data['sport_event_fees'] ?? $data;
            $numeric = fn (string $key) => isset($eventFee[$key]) && $eventFee[$key] !== '' && $eventFee[$key] !== null ? (float) $eventFee[$key] : null;
            $int = fn (string $key, int $default = 0) => isset($eventFee[$key]) && $eventFee[$key] !== '' && $eventFee[$key] !== null ? (int) $eventFee[$key] : $default;
            $intNullable = fn (string $key) => isset($eventFee[$key]) && $eventFee[$key] !== '' && $eventFee[$key] !== null ? (int) $eventFee[$key] : null;

            $updatePayload = [];

            if (array_key_exists('school_registration_fee', $eventFee)) {
                $updatePayload['school_registration_fee'] = $numeric('school_registration_fee');
            }
            if (array_key_exists('student_registration_fee', $eventFee)) {
                $updatePayload['student_registration_fee'] = $numeric('student_registration_fee');
            }
            if (array_key_exists('team_registration_fee', $eventFee)) {
                $updatePayload['team_registration_fee'] = $numeric('team_registration_fee');
            }
            if (array_key_exists('default_item_fee', $eventFee)) {
                $updatePayload['default_item_fee'] = $numeric('default_item_fee');
            }
            if (array_key_exists('extra_item_fee', $eventFee)) {
                $updatePayload['extra_item_fee'] = $numeric('extra_item_fee');
            }
            if (array_key_exists('included_items_per_student', $eventFee)) {
                $updatePayload['included_items_per_student'] = $int('included_items_per_student', 0);
            }
            if (array_key_exists('included_teams', $eventFee)) {
                $updatePayload['included_teams'] = $int('included_teams', 0);
            }
            if (array_key_exists('verification_policy', $eventFee)) {
                $updatePayload['verification_policy'] = $eventFee['verification_policy'];
            }
            if (array_key_exists('approval_policy', $eventFee)) {
                $updatePayload['approval_policy'] = $eventFee['approval_policy'];
            }
            if (array_key_exists('max_participants', $eventFee)) {
                $updatePayload['max_participants'] = $intNullable('max_participants');
            }
            if (array_key_exists('max_teams', $eventFee)) {
                $updatePayload['max_teams'] = $intNullable('max_teams');
            }

            if (! empty($updatePayload)) {
                $event->update($updatePayload);

                // Sync linked FestItemHead so legacy fallback in resolveSportsFeeSource never returns old values
                if ($event->source_head_id) {
                    \App\Models\FestItemHead::where('id', $event->source_head_id)->update($updatePayload);
                }
                \App\Models\FestItemHead::where('event_id', $event->id)->whereNull('parent_id')->update($updatePayload);
            }
        }

        foreach ($data['item_fees'] ?? [] as $row) {
            $item = FestEventItem::where('event_id', $event->id)->find($row['id']);
            if (! $item || $item->isStateCatalog()) {
                continue;
            }

            $item->update([
                'fee_amount' => isset($row['fee_amount']) && $row['fee_amount'] !== ''
                    ? (float) $row['fee_amount']
                    : null,
            ]);
        }

        foreach ($data['head_fees'] ?? [] as $row) {
            $numeric = fn (string $key) => isset($row[$key]) && $row[$key] !== '' ? (float) $row[$key] : null;
            $int = fn (string $key, int $default = 0) => isset($row[$key]) && $row[$key] !== '' ? (int) $row[$key] : $default;
            $intNullable = fn (string $key) => isset($row[$key]) && $row[$key] !== '' ? (int) $row[$key] : null;

            \App\Models\FestItemHead::where('event_id', $event->id)
                ->where('id', $row['id'])
                ->update([
                    'default_item_fee' => $numeric('default_item_fee'),
                    'extra_item_fee' => $numeric('extra_item_fee'),
                    'school_registration_fee' => $numeric('school_registration_fee'),
                    'student_registration_fee' => $numeric('student_registration_fee'),
                    'team_registration_fee' => $numeric('team_registration_fee'),
                    'included_items_per_student' => $int('included_items_per_student', 0),
                    'included_teams' => $int('included_teams', 0),
                    'verification_policy' => $row['verification_policy'] ?? 'all_students',
                    'approval_policy' => $row['approval_policy'] ?? 'auto',
                    'max_participants' => $intNullable('max_participants'),
                    'max_teams' => $intNullable('max_teams'),
                ]);
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('fees'),
            'fest.settings.fees_saved',
            'Fee settings saved',
        );

        return back()->with('success', 'Fee settings saved.');
    }

    public function updateLedgerAccount(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $setup = app(\App\Services\Ledger\LedgerAccountSetupService::class);
        $head = $setup->ensureFestEventHead($event);
        $setup->updateHeadName($head, $data['name']);

        return back()->with('success', 'Ledger account name saved.');
    }

    public function updateItemFee(Request $request, string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'fee_amount' => 'nullable|numeric|min:0',
        ]);

        $item->update([
            'fee_amount' => isset($data['fee_amount']) && $data['fee_amount'] !== ''
                ? (float) $data['fee_amount']
                : null,
        ]);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('fees'),
            'fest.settings.item_fee_updated',
            "Item fee updated: {$item->title}",
            ['item_id' => $item->id],
        );

        return back()->with('success', 'Item fee updated.');
    }

    public function storeVenue(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'location'  => 'nullable|string|max:255',
            'capacity'  => 'nullable|integer|min:1',
            'region_id' => 'nullable|exists:regions,id',
        ]);

        FestVenue::create(array_merge($data, [
            'tenant_id' => $this->sahodaya->id,
            'event_id'  => $event->id,
        ]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.venue_created',
            "Venue added: {$data['name']}",
        );

        return back()->with('success', 'Venue added.');
    }

    public function storeClassGroup(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'key' => [
                'required', 'string', 'max:60', 'alpha_dash',
                \Illuminate\Validation\Rule::unique('fest_event_class_groups', 'key')->where('event_id', $event->id),
            ],
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'classes' => 'nullable|array',
            'classes.*' => 'integer|min:1|max:12',
        ]);

        $nextOrder = (int) FestEventClassGroup::where('event_id', $event->id)->max('sort_order') + 1;

        FestEventClassGroup::create(array_merge($data, [
            'tenant_id' => $this->sahodaya->id,
            'event_id' => $event->id,
            'sort_order' => $nextOrder,
        ]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('fees'),
            'fest.settings.class_group_created',
            "Custom category added: {$data['label']}",
        );

        return back()->with('success', 'Category added.');
    }

    public function destroyClassGroup(string $tenantId, FestEvent $event, FestEventClassGroup $classGroup)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($classGroup->event_id !== $event->id, 404);

        $label = $classGroup->label;
        $classGroup->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('fees'),
            'fest.settings.class_group_deleted',
            "Custom category removed: {$label}",
        );

        return back()->with('success', 'Category removed.');
    }

    // --- Named class category schemes (Sahodaya-wide, not per-event) -----------------
    // Unlike storeClassGroup()/destroyClassGroup() above (legacy, one event's private
    // category list), these manage FestClassCategoryScheme — a named setup reusable across
    // every event in this Sahodaya. No $event in scope; these routes sit outside the
    // events/{event} prefix but still resolve $this->sahodaya from {tenantId} like every
    // other method on this controller.

    public function storeClassCategoryScheme(Request $request, string $tenantId)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('fest_class_category_schemes', 'name')->where('tenant_id', $this->sahodaya->id),
            ],
            'description' => 'nullable|string|max:500',
        ]);

        $nextOrder = (int) \App\Models\FestClassCategoryScheme::forTenant($this->sahodaya->id)->max('sort_order') + 1;

        \App\Models\FestClassCategoryScheme::create(array_merge($data, [
            'tenant_id' => $this->sahodaya->id,
            'sort_order' => $nextOrder,
        ]));

        return back()->with('success', 'Category scheme created.');
    }

    public function destroyClassCategoryScheme(string $tenantId, \App\Models\FestClassCategoryScheme $classCategoryScheme)
    {
        abort_if($classCategoryScheme->tenant_id !== $this->sahodaya->id, 403);

        $name = $classCategoryScheme->name;
        $classCategoryScheme->delete();

        return back()->with('success', "Category scheme \"{$name}\" removed.");
    }

    public function storeClassCategorySchemeGroup(Request $request, string $tenantId, \App\Models\FestClassCategoryScheme $classCategoryScheme)
    {
        abort_if($classCategoryScheme->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'key' => [
                'required', 'string', 'max:60', 'alpha_dash',
                \Illuminate\Validation\Rule::unique('fest_class_category_scheme_groups', 'key')->where('scheme_id', $classCategoryScheme->id),
            ],
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'classes' => 'nullable|array',
            'classes.*' => 'integer|min:1|max:12',
        ]);

        $nextOrder = (int) $classCategoryScheme->groups()->max('sort_order') + 1;

        $classCategoryScheme->groups()->create(array_merge($data, [
            'tenant_id' => $this->sahodaya->id,
            'sort_order' => $nextOrder,
        ]));

        return back()->with('success', 'Category added.');
    }

    public function updateClassCategorySchemeGroup(Request $request, string $tenantId, \App\Models\FestClassCategoryScheme $classCategoryScheme, \App\Models\FestClassCategorySchemeGroup $classCategorySchemeGroup)
    {
        abort_if($classCategoryScheme->tenant_id !== $this->sahodaya->id, 403);
        abort_if($classCategorySchemeGroup->scheme_id !== $classCategoryScheme->id, 404);

        $data = $request->validate([
            'key' => [
                'required', 'string', 'max:60', 'alpha_dash',
                \Illuminate\Validation\Rule::unique('fest_class_category_scheme_groups', 'key')
                    ->where('scheme_id', $classCategoryScheme->id)
                    ->ignore($classCategorySchemeGroup->id),
            ],
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'classes' => 'nullable|array',
            'classes.*' => 'integer|min:1|max:12',
        ]);

        $classCategorySchemeGroup->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroyClassCategorySchemeGroup(string $tenantId, \App\Models\FestClassCategoryScheme $classCategoryScheme, \App\Models\FestClassCategorySchemeGroup $classCategorySchemeGroup)
    {
        abort_if($classCategoryScheme->tenant_id !== $this->sahodaya->id, 403);
        abort_if($classCategorySchemeGroup->scheme_id !== $classCategoryScheme->id, 404);

        $classCategorySchemeGroup->delete();

        return back()->with('success', 'Category removed.');
    }

    public function updateVenue(Request $request, string $tenantId, FestEvent $event, FestVenue $venue)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($venue->event_id !== $event->id, 404);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'location'  => 'nullable|string|max:255',
            'capacity'  => 'nullable|integer|min:1',
            'region_id' => 'nullable|exists:regions,id',
        ]);

        $venue->update($data);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.venue_updated',
            "Venue updated: {$data['name']}",
            ['venue_id' => $venue->id],
        );

        return back()->with('success', 'Venue updated.');
    }

    public function destroyVenue(string $tenantId, FestEvent $event, FestVenue $venue)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($venue->event_id !== $event->id, 404);
        $name = $venue->name;
        $venue->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.venue_deleted',
            "Venue removed: {$name}",
        );

        return back()->with('success', 'Venue removed.');
    }

    public function storeStage(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'venue_id' => 'nullable|exists:fest_venues,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (! empty($data['venue_id'])) {
            abort_unless(
                FestVenue::where('event_id', $event->id)->where('id', $data['venue_id'])->exists(),
                422,
                'Venue does not belong to this event.'
            );
        }

        FestStage::create([
            'event_id'   => $event->id,
            'venue_id'   => $data['venue_id'] ?? null,
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? ((FestStage::where('event_id', $event->id)->max('sort_order') ?? 0) + 1),
            'is_active'  => true,
        ]);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.stage_created',
            "Stage added: {$data['name']}",
        );

        return back()->with('success', 'Stage added.');
    }

    public function destroyStage(string $tenantId, FestEvent $event, FestStage $stage)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($stage->event_id !== $event->id, 404);
        $name = $stage->name;
        $stage->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('venues'),
            'fest.settings.stage_deleted',
            "Stage removed: {$name}",
        );

        return back()->with('success', 'Stage removed.');
    }

    public function storeComboRule(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'school_id'          => 'nullable|string',
            'class_group'        => 'nullable|in:lp,up,hs,hss,open',
            'max_arts_events'    => 'nullable|integer|min:0',
            'max_sports_events'  => 'nullable|integer|min:0',
            'max_common_events'  => 'nullable|integer|min:0',
            'max_on_stage'       => 'nullable|integer|min:0',
            'max_off_stage'      => 'nullable|integer|min:0',
            'max_group'          => 'nullable|integer|min:0',
        ]);

        FestCombinationRule::create(array_merge($data, ['event_id' => $event->id]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('combo'),
            'fest.settings.combo_rule_created',
            'Combination rule saved',
        );

        return back()->with('success', 'Combination rule saved.');
    }

    public function destroyComboRule(string $tenantId, FestEvent $event, FestCombinationRule $comboRule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($comboRule->event_id !== $event->id, 404);
        $comboRule->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('combo'),
            'fest.settings.combo_rule_deleted',
            'Combination rule removed',
        );

        return back()->with('success', 'Combination rule removed.');
    }

    public function storeGradeConfig(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'item_id'   => 'nullable|exists:fest_event_items,id',
            'grade'     => 'required|in:A_plus,A,B,C',
            'min_score' => 'nullable|numeric|min:0',
            'max_score' => 'nullable|numeric|min:0',
        ]);

        FestGradeConfig::create(array_merge($data, ['event_id' => $event->id]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('grades'),
            'fest.settings.grade_band_created',
            "Grade band saved: {$data['grade']}",
        );

        return back()->with('success', 'Grade band saved.');
    }

    public function destroyGradeConfig(string $tenantId, FestEvent $event, FestGradeConfig $gradeConfig)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($gradeConfig->event_id !== $event->id, 404);
        $gradeConfig->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('grades'),
            'fest.settings.grade_band_deleted',
            'Grade band removed',
        );

        return back()->with('success', 'Grade band removed.');
    }

    public function storePointRule(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'grade'    => 'nullable|in:A_plus,A,B,C',
            'position' => 'nullable|integer|min:1|max:10',
            'points'   => 'required|integer|min:0',
            'is_group' => 'nullable|boolean',
        ]);

        FestPointRule::create(array_merge($data, [
            'event_id'  => $event->id,
            'is_group'  => $data['is_group'] ?? false,
        ]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rule_created',
            'Point rule saved',
            ['points' => $data['points']],
        );

        return back()->with('success', 'Point rule saved.');
    }

    public function destroyPointRule(string $tenantId, FestEvent $event, FestPointRule $pointRule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($pointRule->event_id !== $event->id, 404);
        $pointRule->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rule_deleted',
            'Point rule removed',
        );

        return back()->with('success', 'Point rule removed.');
    }

    public function updateRankPoints(Request $request, string $tenantId, FestEvent $event, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 422, 'Rank points apply to sports events only.');

        $data = $request->validate([
            'ranks'             => 'required|array|min:1',
            'ranks.*.rank'      => 'required|integer|min:1|max:255',
            'ranks.*.points'    => 'required|integer|min:0',
            'ranks.*.is_group'  => 'nullable|boolean',
            'is_group'          => 'nullable|boolean',
        ]);

        $isGroup = (bool) ($data['is_group'] ?? false);
        $count = $rankPoints->replaceForEvent($event, $data['ranks'], $isGroup);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.rank_points_updated',
            "Rank points master saved ({$count} rank(s))",
            ['count' => $count, 'is_group' => $isGroup],
        );

        return back()->with('success', "Rank points saved ({$count} rank(s)).");
    }

    public function seedRankPoints(string $tenantId, FestEvent $event, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 422, 'Rank points apply to sports events only.');

        $count = $rankPoints->seedAthleticsStandard($event);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.rank_points_seeded',
            'Athletics standard rank points loaded',
            ['count' => $count],
        );

        return back()->with('success', "Loaded athletics standard ({$count} ranks).");
    }

    public function storeVolunteer(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'duty'  => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        FestVolunteer::create(array_merge($data, ['event_id' => $event->id]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('volunteers'),
            'fest.settings.volunteer_created',
            "Volunteer added: {$data['name']}",
        );

        return back()->with('success', 'Volunteer added.');
    }

    public function destroyVolunteer(string $tenantId, FestEvent $event, FestVolunteer $volunteer)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($volunteer->event_id !== $event->id, 404);
        $name = $volunteer->name;
        $volunteer->delete();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('volunteers'),
            'fest.settings.volunteer_deleted',
            "Volunteer removed: {$name}",
        );

        return back()->with('success', 'Volunteer removed.');
    }

    public function cloneEvent(Request $request, string $tenantId, FestEvent $event, FestCloneService $cloneService)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate(['title' => 'required|string|max:255']);

        $clone = $cloneService->cloneEvent($event, $data['title']);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('clone'),
            'fest.settings.event_cloned',
            "Event cloned as \"{$clone->title}\"",
            ['clone_event_id' => $clone->id],
        );

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$clone->id}/settings")
            ->with('success', "Event cloned as \"{$clone->title}\".");
    }

    public function updateRegistrationSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'require_event_registration' => 'nullable|boolean',
            'event_reg_start' => 'nullable|date',
            'event_reg_end' => 'nullable|date',
            'allow_student_self_register' => 'nullable|boolean',
            'student_verification_mode' => 'nullable|in:inherit,required,optional',
        ]);

        $verificationMode = $data['student_verification_mode'] ?? null;
        unset($data['student_verification_mode']);

        $event->update([
            'require_event_registration' => (bool) ($data['require_event_registration'] ?? false),
            'event_reg_start' => $data['event_reg_start'] ?? null,
            'event_reg_end' => $data['event_reg_end'] ?? null,
            'allow_student_self_register' => (bool) ($data['allow_student_self_register'] ?? false),
        ]);

        $this->applyStudentVerificationMode($event, $verificationMode);

        return back()->with('success', 'Registration settings saved.');
    }

    private function applyStudentVerificationMode(FestEvent $event, ?string $mode): void
    {
        if ($mode === null) {
            return;
        }

        $feeSettings = is_array($event->fee_settings) ? $event->fee_settings : [];

        if ($mode === 'inherit') {
            unset($feeSettings['require_verified_students']);
        } elseif ($mode === 'required') {
            $feeSettings['require_verified_students'] = true;
        } elseif ($mode === 'optional') {
            $feeSettings['require_verified_students'] = false;
        }

        $event->update(['fee_settings' => $feeSettings]);
    }

    public function updateNumberingSettings(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'event_reg_start' => 'nullable|integer|min:1',
            'event_reg_prefix' => 'nullable|string|max:20',
            'chest_no_start' => 'nullable|integer|min:1',
            'chest_no_prefix' => 'nullable|string|max:20',
            'auto_assign_on_approve' => 'nullable|boolean',
            'auto_assign_chest_on_create' => 'nullable|boolean',
        ]);

        $event->update([
            'numbering_settings' => array_merge(
                app(\App\Services\Events\FestNumberingService::class)->settings($event),
                array_filter($data, fn ($v) => $v !== null)
            ),
        ]);

        return back()->with('success', 'Numbering settings saved.');
    }

    public function updateItemNumbering(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:fest_event_items,id',
            'items.*.chest_no_start' => 'nullable|integer|min:1',
            'items.*.item_reg_id_start' => 'nullable|integer|min:1',
        ]);

        $itemIds = FestEventItem::where('event_id', $event->id)->pluck('id')->all();

        foreach ($data['items'] as $row) {
            if (! in_array((int) $row['id'], $itemIds, true)) {
                continue;
            }
            FestEventItem::where('id', $row['id'])->update([
                'chest_no_start'      => $row['chest_no_start'] ?? null,
                'item_reg_id_start'   => $row['item_reg_id_start'] ?? null,
            ]);
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('numbering'),
            'fest.settings.item_numbering_updated',
            'Per-item chest and registration starts updated',
            ['count' => count($data['items'])],
        );

        return back()->with('success', 'Per-item numbering saved.');
    }

    public function updateItemWindows(Request $request, string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $data = $request->validate([
            'reg_start' => 'nullable|date',
            'reg_end' => 'nullable|date',
            'competition_start' => 'nullable|date',
            'competition_end' => 'nullable|date|after_or_equal:competition_start',
            'competition_time' => 'nullable|date_format:H:i',
            'item_reg_id_start' => 'nullable|integer|min:1',
            'chest_no_start' => 'nullable|integer|min:1',
            'head_id' => 'nullable|exists:fest_item_heads,id',
            'results_published_at' => 'nullable|date',
            'is_enabled' => 'nullable|boolean',
            'fee_amount' => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('fee_amount', $data)) {
            $data['fee_amount'] = isset($data['fee_amount']) && $data['fee_amount'] !== ''
                ? (float) $data['fee_amount']
                : null;
        }

        $item->update($data);

        return back()->with('success', 'Item registration window saved.');
    }

    /** Save every row of the "Per-item windows" table in one request instead of one PATCH per row. */
    public function bulkUpdateItemWindows(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'rows' => 'required|array',
            'rows.*.id' => 'required|integer|exists:fest_event_items,id',
            'rows.*.reg_start' => 'nullable|date',
            'rows.*.reg_end' => 'nullable|date',
            'rows.*.competition_start' => 'nullable|date',
            'rows.*.competition_end' => 'nullable|date|after_or_equal:rows.*.competition_start',
            'rows.*.head_id' => 'nullable|exists:fest_item_heads,id',
        ]);

        $updated = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $event, &$updated) {
            foreach ($data['rows'] as $row) {
                $item = FestEventItem::where('event_id', $event->id)->find($row['id']);
                if (! $item) {
                    continue;
                }

                $item->update([
                    'reg_start' => $row['reg_start'] ?? null,
                    'reg_end' => $row['reg_end'] ?? null,
                    'competition_start' => $row['competition_start'] ?? null,
                    'competition_end' => $row['competition_end'] ?? null,
                    'head_id' => $row['head_id'] ?? null,
                ]);
                $updated++;
            }
        });

        return back()->with('success', "Saved {$updated} item registration window(s).");
    }

    public function publishItemResults(string $tenantId, FestEvent $event, FestEventItem $item)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $item->update(['results_published_at' => now()]);

        return back()->with('success', 'Item results marked published.');
    }

    public function backfillLevelRegistrations(string $tenantId, FestEvent $event, FestLevelRegistrationService $service)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $count = $service->backfillEvent($event);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('lifecycle'),
            'fest.settings.level_registrations_backfilled',
            "Backfilled {$count} level registration number(s)",
            ['count' => $count],
        );

        return back()->with('success', "Backfilled {$count} level registration number(s).");
    }
}
