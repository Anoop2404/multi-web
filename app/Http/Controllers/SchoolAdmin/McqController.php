<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\Student;
use App\Services\Mcq\McqAttendanceCorrectionService;
use App\Services\Mcq\McqEligibilityService;
use App\Services\Mcq\McqRegistrationGateService;
use App\Services\Mcq\McqReportService;
use App\Services\Mcq\McqSchoolFeeService;
use App\Services\School\SchoolDocumentDownloadGateService;
use App\Services\Students\StudentVerificationGate;
use App\Support\Mcq\McqExamEligibilityConfig;
use App\Support\Mcq\McqExamLevelLabels;
use App\Support\Mcq\McqRegistrationStatusPresenter;
use App\Support\Mcq\McqResultPresenter;
use App\Models\Tenant;
use Illuminate\Http\Request;

class McqController extends SchoolAdminController
{
    public function hub(McqRegistrationController $registrations)
    {
        $sahodayaId = $this->school->parent_id;
        $exams = McqExam::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('scheduled_at')
            ->get();

        $registeredCount = McqRegistration::where('school_id', $this->school->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->count();

        $hubStats = [
            'available'   => $exams->whereIn('status', ['published', 'ongoing'])->count(),
            'registered'  => $exams->filter(fn (McqExam $e) => McqRegistration::where('exam_id', $e->id)->where('school_id', $this->school->id)->exists())->count(),
            'completed'   => $exams->where('status', 'completed')->where('results_published', true)->count(),
            'total_regs'  => $registeredCount,
        ];

        return $registrations->index($hubStats);
    }

    public function exam(string $tenantId, McqExam $exam, string $tab = 'register')
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $gate = app(McqRegistrationGateService::class)->schoolGatePayload($this->school);
        $downloadGate = app(SchoolDocumentDownloadGateService::class)->payload($this->school, null, $exam);

        $exam->load(['series:id,title', 'parentExam:id,title']);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->active()
            ->with(['student.user:id,username', 'student.schoolClass:id,name', 'teacher:id,name,employee_code,reg_no', 'mark', 'feeReceipt'])
            ->orderBy('hall_ticket_no')
            ->get()
            ->map(function (McqRegistration $reg) use ($exam) {
                $row = $reg->toArray();
                $row['student'] = $reg->student
                    ? array_merge($reg->student->only('id', 'name', 'reg_no'), ['class_name' => $reg->student->schoolClass?->name])
                    : null;
                $row['teacher'] = $reg->teacher
                    ? $reg->teacher->only('id', 'name', 'employee_code', 'reg_no')
                    : null;
                $row['participant_name'] = $reg->participantName();
                $row['is_teacher'] = $reg->isTeacherRegistration();
                $row['portal_username'] = $reg->student?->user?->username;
                $row['approval_status_label'] = $reg->approvalStatusLabel();
                $row['lifecycle_status'] = McqRegistrationStatusPresenter::forRegistration($reg, $exam);
                $row['can_cancel'] = $reg->canBeCancelledBySchool();
                if ($exam->results_published && $reg->mark) {
                    $row['mark'] = McqResultPresenter::forRegistration($reg, $reg->mark);
                }

                return $row;
            });

        $allowsStudents = McqExamEligibilityConfig::allowsStudents($exam->eligibility_config);
        $allowsTeachers = McqExamEligibilityConfig::allowsTeachers($exam->eligibility_config);

        $cancelledStudentIds = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'cancelled')
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->filter()
            ->flip();

