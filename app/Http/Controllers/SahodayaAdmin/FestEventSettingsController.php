<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestCombinationRule;
use App\Models\FestEventClassGroup;
use App\Models\FestEventItem;
use App\Models\FestEvent;
use App\Models\FestGradeConfig;
use App\Models\FestPointRule;
use App\Models\FestRankPointTemplate;
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
use App\Services\Events\FestItemResultsService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Services\Events\FestRankPointService;
use App\Services\Events\FestLifecycleService;
use App\Services\Events\FestMandatoryItemService;
use App\Support\TenantStorage;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FestEventSettingsController extends SahodayaAdminController
{
    public function settings(string $tenantId, FestEvent $event, ?string $tab = null)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        // Grade Master and Rank Points moved to their own top-level pages — keep the
        // route accepting these tab values (removing them would 404 instead of
        // redirecting) so old bookmarks/links still land somewhere useful.
        if ($tab === 'grades') {
            return redirect()->route('sahodaya.events.grade-master.index', [$tenantId, $event->id]);
        }
        if ($tab === 'points') {
            return redirect()->route('sahodaya.events.rank-points.index', [$tenantId, $event->id]);
        }

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
            'feePresets'   => config('fest_fees.presets'),
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

    /** Score/percentage range → grade label bands. Non-sports only (sports ranks by measurement/position, not grade bands). */
    public function gradeMaster(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->event_type === 'sports', 404);

        $event->load('items');

        return $this->inertia('Sahodaya/Events/GradeMaster', $this->withEventActivity($event, FestPageActivity::settingsTab('grades'), [
            'event'        => $event,
            'gradeConfigs' => FestGradeConfig::where('event_id', $event->id)->with('item')->get(),
            'childEvents'  => $event->sportEventDropdownOptions(),
        ]));
    }

    /** Rank/grade → championship points. Sports: Individual + Team/Relay rank tables. Non-sports: Grade Points Master. */
    public function rankPoints(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load('items');

        return $this->inertia('Sahodaya/Events/RankPoints', $this->withEventActivity($event, FestPageActivity::settingsTab('points'), [
            'event'           => $event,
            'pointRules'      => FestPointRule::where('event_id', $event->id)->orderBy('grade')->orderBy('position')->get(),
            'templates'       => app(FestRankPointService::class)->templatesForEvent($event),
            'allParticipantTypes' => \App\Support\FestTeamSquadRules::ALL_TYPES,
            // Read-only here — the non-sports "Grade Points Master" rule form's Grade
            // dropdown is built from this, even though grades are edited on the
            // separate Grade Master page now.
            'gradeConfigs'    => FestGradeConfig::where('event_id', $event->id)->with('item')->get(),
            'childEvents'     => $event->sportEventDropdownOptions(),
        ]));
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

        // Cascade scoring_locked/schedule_published/registration_locked down onto every
        // region child — see FestRegionPartitionService::cascadeLifecycleToChildren().
        app(\App\Services\Events\FestRegionPartitionService::class)
            ->cascadeLifecycleToChildren($event, $data);

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
            'fee_model' => 'nullable|in:none,sports_composite,kalolsavam_composite,cksc_tiered,item_catalog,flat_school,per_item,per_student,student_count_slab',
            'school_registration_flat' => 'nullable|numeric|min:0',
            'included_items_per_student' => 'nullable|integer|min:0|max:50',
            'first_item' => 'nullable|numeric|min:0',
            'additional_item' => 'nullable|numeric|min:0',
            'charge_standbys' => 'nullable|boolean',
            'team_standby_fee_amount' => 'nullable|numeric|min:0',
            // Phase L — event-wide default for the group/team item per-participant
            // surcharge (flat_fee + rate × actual FestGroup participant count); item-level
            // override lives in item_fees.*.group_item_flat_fee/group_item_per_participant_rate
            // below. See FestItemFeeResolver::groupItemSurchargeAmount().
            'group_item_flat_fee' => 'nullable|numeric|min:0',
            'group_item_per_participant_rate' => 'nullable|numeric|min:0',
            // N-tier school registration map (Phase I) — no longer hard-limited to exactly
            // 'secondary'/'senior_secondary'; any tier key the admin adds/removes in the
            // fee-settings UI validates the same way. See SchoolClassCategoryResolver and
            // FestEventFeeResolver::normalizeSchoolRegistration().
            'school_registration' => 'nullable|array',
            'school_registration.*' => 'nullable|numeric|min:0',
            'flat_amount' => 'nullable|numeric|min:0',
            'per_item_amount' => 'nullable|numeric|min:0',
            'per_student_amount' => 'nullable|numeric|min:0',
            // Phase J — student_count_slab fee model's slab table.
            'student_count_slabs' => 'nullable|array',
            'student_count_slabs.*.min_count' => 'nullable|integer|min:0',
            'student_count_slabs.*.max_count' => 'nullable|integer|min:0',
            'student_count_slabs.*.amount' => 'nullable|numeric|min:0',
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
            'payment_bank_name' => 'nullable|string|max:255',
            'payment_account_no' => 'nullable|string|max:64',
            'payment_ifsc' => 'nullable|string|max:20',
            'payment_upi' => 'nullable|string|max:255',
            'payment_instructions' => 'nullable|string|max:5000',
            'payment_qr_code' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:3072',
            'remove_payment_qr_code' => 'nullable|boolean',
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
            'item_fees.*.group_item_flat_fee' => 'nullable|numeric|min:0',
            'item_fees.*.group_item_per_participant_rate' => 'nullable|numeric|min:0',
        ]);

        if (empty($data['fee_model'])) {
            $data['fee_model'] = $event->fee_settings['fee_model']
                ?? ($event->event_type === 'sports' ? 'sports_composite' : ($event->event_type === 'kalolsavam' ? 'kalolsavam_composite' : 'none'));
        }

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
                'payment_bank_name' => array_key_exists('payment_bank_name', $data)
                    ? (filled($data['payment_bank_name']) ? trim((string) $data['payment_bank_name']) : null) : null,
                'payment_account_no' => array_key_exists('payment_account_no', $data)
                    ? (filled($data['payment_account_no']) ? trim((string) $data['payment_account_no']) : null) : null,
                'payment_ifsc' => array_key_exists('payment_ifsc', $data)
                    ? (filled($data['payment_ifsc']) ? trim((string) $data['payment_ifsc']) : null) : null,
                'payment_upi' => array_key_exists('payment_upi', $data)
                    ? (filled($data['payment_upi']) ? trim((string) $data['payment_upi']) : null) : null,
                'payment_instructions' => array_key_exists('payment_instructions', $data)
                    ? (filled($data['payment_instructions']) ? trim((string) $data['payment_instructions']) : null) : null,
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

        $existingQrCode = $event->fee_settings['payment_qr_code'] ?? null;
        if ($request->hasFile('payment_qr_code')) {
            $existingQrCode = \App\Support\TenantStorage::storeUploadedFile($request->file('payment_qr_code'), 'payment_qr_codes', 'public');
        } elseif ($request->boolean('remove_payment_qr_code')) {
            $existingQrCode = null;
        }

        if ($existingQrCode !== null) {
            $feeSettings['payment_qr_code'] = $existingQrCode;
        } else {
            unset($feeSettings['payment_qr_code']);
        }

        $event->update(['fee_settings' => $feeSettings]);

        // A region/finale partition child saving its own fee settings directly means
        // this child's fees are no longer just an untouched copy of the hub's — protect
        // it from FestSchoolEventFeeService::propagateFeeSettingsToChildren() reverting
        // this save the next time the hub's own fee settings are saved. No-op (and never
        // read) for a standalone event or the hub itself.
        if ($event->parent_event_id !== null) {
            $event->updateQuietly(['fee_customized_at' => now()]);
        }

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
            if (! $item) {
                continue;
            }
            // State-catalog items are fully editable at the Sahodaya level (they're the ones
            // actually conducting them), so fee_amount is no longer skipped for owner_level='state'.

            $item->update([
                'fee_amount' => isset($row['fee_amount']) && $row['fee_amount'] !== ''
                    ? (float) $row['fee_amount']
                    : null,
                'group_item_flat_fee' => isset($row['group_item_flat_fee']) && $row['group_item_flat_fee'] !== ''
                    ? (float) $row['group_item_flat_fee']
                    : null,
                'group_item_per_participant_rate' => isset($row['group_item_per_participant_rate']) && $row['group_item_per_participant_rate'] !== ''
                    ? (float) $row['group_item_per_participant_rate']
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

        // If this is a partitioned hub, cascade the fee configuration just saved (schedule,
        // per-item overrides, per-head overrides, and — for sports — the composite fee
        // columns) down onto every region/cluster child. See propagateFeeSettingsToChildren()
        // for why this is needed: resolveSchedule() already redirects a child's schedule
        // LOOKUP to the hub, but registrations actually price against each child's own
        // FestEventItem/FestItemHead rows, which that read-side redirect never touches.
        app(FestSchoolEventFeeService::class)->propagateFeeSettingsToChildren($event->fresh());

        // Recalculate every already-registered school's fee now that the schedule
        // changed, instead of leaving it to the registration page's own read-time
        // recalculation (removed — see FestRegistrationController::
        // hydrateEventForSchoolRegistration()). Queued: see RecalculateEventSchoolFeesJob.
        \App\Jobs\RecalculateEventSchoolFeesJob::dispatch($event->id);

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

        // See the matching comment in updateFeeSettings() above: a region/finale child
        // editing one of its own item fees directly must survive the hub's next cascade.
        if ($event->parent_event_id !== null) {
            $event->updateQuietly(['fee_customized_at' => now()]);
        }

        // This quick single-item edit previously bypassed the hub->children fee cascade
        // entirely — only the "Fee settings" tab's bulk save triggered
        // propagateFeeSettingsToChildren(). Editing one item's fee here on the hub left
        // already-spawned region/finale children (and already-registered schools within
        // them) silently out of sync (Phase 6 audit). No-ops for non-hub events.
        app(\App\Services\Events\FestSchoolEventFeeService::class)->propagateFeeSettingsToChildren($event->fresh());

        // Same reasoning as updateFeeSettings() above — this single-item fee edit also
        // changes what schools owe, so recalculate eagerly here rather than relying on
        // the (now removed) read-time recalculation on the registration page.
        \App\Jobs\RecalculateEventSchoolFeesJob::dispatch($event->id);

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
            'region_id' => ['nullable', Rule::exists('regions', 'id')->where('tenant_id', $this->sahodaya->id)],
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

        $inUse = FestEvent::forTenant($this->sahodaya->id)
            ->where('fee_settings->class_group_scheme', (string) $classCategoryScheme->id)
            ->exists();
        abort_if($inUse, 422, 'This category scheme is used by one or more events. Reassign those events before deleting it.');

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

        $inUse = \App\Models\FestEventItem::where('class_group', $classCategorySchemeGroup->key)
            ->whereHas('event', fn ($query) => $query
                ->where('tenant_id', $this->sahodaya->id)
                ->where('fee_settings->class_group_scheme', (string) $classCategoryScheme->id))
            ->exists();
        abort_if($inUse, 422, 'This category is used by one or more event items. Reassign those items before deleting it.');

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
            'region_id' => ['nullable', Rule::exists('regions', 'id')->where('tenant_id', $this->sahodaya->id)],
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
            'venue_id' => ['nullable', Rule::exists('fest_venues', 'id')->where('event_id', $event->id)],
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
            'school_id'          => [
                'nullable',
                'string',
                Rule::exists('tenants', 'id')->where('parent_id', $this->sahodaya->id)->where('type', 'school'),
            ],
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

        $data = $this->validatedGradeConfig($request, $event);

        FestGradeConfig::create(array_merge($data, ['event_id' => $event->id]));

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('grades'),
            'fest.settings.grade_band_created',
            "Grade band saved: {$data['grade']}",
        );

        return back()->with('success', 'Grade band saved.');
    }

    public function updateGradeConfig(Request $request, string $tenantId, FestEvent $event, FestGradeConfig $gradeConfig)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($gradeConfig->event_id !== $event->id, 404);

        $data = $this->validatedGradeConfig($request, $event, $gradeConfig);

        $gradeConfig->update($data);

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('grades'),
            'fest.settings.grade_band_updated',
            "Grade band updated: {$data['grade']}",
        );

        return back()->with('success', 'Grade band updated.');
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

    /**
     * Shared by store/update so both reject an inverted range and a range that overlaps
     * another band already covering the same event+item (in the same mode — percentage
     * bands and raw-score bands on the same item are tracked as separate overlap groups,
     * since resolveGradeFromScore() only ever matches one mode per item).
     *
     * @return array<string, mixed>
     */
    private function validatedGradeConfig(Request $request, FestEvent $event, ?FestGradeConfig $existing = null): array
    {
        $data = $request->validate([
            'item_id'     => ['nullable', Rule::exists('fest_event_items', 'id')->where('event_id', $event->id)],
            // Free-text since 2026-10 — was 'in:A_plus,A,B,C'. Deliberately not a closed
            // list any more: this is what actually defines an event's grade vocabulary
            // (see FestGradePointService::validGradesForEvent(), which reads whatever
            // grade values exist on this event's FestGradeConfig rows). The regex just
            // keeps the label sane — letters/numbers/spaces/+/-/_ in any script.
            'grade'       => ['required', 'string', 'max:40', 'regex:/^[\pL\pN\s\+\-_]+$/u'],
            'min_score'   => 'nullable|numeric|min:0',
            'max_score'   => 'nullable|numeric|min:0',
            'min_percent' => 'nullable|numeric|min:0|max:100',
            'max_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // Pre-existing bug fixed in passing: nullable fields absent from the request body
        // entirely (not just sent as null) aren't guaranteed keys in validate()'s returned
        // array, so the direct $data['min_percent'] access below could throw "Undefined
        // array key" rather than the null it was presumably meant to read as.
        $usePercentage = ($data['min_percent'] ?? null) !== null || ($data['max_percent'] ?? null) !== null;
        $field = $usePercentage ? 'min_percent' : 'min_score';
        $min = (float) ($usePercentage ? ($data['min_percent'] ?? 0) : ($data['min_score'] ?? 0));
        $max = (float) ($usePercentage ? ($data['max_percent'] ?? 100) : ($data['max_score'] ?? 100));

        if ($min > $max) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'Minimum must not be greater than maximum.',
            ]);
        }

        $itemId = $data['item_id'] ?? null;

        $overlap = FestGradeConfig::where('event_id', $event->id)
            ->when($itemId !== null, fn ($q) => $q->where('item_id', $itemId), fn ($q) => $q->whereNull('item_id'))
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->get()
            ->first(function (FestGradeConfig $cfg) use ($usePercentage, $min, $max) {
                if (($cfg->min_percent !== null) !== $usePercentage) {
                    return false;
                }
                $cfgMin = (float) ($usePercentage ? ($cfg->min_percent ?? 0) : ($cfg->min_score ?? 0));
                $cfgMax = (float) ($usePercentage ? ($cfg->max_percent ?? 100) : ($cfg->max_score ?? 100));

                return $min <= $cfgMax && $max >= $cfgMin;
            });

        if ($overlap) {
            $overlapRange = $usePercentage
                ? "{$overlap->min_percent}%-{$overlap->max_percent}%"
                : "{$overlap->min_score}-{$overlap->max_score}";
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => "This range overlaps the existing {$overlap->grade} band ({$overlapRange}).",
            ]);
        }

        return $data;
    }

    public function storePointRule(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $gradePointService = app(\App\Services\Events\FestGradePointService::class);

        $data = $request->validate([
            'grade'    => ['nullable', $gradePointService->gradeValidationRule($event)],
            'position' => 'nullable|integer|min:1|max:10',
            'points'   => 'required|integer|min:0',
            'is_group' => 'nullable|boolean',
        ]);

        // gradeValidationRule() validates against display-form grades ("A+", matching what
        // FestGradePointService::pointsForMark() looks marks up by after normalizeGrade())
        // — convert to the same storage form FestPointRule.grade actually needs before
        // saving, mirroring FestGradeConfig's own A_plus-suffix convention for the legacy
        // four grades (custom labels pass through unchanged either way).
        if (! empty($data['grade'])) {
            $data['grade'] = $gradePointService->normalizeGrade($event, $data['grade']);
        }

        FestPointRule::create(array_merge($data, [
            'event_id'  => $event->id,
            'is_group'  => $data['is_group'] ?? false,
        ]));

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rule_created',
            'Point rule saved',
            ['points' => $data['points']],
        );

        return back()->with('success', 'Point rule saved.');
    }

    /**
     * One-click fill for the "Grade Points Master" table with the Confederation of Kerala
     * Sahodaya Complexes' State Kalolsavam Manual's official grade+place points (config/
     * fest_confed_kalotsav_scoring.php — the same numbers scoring_preset='confed_kalotsav'
     * uses directly, kept here so an event that isn't on that preset can still adopt the
     * identical table as ordinary, per-event-editable FestPointRule rows). updateOrCreate
     * so re-clicking after a manual tweak resets cleanly back to the standard instead of
     * erroring on duplicate (grade, position, is_group) rows.
     */
    public function seedConfedKalotsavPoints(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $gradePointService = app(\App\Services\Events\FestGradePointService::class);
        $tables = [
            false => config('fest_confed_kalotsav_scoring.individual_points', []),
            true => config('fest_confed_kalotsav_scoring.group_points', []),
        ];

        $count = 0;
        foreach ($tables as $isGroup => $grades) {
            foreach ($grades as $grade => $positions) {
                $normalizedGrade = $gradePointService->normalizeGrade($event, $grade);
                foreach ($positions as $position => $points) {
                    FestPointRule::updateOrCreate(
                        ['event_id' => $event->id, 'grade' => $normalizedGrade, 'position' => (int) $position, 'is_group' => $isGroup],
                        ['points' => $points],
                    );
                    $count++;
                }
            }
        }

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rules_seeded',
            'Loaded Kalolsavam Manual standard point rules',
            ['count' => $count],
        );

        return back()->with('success', 'Kalolsavam Manual standard point rules loaded.');
    }

    /**
     * Copies this event's current Grade Points Master rows down to every region-child
     * event under the same hub. FestPointRule is scoped by event_id, so a region child
     * (its own separate event) never inherits rules edited on the hub — marks entered
     * under a region keep using stale or default points until explicitly synced here.
     * updateOrCreate so re-running after further hub edits stays a clean re-sync.
     */
    public function syncPointRulesToRegions(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $regionChildren = $event->rootEvent()->childrenForRoles(['region'])
            ->reject(fn (FestEvent $child) => $child->id === $event->id)
            ->values();

        abort_if($regionChildren->isEmpty(), 422, 'No regions are linked to this event to sync to.');

        $rules = FestPointRule::where('event_id', $event->id)->get();

        foreach ($regionChildren as $child) {
            foreach ($rules as $rule) {
                FestPointRule::updateOrCreate(
                    [
                        'event_id' => $child->id,
                        'grade'    => $rule->grade,
                        'position' => $rule->position,
                        'is_group' => $rule->is_group,
                    ],
                    [
                        'points'       => $rule->points,
                        'points_table' => $rule->points_table,
                    ],
                );
            }
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rules_synced_to_regions',
            'Synced grade points master to all regions',
            ['regions' => $regionChildren->count(), 'rules' => $rules->count()],
        );

        $regionCount = $regionChildren->count();

        return back()->with('success', "Grade points synced to {$regionCount} region".($regionCount === 1 ? '' : 's').'.');
    }

    public function destroyPointRule(string $tenantId, FestEvent $event, FestPointRule $pointRule)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($pointRule->event_id !== $event->id, 404);
        $pointRule->delete();

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.point_rule_deleted',
            'Point rule removed',
        );

        return back()->with('success', 'Point rule removed.');
    }

    public function storeRankTemplate(Request $request, string $tenantId, FestEvent $event, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_unless($event->event_type === 'sports', 422, 'Rank point templates apply to sports events only.');

        $data = $request->validate(['name' => 'required|string|max:100']);

        $template = $rankPoints->createTemplate($event, $data['name']);

        app(PlatformAuditLogger::class)->festEvent(
            $event, FestPageActivity::settingsTab('points'), 'fest.settings.rank_template_created',
            "Rank point template \"{$template->name}\" created", ['template_id' => $template->id],
        );

        return back()->with('success', "Template \"{$template->name}\" created.");
    }

    public function updateRankTemplate(Request $request, string $tenantId, FestEvent $event, FestRankPointTemplate $template, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($template->event_id !== $event->id, 404);

        $data = $request->validate([
            'name'                 => 'required|string|max:100',
            'participant_types'    => 'nullable|array',
            'participant_types.*'  => ['string', Rule::in(\App\Support\FestTeamSquadRules::ALL_TYPES)],
        ]);

        $rankPoints->renameTemplate($template, $data['name']);
        $rankPoints->assignTypes($template, $data['participant_types'] ?? []);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event, FestPageActivity::settingsTab('points'), 'fest.settings.rank_template_updated',
            "Rank point template \"{$template->name}\" updated", [
                'template_id' => $template->id,
                'participant_types' => $data['participant_types'] ?? [],
            ],
        );

        return back()->with('success', "Template \"{$template->name}\" saved.");
    }

    public function destroyRankTemplate(string $tenantId, FestEvent $event, FestRankPointTemplate $template, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($template->event_id !== $event->id, 404);

        $name = $template->name;
        $rankPoints->deleteTemplate($template);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event, FestPageActivity::settingsTab('points'), 'fest.settings.rank_template_deleted',
            "Rank point template \"{$name}\" deleted", ['template_name' => $name],
        );

        return back()->with('success', "Template \"{$name}\" deleted.");
    }

    public function updateRankPoints(Request $request, string $tenantId, FestEvent $event, FestRankPointTemplate $template, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($template->event_id !== $event->id, 404);

        $data = $request->validate([
            'ranks'          => 'required|array|min:1',
            'ranks.*.rank'   => 'required|integer|min:1|max:255',
            'ranks.*.points' => 'required|integer|min:0',
        ]);

        $count = $rankPoints->replaceRows($template, $data['ranks']);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.rank_points_updated',
            "Rank points for \"{$template->name}\" saved ({$count} rank(s))",
            ['template_id' => $template->id, 'count' => $count],
        );

        return back()->with('success', "Rank points saved ({$count} rank(s)).");
    }

    public function seedRankPoints(string $tenantId, FestEvent $event, FestRankPointTemplate $template, FestRankPointService $rankPoints)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($template->event_id !== $event->id, 404);

        $count = $rankPoints->seedAthleticsStandard($template);

        EventContext::for($event)->recalculateSchoolPoints();

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::settingsTab('points'),
            'fest.settings.rank_points_seeded',
            "Athletics standard loaded into \"{$template->name}\"",
            ['template_id' => $template->id, 'count' => $count],
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
            'event_reg_end' => 'nullable|date|after_or_equal:event_reg_start',
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
            'items.*.id' => ['required', 'integer', Rule::exists('fest_event_items', 'id')->where('event_id', $event->id)],
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
            'reg_end' => 'nullable|date|after_or_equal:reg_start',
            'competition_start' => 'nullable|date',
            'competition_end' => 'nullable|date|after_or_equal:competition_start',
            'competition_time' => 'nullable|date_format:H:i',
            'item_reg_id_start' => 'nullable|integer|min:1',
            'chest_no_start' => 'nullable|integer|min:1',
            'head_id' => ['nullable', Rule::exists('fest_item_heads', 'id')->where('event_id', $event->id)],
            'is_enabled' => 'nullable|boolean',
            'fee_amount' => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('fee_amount', $data)) {
            $data['fee_amount'] = isset($data['fee_amount']) && $data['fee_amount'] !== ''
                ? (float) $data['fee_amount']
                : null;
        }

        $item->update($data);

        // Registration windows are legitimately region-specific and are NOT cascaded — a
        // region can run its own registration window independent of siblings. fee_amount is
        // the one field on this form that IS supposed to follow the hub->children cascade
        // (Phase 6 audit), so re-run it when this endpoint touched fee_amount on a hub event.
        if (array_key_exists('fee_amount', $data)) {
            app(\App\Services\Events\FestSchoolEventFeeService::class)->propagateFeeSettingsToChildren($event->fresh());
        }

        return back()->with('success', 'Item registration window saved.');
    }

    /** Save every row of the "Per-item windows" table in one request instead of one PATCH per row. */
    public function bulkUpdateItemWindows(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'rows' => 'required|array',
            'rows.*.id' => ['required', 'integer', Rule::exists('fest_event_items', 'id')->where('event_id', $event->id)],
            'rows.*.reg_start' => 'nullable|date',
            'rows.*.reg_end' => 'nullable|date|after_or_equal:rows.*.reg_start',
            'rows.*.competition_start' => 'nullable|date',
            'rows.*.competition_end' => 'nullable|date|after_or_equal:rows.*.competition_start',
            'rows.*.head_id' => ['nullable', Rule::exists('fest_item_heads', 'id')->where('event_id', $event->id)],
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

    public function publishItemResults(
        string $tenantId,
        FestEvent $event,
        FestEventItem $item,
        FestItemResultsService $results,
        PlatformAuditLogger $audit,
    )
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 404);

        $results->publishItem($item);
        EventContext::for($event)->recalculateSchoolPoints();

        $audit->festEvent(
            $event,
            FestPageActivity::settingsTab('lifecycle'),
            'fest.item_results.published',
            "Published results for {$item->title}",
            ['item_id' => $item->id],
        );

        return back()->with('success', 'Item results published.');
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
