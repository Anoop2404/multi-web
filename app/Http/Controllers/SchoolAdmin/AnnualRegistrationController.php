<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FestEventPhase;
use App\Models\MembershipPayment;
use App\Models\Region;
use App\Models\Registration;
use App\Models\SchoolRegionAssignment;
use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\SchoolClass;
use App\Models\SchoolYearStudentCount;
use App\Models\Student;
use App\Services\Membership\SchoolYearSubmissionReviewService;
use App\Models\SubmissionStudent;
use App\Models\SubmissionTeacher;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\UploadBackupService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Membership\FeeReceiptService;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\MembershipPaymentProofService;
use App\Services\Membership\MembershipRegistrationWindowService;
use App\Services\Membership\RegistrationStatusService;
use App\Support\AcademicYear;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnualRegistrationController extends SchoolAdminController
{
    public function index(EffectiveMasterDataResolver $resolver)
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $sahodaya = $this->school->parent;
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $registration = Registration::where('school_id', $this->school->id)->where('academic_year', $academicYear)->first();
        if ($registration) {
            $registration = app(RegistrationStatusService::class)->ensureMembershipNumber($registration);
            if (! in_array($registration->registration_status, ['completed', 'approved'], true)) {
                $registration = app(RegistrationStatusService::class)->ensureMembershipFee($registration);
            }
        }
        $window = $profile
            ? SahodayaRegistrationWindow::where('sahodaya_id', $sahodaya->id)->where('academic_year', $academicYear)->first()
            : null;
        $windowService = app(MembershipRegistrationWindowService::class);
        $windowBlockReason = $windowService->blockReason($window);

        $payments = $registration
            ? MembershipPayment::where('school_id', $this->school->id)
                ->where('academic_year', $academicYear)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('created_at')
                ->get()
                ->each(fn ($payment) => $payment->setRelation('school', $this->school))
            : collect();

        $yearOptions = AcademicYear::options();
        $priorYear = null;
        $currentIndex = array_search($academicYear, $yearOptions, true);
        if ($currentIndex !== false && isset($yearOptions[$currentIndex + 1])) {
            $priorYear = $yearOptions[$currentIndex + 1];
        }

        $priorRegistration = $priorYear
            ? Registration::where('school_id', $this->school->id)->where('academic_year', $priorYear)->first()
            : null;

        $isRenewal = ! $registration
            && $priorRegistration
            && in_array($priorRegistration->registration_status, ['completed', 'approved'], true);

        $priorYearSummary = $priorRegistration ? [
            'academic_year'        => $priorRegistration->academic_year,
            'reg_no'               => $priorRegistration->reg_no,
            'registration_status'  => $priorRegistration->registration_status,
            'membership_fee_amount'=> $priorRegistration->membership_fee_amount,
        ] : null;

        $profilePayload = $profile ? array_merge($profile->toArray(), [
            'payment_details_text' => $profile->paymentDetailsText(),
            'membership_fee_configured' => $profile->membershipFeeConfigured($academicYear),
            'requires_membership_payment' => $profile->requiresMembershipPaymentForSchool($this->school),
        ]) : null;

        $feeNotConfigured = $profile && ! $profile->membershipFeeConfigured($academicYear);

        $regions = Region::forTenant($sahodaya->id)
            ->active()
            ->globalOnly()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        // §7.3 item 4 (docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md): most Sahodayas have zero or
        // one regional phase group, so they keep today's single Sahodaya-wide picker exactly
        // as-is. Only once an event has 2+ regional phase groups (e.g. Off Stage + Sargadhara)
        // does the UI switch to one independent picker per group.
        $regionalGroups = $regions->isEmpty() ? [] : $this->regionalPhaseGroups($sahodaya->id);

        $selectedRegionId = null;
        $selectedRegionsByGroup = [];
        if (count($regionalGroups) >= 2) {
            $selectedRegionsByGroup = SchoolRegionAssignment::forTenant($sahodaya->id)
                ->forYear($academicYear)
                ->where('school_id', $this->school->id)
                ->whereIn('partition_group', array_column($regionalGroups, 'key'))
                ->pluck('region_id', 'partition_group')
                ->all();
        } else {
            $regionalGroups = [];
            $selectedRegionId = $regions->isEmpty() ? null : SchoolRegionAssignment::forTenant($sahodaya->id)
                ->forYear($academicYear)
                ->where('school_id', $this->school->id)
                ->forPartitionGroup(null)
                ->value('region_id');
        }

        return $this->inertia('School/Registration/Index', [
            'academicYear'       => $academicYear,
            'regions'            => $regions,
            'regionalGroups'     => $regionalGroups,
            'selectedRegionId'   => $selectedRegionId,
            'selectedRegionsByGroup' => $selectedRegionsByGroup,
            'registration'       => $registration?->load('submission'),
            'profile'            => $profilePayload,
            'registrationWindow' => $windowService->displayPayload($window),
            'registrationWindowBlockReason' => $windowBlockReason,
            'membershipFeeNotConfigured' => $feeNotConfigured,
            'payments'           => $payments,
            'canBegin'           => $profile
                && ! $registration
                && ! empty($this->school->school_prefix)
                && ! $windowBlockReason
                && ! $feeNotConfigured,
            'isRenewal'          => $isRenewal,
            'priorYearSummary'   => $priorYearSummary,
            'membershipFeePreview' => $profile && $profile->membership_fee_type === 'none'
                ? 0
                : ($profile && $profile->membership_fee_type === 'fixed'
                ? $profile->fixed_membership_fee_amount
                : ($profile && $profile->membership_fee_type === 'variable_by_student_count'
                    ? app(\App\Services\Membership\MembershipFeeCalculator::class)->estimateFeeForSchool($this->school, $academicYear)
                    : null)),
            'membershipFeeEstimateStudents' => $profile && $profile->membership_fee_type === 'variable_by_student_count'
                ? app(\App\Services\Membership\MembershipFeeCalculator::class)->estimateStudentCount($this->school, $academicYear)
                : null,
            'trackStatus' => $registration?->submission ? [
                'full_records' => $registration->submission->full_records_status,
                'counts'       => $registration->submission->counts_status,
                'teachers'     => $registration->submission->teacher_status,
            ] : null,
            'trackRejectionReasons' => $registration?->submission ? array_filter([
                'full_records' => $registration->submission->full_records_rejection_reason,
                'counts'       => $registration->submission->counts_rejection_reason,
                'teachers'     => $registration->submission->teacher_rejection_reason,
            ]) : null,
            'membershipReceiptPaymentId' => $payments->firstWhere('status', 'verified')?->id,
        ]);
    }

    public function begin(RegistrationStatusService $service, MembershipRegistrationWindowService $windowService)
    {
        $academicYear = AcademicYear::forSchool($this->school);
        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();
        abort_unless($profile && $profile->membershipFeeConfigured($academicYear), 422, 'Membership fees are not configured yet. Contact your Sahodaya office.');

        $window = $windowService->forSchool($this->school, $academicYear);
        if ($reason = $windowService->blockReason($window)) {
            return redirect("/school-admin/{$this->school->id}/registration")
                ->with('error', $reason);
        }

        $alreadyStarted = Registration::where('school_id', $this->school->id)
            ->where('academic_year', $academicYear)
            ->exists();

        try {
            $registration = $service->beginAnnualRegistration($this->school);
        } catch (\RuntimeException $e) {
            return redirect("/school-admin/{$this->school->id}/registration")
                ->with('error', $e->getMessage());
        }

        if (! $alreadyStarted) {
            app(DataChangeLogger::class)->created(
                $registration,
                "Annual membership started: {$registration->reg_no}",
                $this->school->id,
                'membership',
            );
        }

        $message = $alreadyStarted
            ? "Annual membership in progress. Membership No: {$registration->reg_no}"
            : "Annual membership started. Membership No: {$registration->reg_no}";

        return redirect("/school-admin/{$this->school->id}/registration")
            ->with('success', $message);
    }

    public function saveRegion(Request $request)
    {
        $sahodayaId = $this->school->parent_id;
        $academicYear = AcademicYear::forSchool($this->school);

        $regionIds = Region::forTenant($sahodayaId)->active()->globalOnly()->pluck('id')->all();
        $regionalGroups = $this->regionalPhaseGroups($sahodayaId);

        if (count($regionalGroups) >= 2) {
            return $this->saveRegionsByGroup($request, $sahodayaId, $academicYear, $regionIds, $regionalGroups);
        }

        $data = $request->validate([
            'region_id' => ['nullable', Rule::in($regionIds)],
        ]);

        if (empty($data['region_id'])) {
            SchoolRegionAssignment::forTenant($sahodayaId)
                ->forYear($academicYear)
                ->where('school_id', $this->school->id)
                ->forPartitionGroup(null)
                ->delete();

            return back()->with('success', 'Region cleared.');
        }

        $match = [
            'school_id'     => $this->school->id,
            'academic_year' => $academicYear,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('school_region_assignments', 'partition_group')) {
            $match['partition_group'] = null;
        }

        SchoolRegionAssignment::updateOrCreate(
            $match,
            [
                'tenant_id'           => $sahodayaId,
                'region_id'           => $data['region_id'],
                'source'              => 'school',
                'assigned_by_user_id' => $request->user()?->id,
            ],
        );

        // Route the school into its region's partition on every already-partitioned
        // fest hub, so Sahodaya admins don't have to re-click "Sync Partitions"
        // every time a school newly picks its region.
        app(\App\Services\Events\FestRegionPartitionService::class)
            ->syncSchoolAcrossHubs($sahodayaId, $this->school->id);

        return back()->with('success', 'Region saved.');
    }

    /**
     * Regional-phase-group variant of saveRegion() — one region choice per regional
     * phase group (§7.3 item 4), persisted as one SchoolRegionAssignment row per group,
     * keyed by its `partition_group`. Distinct from the legacy single-row (null group)
     * path above, which is left completely untouched for every Sahodaya that doesn't
     * have 2+ regional phase groups.
     *
     * @param  array<int, array{key: string, label: string}>  $regionalGroups
     */
    private function saveRegionsByGroup(
        Request $request,
        string $sahodayaId,
        string $academicYear,
        array $regionIds,
        array $regionalGroups,
    ) {
        $groupKeys = array_column($regionalGroups, 'key');

        $data = $request->validate([
            'regions'   => ['required', 'array'],
            'regions.*' => ['nullable', Rule::in($regionIds)],
        ]);

        $submitted = collect($data['regions'])->only($groupKeys);

        foreach ($groupKeys as $groupKey) {
            $regionId = $submitted->get($groupKey);

            if (empty($regionId)) {
                SchoolRegionAssignment::forTenant($sahodayaId)
                    ->forYear($academicYear)
                    ->where('school_id', $this->school->id)
                    ->forPartitionGroup($groupKey)
                    ->delete();

                continue;
            }

            SchoolRegionAssignment::updateOrCreate(
                ['school_id' => $this->school->id, 'academic_year' => $academicYear, 'partition_group' => $groupKey],
                [
                    'tenant_id'           => $sahodayaId,
                    'region_id'           => $regionId,
                    'source'              => 'school',
                    'assigned_by_user_id' => $request->user()?->id,
                ],
            );
        }

        // Deliberately no syncSchoolAcrossHubs() call here: that helper only understands
        // the legacy single Sahodaya-wide region (FestRegionPartitionService::schoolRegion()
        // with no group). Routing a school into each regional phase's own group-scoped
        // partition children is §7.5 Phase G (per-group syncPartitionsFromRegions()
        // variant), not yet built — these rows are ready for it once it lands.
        return back()->with('success', 'Regions saved.');
    }

    /**
     * Distinct regional phase groups configured across this Sahodaya's fest hubs — e.g.
     * [['key' => 'off_stage', 'label' => 'Off Stage'], ['key' => 'sargadhara', 'label' =>
     * 'Sargadhara']]. Empty for the common case of an event with zero or one regional
     * phase, in which case callers fall back to the legacy single Sahodaya-wide picker.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private function regionalPhaseGroups(string $sahodayaId): array
    {
        return FestEventPhase::query()
            ->whereNotNull('region_partition_group')
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $sahodayaId)->whereNull('parent_event_id'))
            ->orderBy('sort_order')
            ->get(['region_partition_group', 'name'])
            ->unique('region_partition_group')
            ->map(fn (FestEventPhase $phase) => [
                'key'   => $phase->region_partition_group,
                'label' => $phase->name,
            ])
            ->values()
            ->all();
    }

    public function students(EffectiveMasterDataResolver $resolver, Request $request)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        // Previously ->get() with no limit — a school with ~3000 active students
        // (see docs/SCALE_AND_PAGINATION_PLAN.md's corrected per-school scale note)
        // shipped its entire roster on every visit to this read-only snapshot page.
        // studentTotal is kept as its own count query (not derived from the page of
        // rows) since it also drives the "submit for review" button's disabled state.
        $studentTotal = Student::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->count();

        $search = trim((string) $request->query('search', ''));

        $students = Student::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(reg_no) LIKE ?', [$like]);
                });
            })
            ->with('schoolClass.classCategory')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Student $student) => [
                'id'           => $student->id,
                'name'         => $student->name,
                'reg_no'       => $student->reg_no,
                'gender'       => $student->gender,
                'dob'          => $student->dob?->format('Y-m-d'),
                'school_class' => $student->schoolClass ? [
                    'name'          => $student->schoolClass->name,
                    'class_category' => $student->schoolClass->classCategory ? [
                        'label' => $student->schoolClass->classCategory->label,
                    ] : null,
                ] : null,
            ]);

        return $this->inertia('School/Registration/Students', [
            'registration' => $registration,
            'submission'   => $submission,
            'profile'      => $this->registrationProfilePayload(),
            'students'     => $students,
            'studentTotal' => $studentTotal,
            'search'       => $search,
        ]);
    }

    public function storeStudent(Request $request)
    {
        return redirect("/school-admin/{$this->school->id}/students")
            ->with('info', 'Student records are managed under Records → Students. Return here to submit for Sahodaya review.');
    }

    public function destroyStudent(string $tenantId, int|string $student)
    {
        return redirect("/school-admin/{$this->school->id}/students")
            ->with('info', 'Student records are managed under Records → Students.');
    }

    public function counts(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        $classes = $this->schoolClasses();
        $existing = $submission->counts()->get()->keyBy('school_class_id');
        $dbStudentCount = Student::where('tenant_id', $this->school->id)->where('status', 'active')->count();
        $submittedTotal = (int) $existing->sum('total_count');

        return $this->inertia('School/Registration/Counts', [
            'registration' => $registration,
            'submission'   => $submission,
            'profile'      => $this->registrationProfilePayload(),
            'classes'      => $classes,
            'counts'       => $existing,
            'dbStudentCount' => $dbStudentCount,
            'countMismatch' => $dbStudentCount > 0 && $submittedTotal > 0
                && abs($dbStudentCount - $submittedTotal) / max($dbStudentCount, 1) > 0.1,
        ]);
    }

    public function saveCounts(Request $request, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        // Counts can also be edited after approval, so a school can report a mid-year
        // enrollment increase; this only updates the saved rows, it does not change
        // counts_status — the school must still explicitly resubmit for Sahodaya review.
        abort_unless(in_array($submission->counts_status, ['pending', 'rejected', 'approved']), 403, 'This action isn\'t available at the registration\'s current status.');

        $data = $request->validate([
            'counts' => 'required|array',
            'counts.*.school_class_id' => [
                'required',
                Rule::exists(SchoolClass::class, 'id')->where('tenant_id', $this->school->id),
            ],
            'counts.*.male_count'        => 'required|integer|min:0',
            'counts.*.female_count'      => 'required|integer|min:0',
            'counts.*.total_count'       => 'required|integer|min:0',
        ]);

        $classCategoryIds = SchoolClass::whereIn('id', collect($data['counts'])->pluck('school_class_id'))
            ->pluck('class_category_id', 'id');

        foreach ($data['counts'] as $row) {
            SchoolYearStudentCount::updateOrCreate(
                ['school_year_submission_id' => $submission->id, 'school_class_id' => $row['school_class_id']],
                array_merge($row, [
                    // kept in sync for backward-compatible category-level reporting
                    'class_category_id' => $classCategoryIds[$row['school_class_id']] ?? null,
                ])
            );
        }

        app(DataChangeLogger::class)->event(
            'updated',
            'Annual registration student counts saved',
            $this->school->id,
            'membership',
            $submission,
            ['counts_rows' => count($data['counts'])],
        );

        return back()->with('success', 'Counts saved.');
    }

    public function teachers(EffectiveMasterDataResolver $resolver)
    {
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        return $this->inertia('School/Registration/Teachers', [
            'registration'  => $registration,
            'submission'    => $submission,
            'profile'       => $this->registrationProfilePayload(),
            'teachers'      => $submission->teachers()->with('teachingType')->get()->map(function (SubmissionTeacher $teacher) {
                return array_merge($teacher->toArray(), [
                    'subject_labels' => $this->subjectLabelsFor($teacher->subject_ids ?? []),
                ]);
            }),
            'teachingTypes' => $resolver->teachingTypes($this->school->parent_id),
            'subjects'      => $resolver->subjects($this->school->parent_id),
        ]);
    }

    public function storeTeacher(Request $request, EffectiveMasterDataResolver $resolver, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        abort_unless(in_array($submission->teacher_status, ['pending', 'rejected']), 403, 'Teacher registration can only be edited while pending or rejected.');

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'subject_ids'      => 'nullable|array',
            'subject_ids.*'    => 'integer',
            'teaching_type_id' => ['nullable', Rule::exists((new \App\Models\TeachingType)->getConnectionName().'.teaching_types', 'id')],
        ]);

        $submission->teachers()->create($this->teacherPayloadFrom($data));

        return back()->with('success', 'Teacher added.');
    }

    public function bulkStoreTeachers(Request $request, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        $submission = $registration->submission;
        abort_unless(in_array($submission->teacher_status, ['pending', 'rejected']), 403, 'Teacher registration can only be edited while pending or rejected.');

        $data = $request->validate([
            'teachers'                    => 'required|array|min:1|max:50',
            'teachers.*.name'             => 'required|string|max:255',
            'teachers.*.subject_ids'      => 'nullable|array',
            'teachers.*.subject_ids.*'    => 'integer',
            'teachers.*.teaching_type_id' => ['nullable', Rule::exists((new \App\Models\TeachingType)->getConnectionName().'.teaching_types', 'id')],
        ]);

        $added = 0;
        foreach ($data['teachers'] as $row) {
            if (blank($row['name'] ?? null)) {
                continue;
            }
            $submission->teachers()->create($this->teacherPayloadFrom($row));
            $added++;
        }

        return back()->with('success', "{$added} teacher(s) added.");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function teacherPayloadFrom(array $data): array
    {
        $subjectIds = array_values(array_filter($data['subject_ids'] ?? [], fn ($id) => filled($id)));
        $labels = $this->subjectLabelsFor($subjectIds);

        return [
            'name'             => $data['name'],
            'subject_ids'      => $subjectIds ?: null,
            'subject'          => $labels !== [] ? implode(', ', $labels) : null,
            'teaching_type_id' => $data['teaching_type_id'] ?? null,
        ];
    }

    /**
     * @param  array<int, int>  $subjectIds
     * @return array<int, string>
     */
    private function subjectLabelsFor(array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        return \App\Models\Subject::whereIn('id', $subjectIds)
            ->forSahodaya($this->school->parent_id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label')
            ->all();
    }

    public function destroyTeacher(string $tenantId, SubmissionTeacher $teacher, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        abort_if($teacher->school_year_submission_id !== $registration->submission->id, 403);
        $teacher->delete();

        return back()->with('success', 'Teacher removed.');
    }

    public function submitTrack(Request $request, SchoolYearSubmissionReviewService $reviewService, MembershipRegistrationWindowService $windowService)
    {
        $this->assertRegistrationEditAllowed($windowService);
        $registration = $this->currentRegistration();
        $submission = $registration->submission;

        $data = $request->validate(['track' => 'required|in:full_records,counts,teachers']);

        $reviewService->submitTrack($submission, $this->school, $data['track']);

        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->firstOrFail();
        $submission->refresh();

        if ($submission->allApplicableTracksApproved($profile)) {
            app(RegistrationStatusService::class)->checkAndAdvanceToPayment($registration->fresh());
        } elseif ($registration->registration_status === 'data_rejected') {
            $registration->update(['registration_status' => 'data_pending']);
        }

        return back()->with('success', 'Submitted for Sahodaya review. You will be notified when approved.');
    }

    public function payment()
    {
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', AcademicYear::forSchool($this->school))
            ->with('submission')
            ->first();

        if (! $registration) {
            return redirect("/school-admin/{$this->school->id}/registration")
                ->with('info', 'Start annual registration before uploading a membership payment.');
        }

        $registration = app(RegistrationStatusService::class)
            ->ensureMembershipFee($registration);
        if (! in_array($registration->registration_status, ['payment_pending', 'payment_rejected'], true)) {
            return redirect("/school-admin/{$this->school->id}/payments")
                ->with('info', 'Membership payment is not pending. View the latest payment status and receipts here.');
        }

        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();
        $slab = \App\Models\MembershipFeeSlab::where('sahodaya_id', $this->school->parent_id)
            ->where('academic_year', $registration->academic_year)
            ->orderByDesc('min_students')
            ->first();
        $isOverdue = $slab?->due_date && now()->startOfDay()->gt($slab->due_date);
        $lateFee = ($isOverdue && $slab?->late_fee_amount) ? (float) $slab->late_fee_amount : 0;

        return $this->inertia('School/Registration/Payment', [
            'registration' => $registration,
            'profile'      => $profile ? array_merge($profile->toArray(), [
                'payment_details_text' => $profile->paymentDetailsText(),
            ]) : null,
            'payments'     => MembershipPayment::where('school_id', $this->school->id)
                ->where('academic_year', $registration->academic_year)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('created_at')
                ->get()
                ->each(fn ($payment) => $payment->setRelation('school', $this->school)),
            'paymentDueDate' => $slab?->due_date?->format('Y-m-d'),
            'paymentOverdue' => (bool) $isOverdue,
            'lateFeeAmount'  => $lateFee,
            'amountPaid'     => (float) ($registration->amount_paid ?? 0),
            'totalDue'       => $registration->outstandingBalance() + $lateFee,
        ]);
    }

    public function uploadPayment(Request $request, MembershipNotifier $notifier)
    {
        $registration = app(RegistrationStatusService::class)
            ->ensureMembershipFee($this->currentRegistration());
        abort_unless(in_array($registration->registration_status, ['payment_pending', 'payment_rejected']), 403, 'Payment can only be resubmitted while pending or rejected.');

        if ($registration->membership_fee_amount === null) {
            return back()->with('error', 'Membership fee is not configured yet. Please contact your Sahodaya office.');
        }

        $outstanding = $registration->outstandingBalance();
        if ($outstanding <= 0) {
            return back()->with('info', 'No payment is currently due.');
        }

        // payment_proof accepts up to 5 images for ONE payment — see
        // docs/FLOW_GAP_FIX_PLAN.md multi-image upload feature. The first file remains
        // the primary payment_proof_path/backup exactly as before; extras are attached to
        // the FeeReceipt created below.
        $data = $request->validate([
            'payment_method'   => 'nullable|string|max:50',
            'transaction_ref'  => 'nullable|string|max:100',
            'payment_proof'    => 'required|array|min:1|max:'.\App\Services\Fees\FeeReceiptAttachmentService::MAX_FILES,
            'payment_proof.*'  => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $proofFiles = $request->file('payment_proof');
        $file = $proofFiles[0];
        $backup = app(UploadBackupService::class)->store(
            $file,
            'payment_proof',
            $this->school->id,
            null,
            $request->user()->id,
            ['academic_year' => $registration->academic_year],
        );

        $path = TenantStorage::storeUploadedFile($file, "payments/{$this->school->id}");

        $superseded = MembershipPayment::where('school_id', $this->school->id)
            ->where('academic_year', $registration->academic_year)
            ->whereIn('status', ['submitted', 'rejected'])
            ->get();

        $payment = MembershipPayment::create([
            'school_id'           => $this->school->id,
            'academic_year'       => $registration->academic_year,
            'registration_id'     => $registration->id,
            'amount'              => $outstanding,
            'payment_proof_path'  => $path,
            'payment_method'      => $data['payment_method'] ?? null,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'uploaded_by_user_id' => $request->user()->id,
            'status'              => 'submitted',
        ]);

        foreach ($superseded as $old) {
            $old->update([
                'status' => 'superseded',
                'superseded_by_payment_id' => $payment->id,
            ]);
        }

        $receipt = app(FeeReceiptService::class)->createForMembershipPayment($payment);

        if (count($proofFiles) > 1) {
            app(\App\Services\Fees\FeeReceiptAttachmentService::class)->attachExtra(
                $receipt,
                array_slice($proofFiles, 1),
                "payments/{$this->school->id}",
            );
        }

        $backup->update([
            'related_type' => $payment->getMorphClass(),
            'related_id'   => $payment->id,
        ]);

        $regBefore = $registration->registration_status;
        $registration->update(['registration_status' => 'payment_submitted']);

        app(DataChangeLogger::class)->created(
            $payment,
            'Membership payment proof submitted',
            $this->school->id,
            'membership',
            [
                'amount'          => $payment->amount,
                'transaction_ref' => $payment->transaction_ref,
                'backup_id'       => $backup->id,
            ],
        );

        app(DataChangeLogger::class)->updated(
            $registration,
            'Registration moved to payment submitted',
            ['registration_status' => ['old' => $regBefore, 'new' => 'payment_submitted']],
            $this->school->id,
            'membership',
        );
        $notifier->paymentSubmitted(
            $this->school,
            $registration->academic_year,
            $outstanding,
            $data['transaction_ref'] ?? null,
            $data['payment_method'] ?? null,
        );

        return redirect("/school-admin/{$this->school->id}/registration")
            ->with('success', 'Payment proof submitted. Sahodaya will verify your payment.');
    }

    public function paymentProof(string $tenantId, MembershipPayment $payment, MembershipPaymentProofService $proofs)
    {
        abort_unless($payment->school_id === $this->school->id, 403);

        return $proofs->download($payment);
    }

    public function showSubmissionStudentImage(string $tenantId, SubmissionStudent $student)
    {
        $registration = $this->currentRegistration();
        abort_if($student->school_year_submission_id !== $registration->submission->id, 403);
        abort_unless($student->image_path, 404);

        return TenantStorage::downloadResponse($this->school, $student->image_path);
    }

    private function submissionStudentPayload(SubmissionStudent $student): array
    {
        $data = $student->toArray();
        // Same off-by-one as Student::dob (see StudentController::studentPayload): a `date`-cast
        // attribute serialized via toArray()/toJSON() round-trips through Carbon's UTC conversion,
        // rolling local midnight back a day in the +5:30 app timezone. Override explicitly.
        $data['dob'] = $student->dob?->format('Y-m-d');
        $data['image_url'] = $student->imageUrl($this->school->id);

        return $data;
    }

    private function currentRegistration(): Registration
    {
        $registration = Registration::where('school_id', $this->school->id)
            ->where('academic_year', AcademicYear::forSchool($this->school))
            ->with('submission')
            ->firstOrFail();

        return $registration;
    }

    private function registrationProfilePayload(): ?array
    {
        $profile = SahodayaProfile::where('tenant_id', $this->school->parent_id)->first();
        if (! $profile) {
            return null;
        }

        return array_merge($profile->toArray(), [
            'payment_details_text' => $profile->paymentDetailsText(),
        ]);
    }

    private function assertRegistrationEditAllowed(MembershipRegistrationWindowService $windowService): void
    {
        $window = $windowService->forSchool($this->school, AcademicYear::forSchool($this->school));
        if ($reason = $windowService->editBlockReason($window)) {
            abort(403, $reason);
        }
    }
}