        $cancelledTeacherIds = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'cancelled')
            ->whereNotNull('teacher_id')
            ->pluck('teacher_id')
            ->filter()
            ->flip();

        $cancellableStudentIds = $registrations
            ->filter(fn ($r) => ($r['can_cancel'] ?? false) && ! empty($r['student_id']))
            ->pluck('student_id')
            ->filter()
            ->flip();

        $cancellableTeacherIds = $registrations
            ->filter(fn ($r) => ($r['can_cancel'] ?? false) && ! empty($r['teacher_id']))
            ->pluck('teacher_id')
            ->filter()
            ->flip();

        $schoolFee = McqSchoolFee::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->with('feeReceipt')
            ->first();

        $feeService = app(McqSchoolFeeService::class);
        $feeBreakdown = $feeService->breakdownForSchool($exam, $this->school);

        $eligibilityService = app(McqEligibilityService::class);

        $students = collect();
        $classOptions = collect();
        $registeredIds = $registrations->pluck('student_id')->filter()->all();

        // A school may have anywhere from a few dozen to ~3000 active students. Loading
        // and per-student-annotating the whole roster on every page view of every exam
        // does not scale — mirrors the fix already applied to FestRegistrationController
        // (see docs/SCALE_AND_PAGINATION_PLAN.md §6/§9-new). Above the threshold, skip the
        // eager load: ship an empty list and let the frontend fetch a bounded, searched
        // batch on demand via eligibleStudents() below.
        $studentTotalCount = Student::where('tenant_id', $this->school->id)->active()->count();
        $lazyThreshold = (int) config('erp.fest_registration_lazy_student_threshold', 300);
        $lazyStudents = $allowsStudents && $studentTotalCount > $lazyThreshold;

        if ($allowsStudents && ! $lazyStudents) {
            $studentQuery = Student::where('tenant_id', $this->school->id)
                ->active()
                ->with('schoolClass:id,name,class_category_id')
                ->orderBy('name');

            $eligibleClassIds = $eligibilityService->eligibleSchoolClassIds($exam, $this->school->id);
            if ($eligibleClassIds !== null) {
                if ($eligibleClassIds === []) {
                    $studentQuery->whereRaw('1 = 0');
                } else {
                    $studentQuery->whereIn('school_class_id', $eligibleClassIds);
                }
            }

            if ((int) ($exam->exam_level ?? 1) > 1
                && $exam->promotion_locked
                && ! empty($exam->promoted_student_ids)) {
                $studentQuery->whereIn('id', $exam->promoted_student_ids);
            }

            $allStudents = $studentQuery->get(['id', 'name', 'reg_no', 'school_class_id', 'gender', 'user_id', 'verified_at']);
            $registeredIdSet = array_flip($registeredIds);

            $students = $allStudents->map(function (Student $s) use ($exam, $registeredIdSet, $eligibilityService, $cancelledStudentIds, $cancellableStudentIds) {
                $eligible = $eligibilityService->isEligible($exam, $s);
                $registered = isset($registeredIdSet[$s->id]);

                return [
                    'id'                   => $s->id,
                    'name'                 => $s->name,
                    'reg_no'               => $s->reg_no,
                    'gender'               => $s->gender,
                    'class_name'           => $s->schoolClass?->name,
                    'school_class_id'      => $s->school_class_id,
                    'has_portal_login'     => (bool) $s->user_id,
                    'registered'           => $registered,
                    'previously_cancelled' => ! $registered && $cancelledStudentIds->has($s->id),
                    'can_cancel'           => $registered && $cancellableStudentIds->has($s->id),
                    'eligible'             => $eligible,
                    'ineligible_reason'    => $eligible ? null : $eligibilityService->ineligibilityReason($exam, $s),
                ];
            })->values();

            $classOptions = $this->schoolClasses()->map(function ($class) use ($students) {
                $eligibleInClass = $students
                    ->where('school_class_id', $class->id)
                    ->where('eligible', true)
                    ->where('registered', false);

                return [
                    'id'             => $class->id,
                    'name'           => $class->name,
                    'eligible_count' => $eligibleInClass->count(),
                ];
            })->values();
        } elseif ($allowsStudents) {
            // Lazy mode: class list without per-class eligible counts (would require the
            // same full-roster scan we're avoiding). Frontend shows counts as "—" until a
            // search has been run.
            $classOptions = $this->schoolClasses()->map(fn ($class) => [
                'id'             => $class->id,
                'name'           => $class->name,
                'eligible_count' => null,
            ])->values();
        }

        $teachers = collect();
        $registeredTeacherIds = $registrations->pluck('teacher_id')->filter()->all();
        if ($allowsTeachers) {
            $registeredTeacherSet = array_flip($registeredTeacherIds);
            $allTeachers = \App\Models\Teacher::where('tenant_id', $this->school->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'reg_no', 'teaching_type_id', 'designation_id', 'subject_ids', 'experience_years', 'verified_at', 'user_id']);

            $teachers = $allTeachers->map(function ($t) use ($exam, $registeredTeacherSet, $eligibilityService, $cancelledTeacherIds, $cancellableTeacherIds) {
                $eligible = $eligibilityService->isTeacherEligible($exam, $t);
                $registered = isset($registeredTeacherSet[$t->id]);

                return [
                    'id'                   => $t->id,
                    'name'                 => $t->name,
                    'employee_code'        => $t->employee_code,
                    'reg_no'               => $t->reg_no,
                    'registered'           => $registered,
                    'previously_cancelled' => ! $registered && $cancelledTeacherIds->has($t->id),
                    'can_cancel'           => $registered && $cancellableTeacherIds->has($t->id),
                    'eligible'             => $eligible,
                    'ineligible_reason'    => $eligible ? null : $eligibilityService->teacherIneligibilityReason($exam, $t),
                ];
            })->values();
        }

        $ticketsIssuedCount = $registrations->filter(fn ($r) => ! empty($r['hall_ticket_no']))->count();
        $canRegister = ! $gate['blocked'] && in_array($exam->status, ['published', 'ongoing'], true);

        $reportService = app(McqReportService::class);
        $reportRows = in_array($tab, ['reports'], true) ? $reportService->registrationRows($exam, $this->school->id) : [];
        $toppers = in_array($tab, ['toppers', 'results'], true) && $exam->results_published
            ? $reportService->schoolToppers($exam, $this->school->id)
            : [];

        // Attendance can only be marked once hall tickets have been issued (fee approved).
        $attendanceRegistrations = $registrations->filter(fn ($r) => ! empty($r['hall_ticket_no']));
        $pendingCorrectionsByReg = $tab === 'attendance'
            ? \App\Models\McqAttendanceCorrectionRequest::where('exam_id', $exam->id)
                ->where('status', 'pending')
                ->whereIn('registration_id', $attendanceRegistrations->pluck('id'))
                ->get()
                ->keyBy('registration_id')
            : collect();
        $attendanceRows = $tab === 'attendance'
            ? $attendanceRegistrations->map(fn ($r) => [
                'id'                       => $r['id'],
                'hall_ticket_no'           => $r['hall_ticket_no'] ?? null,
                'student'                  => $r['student'] ?? null,
                'teacher'                  => $r['teacher'] ?? null,
                'participant_name'         => $r['participant_name'] ?? ($r['student']['name'] ?? null),
                'class_name'               => $r['student']['class_name'] ?? null,
                'attendance_status'        => $r['attendance_status'] ?? 'pending',
                'pending_correction_status'=> $pendingCorrectionsByReg->get($r['id'])?->requested_status,
            ])->values()
            : [];
        $attendanceGate = [
            'can_mark'      => $attendanceRegistrations->isNotEmpty(),
            'issued_count'  => $attendanceRegistrations->count(),
            'present'       => $attendanceRegistrations->where('attendance_status', 'present')->count(),
            'absent'        => $attendanceRegistrations->where('attendance_status', 'absent')->count(),
            'pending'       => $attendanceRegistrations->filter(fn ($r) => empty($r['attendance_status']) || $r['attendance_status'] === 'pending')->count(),
        ];

        return $this->inertia('School/Mcq/ExamDetail', [
            'exam' => array_merge($exam->toArray(), [
                'fee_label'              => McqExamLevelLabels::feeLabel($exam->fee_type, $exam->fee_amount),
                'student_fee_label'      => McqExamLevelLabels::rupeeLabel($exam->fee_amount),
                'school_discount_label'  => McqExamLevelLabels::rupeeLabel($exam->schoolDiscountAmount()),
                'payable_per_student_label' => McqExamLevelLabels::rupeeLabel($exam->schoolPayablePerStudent()),
                'scheduled_at_label'     => $exam->scheduled_at?->format('j M Y, g:i A'),
                'has_fee'                => $exam->hasFee(),
                'level_label'            => McqExamLevelLabels::levelLabel((int) ($exam->exam_level ?? 1)),
                'exam_type_label'        => McqExamLevelLabels::examTypeLabel($exam->exam_type),
                'eligibility_summary'    => McqExamEligibilityConfig::summaryLabel($exam->eligibility_config, $exam->tenant_id),
                'delivery_mode_label'    => $exam->isOnlineDelivery() ? 'Online' : 'Offline',
                'registration_open'      => $exam->isRegistrationOpen(),
                'code'                   => $exam->code,
                'registration_opens_at'  => $exam->registration_opens_at?->toIso8601String(),
                'registration_closes_at' => $exam->registration_closes_at?->toIso8601String(),
                'result_date'            => $exam->result_date?->toDateString(),
                'status_label'           => McqExamLevelLabels::statusLabel($exam->status),
                'series_title'           => $exam->series?->title,
                'parent_exam_title'      => $exam->parentExam?->title,
                'require_verified_students' => app(StudentVerificationGate::class)->requiredForMcq($exam),
                'allows_students'           => $allowsStudents,
                'allows_teachers'           => $allowsTeachers,
            ]),
            'tab'                    => $tab,
            'registrations'          => $registrations,
            'schoolFee'              => $schoolFee,
            'feeBreakdown'           => $feeBreakdown,
            'students'               => $students,
            'teachers'               => $teachers,
            'classOptions'           => $classOptions,
            'registeredStudentIds'   => $registeredIds,
            'registeredTeacherIds'   => $registeredTeacherIds,
            'ticketsIssuedCount'     => $ticketsIssuedCount,
            'lazyLoadStudents'       => $lazyStudents,
            'studentCount'           => $studentTotalCount,
            'registerStats'          => [
                // When lazy, the eligible/available counts can't be computed without the
                // full-roster scan we're specifically avoiding — send null and let the
                // frontend show "—" instead of a misleading 0.
                'eligible'    => $lazyStudents
                    ? null
                    : $students->where('eligible', true)->count() + $teachers->where('eligible', true)->count(),
                'available'   => $lazyStudents
                    ? null
                    : $students->where('eligible', true)->where('registered', false)->count()
                        + $teachers->where('eligible', true)->where('registered', false)->count(),
                'registered'  => $registrations->count(),
                'batch_due'   => (float) ($schoolFee?->total_due ?? 0),
                'can_register'=> $canRegister,
                'allows_students' => $allowsStudents,
                'allows_teachers' => $allowsTeachers,
            ],
            'registrationGate'       => $gate,
            'downloadGate'           => $downloadGate,
            'mcqCoordinators'        => \App\Models\User::role('school_mcq_coordinator')
                ->where('tenant_id', $this->school->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'portalLoginUrl'         => url('/portal/login'),
            'credentialsExportUrl'   => "/school-admin/{$this->school->id}/mcq/{$exam->id}/credentials/export",
            'reportRows'             => $reportRows,
            'toppers'                => $toppers,
            'attendanceRows'         => $attendanceRows,
            'attendanceGate'         => $attendanceGate,
            'reportExports'          => [
                'registration' => "/school-admin/{$this->school->id}/mcq/{$exam->id}/reports/registration/export",
                'attendance'   => "/school-admin/{$this->school->id}/mcq/{$exam->id}/reports/attendance/export",
                'toppers'      => "/school-admin/{$this->school->id}/mcq/{$exam->id}/reports/toppers/export",
            ],
        ]);
    }

    /**
     * Bounded, searchable student lookup for the exam's register tab — the counterpart
     * to FestRegistrationController::eligibleStudents() for large rosters. Only reached
     * when exam() reports lazyLoadStudents = true; capped at 150 regardless of search
     * term so a large school opening the picker with no search text yet can't trigger
     * an unbounded scan. See docs/SCALE_AND_PAGINATION_PLAN.md §6/§9-new.
     */
    public function eligibleStudents(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $search = $request->query('search');
        $classId = $request->query('class_id');

        $eligibilityService = app(McqEligibilityService::class);

        $studentQuery = Student::where('tenant_id', $this->school->id)
            ->active()
            ->with('schoolClass:id,name,class_category_id')
            ->orderBy('name');

        $eligibleClassIds = $eligibilityService->eligibleSchoolClassIds($exam, $this->school->id);
        if ($eligibleClassIds !== null) {
            if ($eligibleClassIds === []) {
                $studentQuery->whereRaw('1 = 0');
            } else {
                $studentQuery->whereIn('school_class_id', $eligibleClassIds);
            }
        }

        if ((int) ($exam->exam_level ?? 1) > 1
            && $exam->promotion_locked
            && ! empty($exam->promoted_student_ids)) {
            $studentQuery->whereIn('id', $exam->promoted_student_ids);
        }

        if ($classId) {
            $studentQuery->where('school_class_id', $classId);
        }

        if (filled($search)) {
            $term = strtolower(trim((string) $search));
            $studentQuery->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw('LOWER(reg_no) LIKE ?', ["%{$term}%"]);
            });
        }

        $matches = $studentQuery->limit(150)->get(['id', 'name', 'reg_no', 'school_class_id', 'gender', 'user_id', 'verified_at']);

        $registeredIdSet = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->active()
            ->whereIn('student_id', $matches->pluck('id'))
            ->pluck('student_id')
            ->filter()
            ->flip();

        $cancelledStudentIds = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->where('status', 'cancelled')
            ->whereIn('student_id', $matches->pluck('id'))
            ->pluck('student_id')
            ->filter()
            ->flip();

        $cancellableStudentIds = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->whereIn('student_id', $matches->pluck('id'))
            ->get()
            ->filter(fn (McqRegistration $r) => $r->canBeCancelledBySchool())
            ->pluck('student_id')
            ->filter()
            ->flip();

        $students = $matches->map(function (Student $s) use ($exam, $registeredIdSet, $eligibilityService, $cancelledStudentIds, $cancellableStudentIds) {
            $eligible = $eligibilityService->isEligible($exam, $s);
            $registered = $registeredIdSet->has($s->id);

            return [
                'id'                   => $s->id,
                'name'                 => $s->name,
                'reg_no'               => $s->reg_no,
                'gender'               => $s->gender,
                'class_name'           => $s->schoolClass?->name,
                'school_class_id'      => $s->school_class_id,
                'has_portal_login'     => (bool) $s->user_id,
                'registered'           => $registered,
                'previously_cancelled' => ! $registered && $cancelledStudentIds->has($s->id),
                'can_cancel'           => $registered && $cancellableStudentIds->has($s->id),
                'eligible'             => $eligible,
                'ineligible_reason'    => $eligible ? null : $eligibilityService->ineligibilityReason($exam, $s),
            ];
        })->values();

        return response()->json(['students' => $students]);
    }

    public function register(Request $request, string $tenantId, McqExam $exam)
    {
        $request->merge(['exam_id' => $exam->id]);

        return app(McqRegistrationController::class)->store($request);
    }

    public function bulkRegister(Request $request, string $tenantId, McqExam $exam)
    {
        $request->merge(['exam_id' => $exam->id]);

        return app(McqRegistrationController::class)->bulkStore($request);
    }

    public function registerTeacher(Request $request, string $tenantId, McqExam $exam)
    {
        $request->merge(['exam_id' => $exam->id]);

        return app(McqRegistrationController::class)->storeTeacher($request);
    }

    public function bulkRegisterTeachers(Request $request, string $tenantId, McqExam $exam)
    {
        $request->merge(['exam_id' => $exam->id]);

        return app(McqRegistrationController::class)->bulkStoreTeachers($request);
    }

    public function cancelRegistration(Request $request, string $tenantId, McqExam $exam)
    {
        return app(McqRegistrationController::class)->cancel($request, $tenantId, $exam);
    }

    public function uploadFee(Request $request, string $tenantId, McqExam $exam)
    {
        return app(McqRegistrationController::class)->uploadSchoolPayment($request, $tenantId, $exam);
    }

    public function storeAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $data = $request->validate([
            'attendance'                       => 'required|array|min:1',
            'attendance.*.registration_id'     => 'required|integer',
            'attendance.*.attendance_status'   => 'required|in:present,absent,malpractice,withheld,pending',
            'attendance.*.attendance_note'     => 'nullable|string|max:1000',
        ]);

        $registrations = McqRegistration::with('exam')
            ->where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->whereNotNull('hall_ticket_no')
            ->get()
            ->keyBy('id');

        $correctionService = app(McqAttendanceCorrectionService::class);
        $marked = 0;
        $queued = 0;
        foreach ($data['attendance'] as $row) {
            $registration = $registrations->get((int) $row['registration_id']);
            if (! $registration) {
                continue;
            }

            $status = $row['attendance_status'];
            if ($status === (string) ($registration->attendance_status ?? 'pending')) {
                continue;
            }

            if ($correctionService->requiresApproval($registration)) {
                $correctionService->submit(
                    $registration,
                    $status,
                    $status === 'pending' ? null : ($row['attendance_note'] ?? null),
                    $request->user(),
                    'school_admin',
                );
                $queued++;

                continue;
            }

            $registration->update([
                'attendance_status'    => $status === 'pending' ? null : $status,
                'attendance_note'      => $status === 'pending' ? null : ($row['attendance_note'] ?? null),
                'attendance_marked_at' => $status === 'pending' ? null : now(),
                'attendance_marked_by' => $status === 'pending' ? null : $request->user()->id,
            ]);

            // Clear any submitted marks if a student is now flagged absent/malpractice/withheld.
            if ($registration->blocksScoring() && $registration->mark) {
                $registration->mark()->delete();
                $registration->update(['status' => 'registered', 'submitted_at' => null]);
            }

            $marked++;
        }

        $message = "Attendance saved for {$marked} student(s).";
        if ($queued > 0) {
            $message .= " {$queued} change(s) already marked sent to the Sahodaya for approval.";
        }

        return back()->with('success', $message);
    }

    public function hallTicketsPdf(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        app(SchoolDocumentDownloadGateService::class)->assertMcqExamFeeForDownloads($exam, $this->school);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->where('school_id', $this->school->id)
            ->whereNotNull('hall_ticket_no')
            ->with(['student'])
            ->orderBy('hall_ticket_no')
            ->get();

        return view('mcq.hall-tickets-bulk', compact('exam', 'registrations'));
    }

    public function registrationInvoice(string $tenantId, McqExam $exam, McqRegistration $registration, \App\Services\Mcq\McqRegistrationInvoiceService $invoices)
    {
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);
        abort_if($registration->exam_id !== $exam->id, 404);
        abort_if($registration->school_id !== $this->school->id, 403);

        return $invoices->download($registration, Tenant::find($this->school->parent_id));
    }
}
