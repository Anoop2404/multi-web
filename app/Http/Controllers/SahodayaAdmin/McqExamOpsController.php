<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\User;
use App\Services\Mcq\McqHallTicketService;
use App\Services\Mcq\McqSeatingService;
use App\Support\Mcq\McqExamPayload;
use App\Support\Mcq\McqHallTicketDesign;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class McqExamOpsController extends SahodayaAdminController
{
    public function attendance(Request $request, string $tenantId, McqExam $exam, \App\Services\Mcq\McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search'    => 'nullable|string|max:100',
            'school_id' => 'nullable|string|max:36',
            'class'     => 'nullable|string|max:60',
        ]);

        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['student', 'school', 'mark']);

        $this->applyRegistrationFilters($query, $filters);

        $registrations = $query->orderBy('hall_ticket_no')
            ->paginate(50)
            ->withQueryString();

        // Active = excludes cancelled registrations, matching the Reports page's "Registrations" stat.
        $base = McqRegistration::where('exam_id', $exam->id)->active();
        $summary = [
            'total'         => (clone $base)->count(),
            'pending'       => (clone $base)->where(fn ($q) => $q->whereNull('attendance_status')->orWhere('attendance_status', 'pending'))->count(),
            'present'       => (clone $base)->where('attendance_status', 'present')->count(),
            'absent'        => (clone $base)->where('attendance_status', 'absent')->count(),
            'malpractice'   => (clone $base)->where('attendance_status', 'malpractice')->count(),
            'withheld'      => (clone $base)->where('attendance_status', 'withheld')->count(),
            'marks_entered' => (clone $base)->whereHas('mark')->count(),
            'not_marked'    => (clone $base)->where('attendance_status', 'present')->whereDoesntHave('mark')->count(),
        ];

        $pendingCorrectionsCount = \App\Models\McqAttendanceCorrectionRequest::where('exam_id', $exam->id)
            ->where('status', 'pending')
            ->count();

        return $this->inertia('Sahodaya/Mcq/Attendance', [
            'exam'                    => $exam,
            'registrations'           => $registrations,
            'summary'                 => $summary,
            'pendingCorrectionsCount' => $pendingCorrectionsCount,
            'filters'                 => $filters,
            'schoolOptions'           => $reports->schoolFilterOptions($exam),
            'classOptions'            => $reports->classFilterOptions($exam),
        ]);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<McqRegistration>  $query */
    private function applyRegistrationFilters($query, array $filters): void
    {
        if (! empty($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }

        if (! empty($filters['class'])) {
            $class = mb_strtolower(trim((string) $filters['class']));
            $query->whereHas('student.schoolClass', fn ($q) => $q->whereRaw('LOWER(name) = ?', [$class]));
        }

        if (! empty($filters['search'])) {
            $rawTerm = trim((string) $filters['search']);
            $term = '%'.mb_strtolower($rawTerm).'%';

            $matchingSchoolIds = \App\Models\Tenant::where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->whereRaw('LOWER(name) LIKE ?', [$term])
                ->pluck('id')
                ->all();

            $query->where(function ($q) use ($term, $matchingSchoolIds) {
                $q->whereRaw('LOWER(hall_ticket_no) LIKE ?', [$term])
                    ->orWhereHas('student', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(admission_number) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(reg_no) LIKE ?', [$term]))
                    ->orWhereHas('teacher', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(employee_code) LIKE ?', [$term]));

                if (! empty($matchingSchoolIds)) {
                    $q->orWhereIn('school_id', $matchingSchoolIds);
                }
            });
        }
    }

    public function storeAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'registration_id'   => 'required|exists:mcq_registrations,id',
            'attendance_status' => 'required|in:present,absent,malpractice,withheld',
            'attendance_note'   => 'nullable|required_if:attendance_status,malpractice,withheld|string|max:1000',
        ]);

        $registration = McqRegistration::where('exam_id', $exam->id)->findOrFail($data['registration_id']);
        $registration->update([
            'attendance_status'    => $data['attendance_status'],
            'attendance_note'      => $data['attendance_note'] ?? null,
            'attendance_marked_at' => now(),
            'attendance_marked_by' => $request->user()->id,
        ]);

        if ($registration->blocksScoring() && $registration->mark) {
            $registration->mark()->delete();
            $registration->update(['status' => 'registered', 'submitted_at' => null]);
        }

        return back()->with('success', 'Attendance saved.');
    }

    public function importAttendance(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $result = app(\App\Services\Mcq\McqMarksAttendanceImporter::class)
            ->importAttendance($request->file('file'), $exam, $request->user()->id);

        if ($result['errors'] !== []) {
            $preview = collect($result['errors'])->take(8)->map(fn ($e) => "Row {$e['row']}: {$e['message']}")->implode(' · ');

            return back()->with([
                'success' => $result['imported'] > 0
                    ? "Imported attendance for {$result['imported']} registration(s)."
                    : null,
                'import_errors' => $result['errors'],
                'warning' => $preview !== '' ? "Import issues: {$preview}" : null,
            ]);
        }

        return back()->with('success', "Imported attendance for {$result['imported']} registration(s).");
    }

    public function exportAttendance(string $tenantId, McqExam $exam, \App\Services\Mcq\McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendance($exam);
    }

    public function attendanceSheetPdf(Request $request, string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $docs)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $docs->attendanceSheetPdf(
            $exam,
            schoolId: $request->input('school_id'),
            selectedClass: $request->input('class'),
            inline: $request->boolean('inline') || $request->query('preview') == '1',
            sahodaya: $this->sahodaya,
        );
    }

    public function markSheetPdf(string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $docs)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $docs->markSheetPdf($exam, $this->sahodaya);
    }

    public function resultSheetPdf(string $tenantId, McqExam $exam, \App\Services\Mcq\McqPrintableDocumentService $docs)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return $docs->resultSheetPdf($exam, $this->sahodaya);
    }

    public function registrationInvoice(string $tenantId, McqExam $exam, McqRegistration $registration, \App\Services\Mcq\McqRegistrationInvoiceService $invoices)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 404);

        return $invoices->download($registration, $this->sahodaya);
    }

    public function results(Request $request, string $tenantId, McqExam $exam, \App\Services\Mcq\McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search'    => 'nullable|string|max:100',
            'school_id' => 'nullable|string|max:36',
            'class'     => 'nullable|string|max:60',
        ]);

        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['mark', 'student', 'school', 'feeReceipt']);

        $this->applyRegistrationFilters($query, $filters);

        $registrations = $query->orderBy('hall_ticket_no')
            ->paginate(50)
            ->withQueryString();

        // Active = excludes cancelled registrations, matching the Reports page's "Registrations" stat.
        $base = McqRegistration::where('exam_id', $exam->id)->active();
        $counts = [
            'total'   => (clone $base)->count(),
            'present' => (clone $base)->where('attendance_status', 'present')->count(),
            'marked'  => (clone $base)->whereHas('mark', fn ($q) => $q->whereNotNull('score'))->count(),
        ];

        $gradeBands = app(\App\Services\Mcq\McqGradeService::class)->bandsForExam($exam);

        return $this->inertia('Sahodaya/Mcq/Results', [
            'exam'          => $exam,
            'registrations' => $registrations,
            'gradeBands'    => $gradeBands,
            'counts'        => $counts,
            'filters'       => $filters,
            'schoolOptions' => $reports->schoolFilterOptions($exam),
            'classOptions'  => $reports->classFilterOptions($exam),
        ]);
    }

    public function hallTickets(Request $request, string $tenantId, McqExam $exam, \App\Services\Mcq\McqReportService $reports)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search'    => 'nullable|string|max:100',
            'school_id' => 'nullable|string|max:36',
            'class'     => 'nullable|string|max:60',
        ]);

        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->with(['student.schoolClass', 'teacher', 'school']);

        $this->applyRegistrationFilters($query, $filters);

        $registrations = $query->orderBy('hall_ticket_no')
            ->paginate(50)
            ->withQueryString();

        $design = McqHallTicketDesign::fromExam($exam);
        $logoUrl = McqHallTicketDesign::logoUrl($this->sahodaya, $design);
        $ticketsIssued = McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->exists();
        $seating = app(\App\Services\Mcq\McqSeatingService::class);

        $templates = \App\Models\McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get(['id', 'title', 'is_default', 'design_json']);

        $memberSchools = \App\Models\Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
            ->values();

        return $this->inertia('Sahodaya/Mcq/HallTickets', [
            'exam'            => $exam,
            'templates'       => $templates,
            'registrations'   => $registrations,
            'savedExports' => \App\Models\McqAdmitCardExport::where('tenant_id', $this->sahodaya->id)
                ->where('exam_id', $exam->id)->latest('id')->limit(30)
                ->get(['id', 'status', 'scope_description', 'total_count', 'filename', 'created_at', 'completed_at']),
            'hallTicketDesign'=> $design,
            'backgroundUrl' => TenantStorage::logoUrl($this->sahodaya, $design['background_path'] ?? null),
            'defaultHallTicketDesign' => McqHallTicketDesign::defaults(),
            'logoUrl'         => $logoUrl,
            'previewSample'   => McqHallTicketDesign::previewSample($exam),
            'ticketsIssued'   => $ticketsIssued,
            'halls'           => $seating->normalizedHalls($exam),
            'hallTicketsPublished'   => (bool) $exam->hall_tickets_published,
            'hallTicketsPublishedAt' => $exam->hall_tickets_published_at?->format('d M Y, h:i A'),
            'filters'         => $filters,
            'schoolOptions'   => $reports->schoolFilterOptions($exam),
            'memberSchools'   => $memberSchools,
            'classOptions'    => $reports->classFilterOptions($exam),
        ]);
    }

    public function previewHallTicket(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $design = McqHallTicketDesign::normalize(
            array_merge(
                McqHallTicketDesign::fromExam($exam),
                array_filter([
                    'header_title'  => request('header_title'),
                    'footer_note'   => request('footer_note'),
                    'primary_color' => request('primary_color'),
                    'accent_color'  => request('accent_color'),
                    'layout'        => request('layout'),
                    'show_reg_no'   => request()->has('show_reg_no') ? request()->boolean('show_reg_no') : null,
                    'show_school'   => request()->has('show_school') ? request()->boolean('show_school') : null,
                ], fn ($v) => $v !== null),
            ),
        );

        return view('mcq.hall-ticket-preview', [
            'design'  => $design,
            'logoUrl' => McqHallTicketDesign::logoUrl($this->sahodaya, $design),
            'sample'  => McqHallTicketDesign::previewSample($exam),
            'tenant' => $this->sahodaya,
        ]);
    }

    public function updateHallTicketDesign(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'header_title'        => 'nullable|string|max:120',
            'footer_note'         => 'nullable|string|max:500',
            'show_reg_no'         => 'nullable|boolean',
            'show_school'         => 'nullable|boolean',
            'show_exam_passcode'  => 'nullable|boolean',
            'primary_color'       => 'nullable|string|max:7',
            'accent_color'        => 'nullable|string|max:7',
            'layout'              => 'nullable|in:standard,compact,stse_three_up,custom_background',
            'background' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'controller_signature' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'remove_controller_signature' => 'nullable|boolean',
            'show_controller_sig' => 'nullable|boolean',
            'show_exam_date' => 'nullable|boolean',
            'field_positions' => 'nullable|array',
            'remarks_text' => 'nullable|string|max:3000',
            'remarks_height_mm' => 'nullable|numeric|min:15|max:257',
            'remarks_format' => 'nullable|in:paragraph,numbered,bullets',
            'remarks_title' => 'nullable|string|max:100',
            'remarks_font_size' => 'nullable|numeric|min:8|max:18',
            'remarks_theme_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'remarks_paper_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'remarks_text_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'remarks_text_style' => 'nullable|array',
            'show_student_photo' => 'nullable|boolean',
            'venue_source' => 'nullable|in:assigned_centre,student_school',
            'custom_fields' => 'nullable|array|max:20',
            'custom_fields.*' => 'array',
            'custom_fields.*.id' => 'required|string|regex:/^custom_[a-zA-Z0-9_-]{1,64}$/',
            'custom_fields.*.label' => 'nullable|string|max:80',
            'custom_fields.*.show_label' => 'nullable|boolean',
            'custom_fields.*.text' => 'nullable|string|max:500',
            'custom_fields.*.source' => 'required|in:text,school_name,center_code,hall_room,seat_no,exam_title,admission_no,academic_year,gender',
            'custom_fields.*.x' => 'required|numeric|min:0|max:95',
            'custom_fields.*.y' => 'required|numeric|min:0|max:95',
            'custom_fields.*.width' => 'required|numeric|min:1|max:100',
            'custom_fields.*.size' => 'required|numeric|min:6|max:72',
            'custom_fields.*.font_family' => 'nullable|in:Arial,Times New Roman,Georgia,Verdana,Tahoma,Courier New',
            'custom_fields.*.font_weight' => 'nullable|in:normal,bold',
            'custom_fields.*.font_style' => 'nullable|in:normal,italic',
            'custom_fields.*.text_align' => 'nullable|in:left,center,right,justify',
            'custom_fields.*.text_decoration' => 'nullable|in:none,underline',
            'custom_fields.*.text_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'custom_fields.*.line_height' => 'nullable|numeric|min:1|max:2.5',
            'custom_fields.*.letter_spacing' => 'nullable|numeric|min:-1|max:5',
            'custom_fields.*.layer' => 'nullable|integer|min:1|max:50',
            'card_width_mm' => 'nullable|numeric|min:20|max:277',
            'card_height_mm' => 'nullable|numeric|min:20|max:277',
            'page_orientation' => 'nullable|in:portrait,landscape',
            'card_gap_mm' => 'nullable|numeric|min:0|max:15',
            'page_margin_mm' => 'nullable|numeric|min:0|max:20',
            'background_fit' => 'nullable|in:contain,stretch',
            'next_hall_ticket_no' => 'nullable|integer|min:1|max:99999999',
            'hall_instructions'   => 'nullable|string|max:2000',
            'logo'                => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'remove_logo'         => 'nullable|boolean',
            'show_photo'          => 'nullable|boolean',
            'show_qr'             => 'nullable|boolean',
            'show_signature'      => 'nullable|boolean',
            'report_before_minutes'      => 'nullable|integer|min:0|max:240',
            'gate_closure_after_minutes' => 'nullable|integer|min:0|max:240',
            'hall_ticket_template_id'    => 'nullable|integer',
        ]);

        if (array_key_exists('next_hall_ticket_no', $data) && $data['next_hall_ticket_no'] !== null) {
            $newNextHallTicketNo = McqExamPayload::nextHallTicketNo($data['next_hall_ticket_no']);
            $hasTickets = McqRegistration::where('exam_id', $exam->id)->whereNotNull('hall_ticket_no')->exists();
            if ($hasTickets) {
                abort_if($newNextHallTicketNo !== (int) $exam->next_hall_ticket_no, 422, 'Starting reg. no. cannot be changed after hall tickets are issued.');
                unset($data['next_hall_ticket_no']);
            } else {
                $data['next_hall_ticket_no'] = $newNextHallTicketNo;
            }
        }

        $current = McqHallTicketDesign::fromExam($exam);

        if (($data['layout'] ?? '') === 'custom_background' && empty($current['background_path'])) {
            $request->validate(['background' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048']);
        }
        if ($request->hasFile('background')) {
            $imageSize = getimagesize($request->file('background')->getRealPath());
            $current['background_aspect_ratio'] = $imageSize[0] / $imageSize[1];
            $current['background_path'] = TenantStorage::storeUploadedFile(
                $request->file('background'), "mcq-hall-tickets/{$this->sahodaya->id}/{$exam->id}/backgrounds",
            );
        }

        if ($request->boolean('remove_controller_signature')) {
            $current['controller_signature_path'] = null;
        }
        if ($request->hasFile('controller_signature')) {
            $current['controller_signature_path'] = TenantStorage::storeUploadedFile(
                $request->file('controller_signature'), "mcq-hall-tickets/{$this->sahodaya->id}/{$exam->id}/signatures"
            );
        }
        if ($request->boolean('remove_logo')) {
            $current['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            $current['logo_path'] = TenantStorage::storeUploadedFile(
                $request->file('logo'),
                "mcq-hall-tickets/{$this->sahodaya->id}/{$exam->id}",
            );
        }

        $design = McqHallTicketDesign::normalize(array_merge($current, [
            'show_controller_sig' => array_key_exists('show_controller_sig', $data) ? (bool) $data['show_controller_sig'] : $current['show_controller_sig'],
            'show_exam_date' => array_key_exists('show_exam_date', $data) ? (bool) $data['show_exam_date'] : $current['show_exam_date'],
            'header_title'  => $data['header_title'] ?? $current['header_title'],
            'footer_note'   => $data['footer_note'] ?? $current['footer_note'],
            'show_reg_no'   => array_key_exists('show_reg_no', $data) ? (bool) $data['show_reg_no'] : $current['show_reg_no'],
            'show_school'   => array_key_exists('show_school', $data) ? (bool) $data['show_school'] : $current['show_school'],
            'show_exam_passcode' => array_key_exists('show_exam_passcode', $data) ? (bool) $data['show_exam_passcode'] : $current['show_exam_passcode'],
            'primary_color' => $data['primary_color'] ?? $current['primary_color'],
            'accent_color'  => $data['accent_color'] ?? $current['accent_color'],
            'layout'        => $data['layout'] ?? $current['layout'],
            'field_positions' => $data['field_positions'] ?? $current['field_positions'],
            'show_student_photo' => $request->boolean('show_student_photo'),
            'custom_fields' => $data['custom_fields'] ?? [],
            'remarks_text' => $data['remarks_text'] ?? '',
            'remarks_height_mm' => $data['remarks_height_mm'] ?? 35,
            'remarks_format' => $data['remarks_format'] ?? 'paragraph',
            'remarks_title' => $data['remarks_title'] ?? 'Remarks / Instructions',
            'remarks_font_size' => $data['remarks_font_size'] ?? 11,
            'remarks_theme_color' => $data['remarks_theme_color'] ?? '#101b6f',
            'remarks_paper_color' => $data['remarks_paper_color'] ?? '#ffffff',
            'remarks_text_color' => $data['remarks_text_color'] ?? '#111111',
            'remarks_text_style' => $data['remarks_text_style'] ?? [],
            ...array_intersect_key($data, array_flip(['card_width_mm', 'card_height_mm', 'page_orientation', 'card_gap_mm', 'page_margin_mm', 'background_fit', 'venue_source'])),
            'show_photo'     => array_key_exists('show_photo', $data) ? (bool) $data['show_photo'] : $current['show_photo'],
            'show_qr'        => array_key_exists('show_qr', $data) ? (bool) $data['show_qr'] : $current['show_qr'],
            'show_signature' => array_key_exists('show_signature', $data) ? (bool) $data['show_signature'] : $current['show_signature'],
            'report_before_minutes'      => $data['report_before_minutes'] ?? $current['report_before_minutes'],
            'gate_closure_after_minutes' => $data['gate_closure_after_minutes'] ?? $current['gate_closure_after_minutes'],
        ]));

        $settings = McqHallTicketDesign::mergeIntoSettings($exam->settings_json ?? [], $design);
        $settings['hall_ticket_design_scope'] = 'exam';

        $updateFields = [
            'settings_json'       => $settings,
            'hall_ticket_template_id' => null,
            'hall_instructions'   => $data['hall_instructions'] ?? $exam->hall_instructions,
            'next_hall_ticket_no' => $data['next_hall_ticket_no'] ?? $exam->next_hall_ticket_no,
        ];


        $exam->update($updateFields);

        return back()->with('success', 'Hall ticket design saved.');
    }

    /** New online exam passcode for one candidate (lost hall ticket, suspected sharing). */
    public function reissuePasscode(string $tenantId, McqExam $exam, McqRegistration $registration, \App\Services\Mcq\McqExamPasscodeService $passcodes)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id || $registration->exam_id !== $exam->id, 403);
        $this->assertStaffCan('mcq.manage');

        if (! $registration->hall_ticket_no) {
            return back()->with('error', 'Issue the hall ticket first — the passcode is created with it.');
        }

        $registration->loadMissing(['student:id,name', 'teacher:id,name']);
        $code = \App\Services\Mcq\McqExamPasscodeService::format($passcodes->reissue($registration));
        $name = $registration->student?->name ?? $registration->teacher?->name ?? 'the candidate';

        return back()->with('success', "New passcode for {$name}: {$code}. The old one no longer works — reprint their hall ticket.");
    }

    public function printAllHallTickets(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'school_id' => 'nullable|string|max:36',
            'class' => 'nullable|string|max:60',
            'registration_id' => 'nullable|integer|min:1',
            'download' => 'nullable|boolean',
        ]);
        $query = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->whereNotNull('hall_ticket_no')
            ->with(['student.schoolClass', 'teacher', 'school']);
        $this->applyRegistrationFilters($query, $filters);
        if (! empty($filters['registration_id'])) {
            $query->whereKey($filters['registration_id']);
        }
        $registrations = $query->orderBy('hall_ticket_no')->get();
        abort_if($registrations->isEmpty(), 404, 'No issued admit cards match this selection. Assign registration numbers first.');

        if ($request->boolean('download')) {
            $design = McqHallTicketDesign::fromExam($exam);
            $artwork = in_array($design['layout'] ?? '', ['stse_three_up', 'custom_background'], true);
            $suffix = ! empty($filters['registration_id']) ? '-'.$filters['registration_id']
                : (! empty($filters['class']) ? '-class-'.\Illuminate\Support\Str::slug($filters['class']) : '');

            return \App\Support\PdfGenerator::fromView(
                'mcq.hall-tickets-bulk',
                compact('exam', 'registrations', 'design') + ['pdfDownload' => true],
                (\Illuminate\Support\Str::slug($exam->title) ?: 'exam').'-admit-cards'.$suffix.'.pdf',
                isLandscape: $artwork && ($design['page_orientation'] ?? 'landscape') === 'landscape',
            );
        }

        return view('mcq.hall-tickets-bulk', compact('exam', 'registrations'));
    }

    public function saveAdmitCardPdf(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'school_id' => 'nullable|string|max:36',
            'class' => 'nullable|string|max:60',
            'registration_id' => 'nullable|integer|min:1',
        ]);
        $query = McqRegistration::where('exam_id', $exam->id)->active()->whereNotNull('hall_ticket_no');
        if (! empty($filters['registration_id'])) {
            $query->whereKey($filters['registration_id']);
        } else {
            $this->applyRegistrationFilters($query, $filters);
        }
        $ids = $query->orderBy('hall_ticket_no')->pluck('id')->all();
        if (! $ids) {
            return back()->with('error', 'No issued admit cards match this selection. Assign registration numbers first.');
        }
        $scope = ! empty($filters['registration_id']) ? 'Individual candidate #'.$filters['registration_id']
            : implode(' · ', array_filter([
                ! empty($filters['class']) ? 'Class: '.$filters['class'] : null,
                ! empty($filters['school_id']) ? 'School: '.\App\Models\Tenant::find($filters['school_id'])?->name : null,
                ! empty($filters['search']) ? 'Search: '.$filters['search'] : null,
            ]));
        $export = \App\Models\McqAdmitCardExport::create([
            'tenant_id' => $this->sahodaya->id, 'exam_id' => $exam->id,
            'scope_description' => $scope ?: 'All issued admit cards',
            'registration_ids' => $ids, 'total_count' => count($ids), 'status' => 'pending',
            'filename' => (\Illuminate\Support\Str::slug($exam->title) ?: 'exam').'-admit-cards-'.now()->format('Ymd-His').'.pdf',
            'storage_disk' => TenantStorage::uploadDisk(),
        ]);
        \App\Jobs\GenerateMcqAdmitCardExport::dispatch($this->sahodaya->id, $export->id);

        return back()->with('success', 'Admit card PDF generation started. The saved file will appear below when ready.');
    }

    public function downloadSavedAdmitCardPdf(string $tenantId, McqExam $exam, \App\Models\McqAdmitCardExport $export)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id || $export->tenant_id !== $this->sahodaya->id
            || $export->exam_id !== $exam->id, 403);
        abort_if($export->status !== 'completed' || ! $export->file_path, 404, 'The PDF is not ready.');

        return TenantStorage::downloadPrivate($export->file_path, $export->storage_disk, $export->filename);
    }

    public function generateHallTickets(string $tenantId, McqExam $exam, McqHallTicketService $service)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $count = $service->issueBulk($exam, true);

        if ($count === 0) {
            return back()->with('info', 'All active candidates already have a Reg. No. assigned.');
        }

        return back()->with('success', "Assigned Reg. No. to {$count} candidate(s).");
    }

    private function centreCodeInput(Request $request): array
    {
        $data = $request->validate([
            'prefix' => 'nullable|string|max:16|regex:/^[A-Za-z0-9 _\/-]*$/',
            'start' => 'required|integer|min:0|max:999999999',
            'digits' => 'required|integer|min:0|max:8',
            'scope' => 'required|in:registered,all',
            'replace' => 'required|boolean',
        ]);
        $data['replace'] = $request->boolean('replace');
        return $data;
    }

    public function schoolCentreCodes(string $tenantId, McqExam $exam, Request $request, \App\Services\Mcq\McqSchoolCentreCodeService $service)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $input = $request->boolean('preview') ? $this->centreCodeInput($request) : null;
        return $this->inertia('Sahodaya/Mcq/CentreCodes', [
            'exam' => $exam,
            'schools' => $service->schools($exam),
            'numbering' => $input ?? array_merge(['prefix' => '', 'start' => 1, 'digits' => 3, 'scope' => 'registered'], $exam->settings_json['centre_code_numbering'] ?? [], ['replace' => false]),
            'assignmentPreview' => $input ? $service->plan($exam, $input) : null,
        ]);
    }

    public function assignSchoolCentreCodes(string $tenantId, McqExam $exam, Request $request, \App\Services\Mcq\McqSchoolCentreCodeService $service)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $count = $service->assign($exam, $this->centreCodeInput($request));
        return redirect()->route('sahodaya.mcq.centre-codes', [$tenantId, $exam->id])
            ->with('success', "Assigned centre codes to {$count} schools. Previous assignments backed up.");
    }

    public function saveSchoolCentreCode(string $tenantId, McqExam $exam, Request $request)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $data = $request->validate([
            'school_id' => ['required', 'uuid', \Illuminate\Validation\Rule::exists('tenants', 'id')->where('parent_id', $this->sahodaya->id)->where('type', 'school')],
            'code' => 'nullable|string|max:40|regex:/^[A-Za-z0-9 _\/-]*$/',
        ]);
        DB::transaction(function () use ($exam, $data) {
            $locked = McqExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $settings = $locked->settings_json ?? [];
            $codes = $settings['school_centre_codes'] ?? [];
            $code = trim($data['code'] ?? '');
            foreach ($codes as $schoolId => $existing) {
                if ($schoolId !== $data['school_id'] && $code !== '' && strcasecmp($existing, $code) === 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'This centre code is already assigned to another school in this exam.']);
                }
            }
            if ($code === '') unset($codes[$data['school_id']]);
            else $codes[$data['school_id']] = $code;
            $settings['school_centre_codes'] = $codes;
            $locked->update(['settings_json' => $settings]);
        });
        return back()->with('success', 'School centre code saved for this exam.');
    }

    public function resetRegistrationNumbers(string $tenantId, McqExam $exam, Request $request)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $data = $request->validate([
            'ranges' => 'required|array|min:1|max:100',
            'ranges.*.class' => 'required|string|max:100|distinct',
            'ranges.*.start' => 'required|integer|min:1|max:999999999',
        ]);
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');
        $starts = collect($data['ranges'])->pluck('start', 'class')->all();
        $result = app(\App\Services\Mcq\McqClassRollRangeService::class)->apply($exam, $starts);

        return back()->with('success', "Reset and applied exam registration numbers for {$result['renumbered']} candidates. Previous numbers backed up. School admission numbers, payment and approval statuses unchanged.");
    }

    /** Re-sort every active candidate into a fresh 1..N roll number per class (shared across schools). */
    public function renumberHallTickets(string $tenantId, McqExam $exam, Request $request, McqHallTicketService $service)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        if ($exam->hall_tickets_published && ! $request->boolean('force')) {
            abort(422, 'Hall tickets are already published to schools. Pass force=1 to confirm renumbering.');
        }

        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '300');

        $result = $service->renumberByClass($exam, true);

        if ($result['renumbered'] === 0) {
            return back()->with('warning', 'No active registrations found to renumber.');
        }

        $note = $result['cleared'] > 0 ? " {$result['cleared']} cancelled registration(s) had their number cleared." : '';

        return back()->with('success', "Regenerated Reg. Nos. for {$result['renumbered']} candidate(s) across {$result['classes']} class(es), starting at {$result['start']}.{$note}");
    }

    public function issueSingleRegNo(string $tenantId, McqExam $exam, McqRegistration $registration, McqHallTicketService $service)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);
        abort_if($registration->status === 'cancelled', 422, 'Cannot assign Reg. No. to a cancelled registration.');

        $reg = $service->issueForRegistration($registration, true);
        app(McqSeatingService::class)->recalculate($exam);

        return back()->with('success', "Assigned Reg. No. {$reg->hall_ticket_no} to {$reg->participantName()}.");
    }

    /**
     * Release hall tickets (roll no., hall, seat) to schools and candidates.
     * Roll numbers are already assigned per-registration as each is approved — this flag is
     * the single, coordinated "make it visible" switch so schools don't see numbers trickle
     * in one student at a time while Sahodaya is still finalizing halls/seating.
     */
    public function publishHallTickets(string $tenantId, McqExam $exam, Request $request)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $exam->update([
            'hall_tickets_published'           => true,
            'hall_tickets_published_at'        => now(),
            'hall_tickets_published_by_user_id'=> $request->user()?->id,
        ]);

        return back()->with('success', 'Hall tickets are now visible to schools and candidates.');
    }

    public function unpublishHallTickets(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $exam->update([
            'hall_tickets_published'    => false,
            'hall_tickets_published_at' => null,
        ]);

        return back()->with('success', 'Hall tickets are hidden from schools until you publish them again.');
    }

    public function saveHalls(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'halls' => 'nullable|array|max:50',
            'halls.*.name' => 'required_with:halls|string|max:80',
            'halls.*.capacity' => 'required_with:halls|integer|min:1|max:5000',
        ]);

        app(\App\Services\Mcq\McqSeatingService::class)->saveHalls($exam, $data['halls'] ?? []);

        return back()->with('success', 'Hall plan saved.');
    }

    public function allocateSeats(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'reallocate' => 'nullable|boolean',
        ]);

        $result = app(\App\Services\Mcq\McqSeatingService::class)
            ->allocateForExam($exam, (bool) ($data['reallocate'] ?? false));

        return back()->with(
            'success',
            "Allocated seats for {$result['allocated']} candidate(s) across {$result['halls_used']} hall(s).",
        );
    }

    public function staff(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $assignments = McqExamStaff::where('exam_id', $exam->id)->with('user')->get();
        $staffPool = User::role(['exam_controller', 'exam_staff', 'mark_entry_admin'])
            ->where('tenant_id', $this->sahodaya->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $this->inertia('Sahodaya/Mcq/Staff', compact('exam', 'assignments', 'staffPool'));
    }

    public function storeStaff(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|in:controller,staff',
        ]);

        McqExamStaff::firstOrCreate(
            ['exam_id' => $exam->id, 'user_id' => $data['user_id']],
            ['role' => $data['role']],
        );

        app(\App\Services\Audit\PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.staff.assigned',
            "Exam staff assigned to {$exam->title}",
            ['user_id' => $data['user_id'], 'role' => $data['role']],
        );

        return back()->with('success', 'Staff assigned.');
    }

    public function generateCertificates(string $tenantId, McqExam $exam, \App\Services\Mcq\McqCertificateService $certificates)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $count = $certificates->issueBulk($exam);

        app(\App\Services\Audit\PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.certificates.generated',
            "Generated {$count} Talent Search certificate(s) for {$exam->title}",
            ['count' => $count],
        );

        return back()->with('success', "{$count} certificate(s) generated.");
    }

    public function printCertificate(string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $certificate = app(\App\Services\Mcq\McqCertificateService::class)->issue($registration->load(['exam', 'student', 'school', 'mark']));

        return view('mcq.certificate', [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'     => $this->sahodaya,
            'fields'       => app(\App\Services\Mcq\McqCertificateService::class)->fieldValues($registration, $this->sahodaya),
            'design'       => $certificate->design_snapshot_json ?? [],
        ]);
    }

    public function previewCertificate(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $service = app(\App\Services\Mcq\McqCertificateService::class);
        $exam->loadMissing('series');

        return view('mcq.certificate', [
            'registration' => null,
            'certificate'  => (object) ['verification_uuid' => 'SAMPLE-DEMO-0000'],
            'sahodaya'     => $this->sahodaya,
            'fields'       => $service->previewSampleFields($exam, $this->sahodaya),
            'design'       => $service->previewDesign($exam),
            'isSample'     => true,
        ]);
    }

    public function destroyStaff(string $tenantId, McqExam $exam, McqExamStaff $staff)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($staff->exam_id !== $exam->id, 403);
        $staff->delete();

        return back()->with('success', 'Staff removed.');
    }

    /**
     * Paper builder: the exam's sections (one per linked bank, in order), each taking all,
     * the first N or a random N of the bank's questions. See McqPaperService.
     */
    public function questionBanks(string $tenantId, McqExam $exam, \App\Services\Mcq\McqPaperService $papers)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');

        $linkedIds = $exam->questionBanks()->pluck('mcq_question_banks.id');
        $availableQuery = McqQuestionBank::where('sahodaya_id', $this->sahodaya->id)
            ->where('status', 'active')
            ->whereNotIn('id', $linkedIds);
        // Association papers use Sahodaya-owned banks by default; member school banks only when allowed.
        if (! $exam->allowsMemberBanks()) {
            $availableQuery->whereNull('school_id');
        }
        \App\Support\Mcq\McqQuestionBankScope::constrainQueryToExam($availableQuery, $exam);
        $available = $availableQuery
            ->withCount('questions')
            ->with(['school:id,name', 'classCategory:id,label', 'masterClass:id,name', 'contributedBySchool:id,name'])
            ->orderBy('title')
            ->get();

        return $this->inertia('Sahodaya/Mcq/QuestionBanks', [
            'exam'      => array_merge($exam->only('id', 'title', 'delivery_mode', 'results_published', 'total_questions', 'status'), [
                'product_mode' => $exam->productMode(),
                'is_model_exam' => $exam->isModelExam(),
                'allow_member_banks' => $exam->allowsMemberBanks(),
                'eligibility_filter' => \App\Support\Mcq\McqQuestionBankScope::examFilterMeta($exam),
            ]),
            'paper'     => $papers->summary($exam),
            'available' => $available->map(fn (McqQuestionBank $bank) => [
                'id' => $bank->id,
                'title' => $bank->title,
                'subject' => $bank->subject,
                'class_group' => $bank->class_group,
                'questions_count' => $bank->questions_count,
                'scope_label' => \App\Support\Mcq\McqQuestionBankScope::label($bank) ?? 'Any class',
                'owner_label' => $bank->school_id
                    ? ($bank->school?->name ?? 'Member school')
                    : ($bank->contributed_by_school_id
                        ? ('Sahodaya · from '.($bank->contributedBySchool?->name ?? 'school'))
                        : 'Sahodaya'),
            ]),
            'paperTemplates' => \App\Models\McqPaperTemplate::where('tenant_id', $exam->tenant_id)->orderBy('title')->get()->map(fn ($t) => [
                'id' => $t->id, 'title' => $t->title, 'version' => $t->version, 'sections' => $t->definition_json,
                'total_marks' => $t->total_marks, 'question_count' => count($t->questions_json), 'is_active' => $t->is_active,
                'questions' => collect($t->questions_json)->map(fn ($q) => ['id' => $q['id'], 'body_text' => $q['body_text'], 'marks' => $q['marks'], 'section_title' => $q['section_title'] ?? ''])->all(),
            ]),
            'classPapers' => $exam->settings_json['paper_assignments'] ?? [],
            'paperFallback' => $exam->settings_json['paper_fallback'] ?? 'require',
            'templateBanks' => McqQuestionBank::where('sahodaya_id', $exam->tenant_id)->where('status', 'active')->withCount('questions')->orderBy('title')->get(['id', 'title', 'subject']),
            'paperClasses' => \App\Models\SchoolClass::whereIn('id', \App\Models\Student::whereIn('id', McqRegistration::where('exam_id', $exam->id)->where('status', '!=', 'cancelled')->select('student_id'))->select('school_class_id'))->distinct()->pluck('name'),
            'settings'  => [
                'shuffle_questions' => (bool) ($exam->settings_json['shuffle_questions'] ?? false),
                'shuffle_options' => (bool) ($exam->settings_json['shuffle_options'] ?? false),
                'multi_marking'     => $exam->multiMarkingMode(),
            ],
            'banksUrl'  => "/sahodaya-admin/{$this->sahodaya->id}/mcq/question-banks",
            'engine'    => $this->enginePanel($exam),
            'capacityGate' => app(\App\Services\ExamEngine\ExamEngineCapacityGate::class)->panel($exam),
        ]);
    }

    /** Online exam engine settings + arming state for the Paper tab. */
    private function enginePanel(McqExam $exam): array
    {
        $bookings = app(\App\Services\ExamEngine\ExamEngineBookingService::class);
        $window = $exam->scheduled_at && \App\Services\ExamEngine\ExamEngineRun::usesEngine($exam) ? $bookings->window($exam) : null;
        $capacityGate = app(\App\Services\ExamEngine\ExamEngineCapacityGate::class)->panel($exam);

        return [
            'enabled'          => \App\Services\ExamEngine\ExamEngineRun::usesEngine($exam),
            'online'           => $exam->isOnlineDelivery(),
            'state'            => \App\Services\ExamEngine\ExamEngineRun::state($exam),
            'armed_at'         => $exam->settings_json['engine_armed_at'] ?? null,
            'version'          => $exam->settings_json['engine_bundle_version'] ?? null,
            'settings'         => \App\Services\ExamEngine\ExamEngineRun::settings($exam),
            'configured'       => app(\App\Services\ExamEngine\ExamEngineClient::class)->isConfigured(),
            'started'          => $exam->scheduled_at && now()->greaterThanOrEqualTo($exam->scheduled_at),
            'scheduled_at'     => $exam->scheduled_at?->toIso8601String(),
            'exam_site_url'    => \App\Services\ExamEngine\ExamEngineBridge::examSiteUrl($exam),
            'capacity'         => [
                'limit'          => (int) config('exam_engine.capacity'),
                'expected'       => $bookings->expectedCount($exam),
                'others_at_peak' => $window ? $bookings->peakLoad($window[0], $window[1], $exam->tenant_id, $exam->id) : 0,
                'window_start'   => $window ? $window[0]->toIso8601String() : null,
                'window_end'     => $window ? $window[1]->toIso8601String() : null,
            ],
            'force_threshold'  => $capacityGate['threshold'],
            'force_required'   => $capacityGate['required'],
            'force_message'    => $capacityGate['message'],
        ];
    }

    public function updateEngineSettings(Request $request, string $tenantId, McqExam $exam, \App\Services\ExamEngine\ExamEngineBridge $bridge, \App\Services\ExamEngine\ExamEngineBookingService $bookings)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');

        $data = $request->validate([
            'enabled'                        => 'required|boolean',
            'timing'                         => 'required|in:fixed,individual',
            'entry_mode'                     => 'required|in:either,portal_password,exam_passcode',
            'supervision'                    => 'required|in:open,supervised',
            'start_pin'                      => 'nullable|required_if:supervision,supervised|regex:/^[0-9]{4,8}$/',
            'checkin_hours'                  => 'required|integer|min:1|max:168',
            'waiting_room_minutes'           => 'required|integer|min:10|max:240',
            'late_entry_minutes'             => 'required|integer|min:0|max:120',
            'grace_seconds'                  => 'required|integer|min:30|max:600',
            'show_score_on_submit'           => 'required|boolean',
            'device_idle_minutes'            => 'required|integer|min:1|max:30',
            'coordinator_extend_cap_minutes' => 'required|integer|min:0|max:60',
        ], ['start_pin.regex' => 'The start PIN must be 4 to 8 digits.']);

        if ($data['enabled'] && ! $exam->isOnlineDelivery()) {
            return back()->with('error', 'Switch the exam to online delivery (Overview tab) before using the exam engine.');
        }

        $state = \App\Services\ExamEngine\ExamEngineRun::state($exam);
        $started = $exam->scheduled_at && now()->greaterThanOrEqualTo($exam->scheduled_at);
        $current = \App\Services\ExamEngine\ExamEngineRun::settings($exam);
        $settings = array_merge($current, \Illuminate\Support\Arr::except($data, ['enabled']), [
            'start_pin' => $data['supervision'] === 'supervised' ? ($data['start_pin'] ?? null) : null,
        ]);

        if ($state === 'armed' && $started) {
            $locked = array_diff_key($settings, array_flip(['start_pin', 'late_entry_minutes', 'show_score_on_submit', 'coordinator_extend_cap_minutes']));
            if (! $data['enabled'] || $locked != array_diff_key($current, array_flip(['start_pin', 'late_entry_minutes', 'show_score_on_submit', 'coordinator_extend_cap_minutes']))) {
                return back()->with('error', 'The exam has started: only the start PIN, late entry, score visibility and the coordinator extension cap can still change.');
            }
        }
        if ($state === 'armed' && ! $data['enabled']) {
            return back()->with('error', 'Disarm the exam on the engine before switching the engine off.');
        }

        $exam->update(['settings_json' => array_merge($exam->settings_json ?? [], [
            'engine'          => $data['enabled'] ? 'engine' : 'laravel',
            'engine_settings' => $settings,
        ])]);
        $exam->refresh();
        $bookings->sync($exam);

        if ($state === 'armed') {
            try {
                $started
                    ? app(\App\Services\ExamEngine\ExamEngineClient::class)->putMeta(\App\Services\ExamEngine\ExamEngineRun::for($exam), $bridge->meta($exam, app(\App\Services\Mcq\McqPaperService::class)->questionsForExam($exam), (int) ($exam->settings_json['engine_bundle_version'] ?? 1)))
                    : $bridge->arm($exam);
            } catch (\App\Services\ExamEngine\ExamEngineException $e) {
                return back()->with('error', 'Settings saved, but the exam engine was not updated: '.$e->getMessage());
            }

            return back()->with('success', 'Engine settings saved and sent to the exam engine.');
        }

        return back()->with('success', 'Engine settings saved.');
    }

    public function armEngine(string $tenantId, McqExam $exam, \App\Services\ExamEngine\ExamEngineBridge $bridge)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');

        try {
            $result = $bridge->arm($exam);
        } catch (\App\Services\ExamEngine\ExamEngineException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Armed on the exam engine: {$result['candidates']} candidates, {$result['questions']} questions (paper version {$result['version']}).");
    }

    public function disarmEngine(string $tenantId, McqExam $exam, \App\Services\ExamEngine\ExamEngineBridge $bridge)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');

        try {
            $bridge->disarm($exam);
        } catch (\App\Services\ExamEngine\ExamEngineException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Disarmed. Students can no longer check in until you arm it again.');
    }

    public function attachBank(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }

        $data = $request->validate(['bank_id' => 'required|integer']);
        $bank = McqQuestionBank::where('sahodaya_id', $this->sahodaya->id)->findOrFail($data['bank_id']);
        if ($bank->status !== 'active') {
            return back()->with('error', 'Only banks marked Ready can be added to a paper.');
        }
        if ($bank->school_id && ! $exam->allowsMemberBanks()) {
            return back()->with('error', 'Member school banks are not allowed on this exam. Enable “Allow member school banks” on Overview first.');
        }
        if (! \App\Support\Mcq\McqQuestionBankScope::matchesExam($bank, $exam)) {
            return back()->with('error', 'That bank’s class/category does not match this exam’s eligibility.');
        }

        $nextOrder = ((int) DB::table('mcq_exam_question_banks')->where('exam_id', $exam->id)->max('display_order')) + 1;
        $exam->questionBanks()->syncWithoutDetaching([$bank->id => ['pick_mode' => 'all', 'display_order' => $nextOrder]]);

        return back()->with('success', "Added \"{$bank->title}\" to the paper.");
    }

    public function updateSection(Request $request, string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }
        abort_unless($exam->questionBanks()->whereKey($bank->id)->exists(), 404);

        $data = $request->validate([
            'section_title' => 'nullable|string|max:120',
            'pick_mode'     => 'required|in:'.implode(',', \App\Services\Mcq\McqPaperService::PICK_MODES),
            'pick_count'    => 'nullable|required_unless:pick_mode,all|integer|min:1|max:1000',
        ]);
        if ($data['pick_mode'] === 'all') {
            $data['pick_count'] = null;
        }

        $exam->questionBanks()->updateExistingPivot($bank->id, $data);

        return back()->with('success', 'Section saved.');
    }

    public function moveSection(Request $request, string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }

        $direction = $request->validate(['direction' => 'required|in:up,down'])['direction'];
        $ids = $exam->questionBanks()->pluck('mcq_question_banks.id')->values()->all();
        $index = array_search($bank->id, $ids, true);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ids[$swap])) {
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];
            DB::transaction(function () use ($exam, $ids) {
                foreach ($ids as $position => $id) {
                    $exam->questionBanks()->updateExistingPivot($id, ['display_order' => $position + 1]);
                }
            });
        }

        return back();
    }

    public function updatePaperSettings(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }

        $data = $request->validate([
            'shuffle_questions' => 'required|boolean',
            'shuffle_options' => 'sometimes|boolean',
            'multi_marking'     => 'required|in:all_or_nothing,partial',
        ]);

        $exam->update(['settings_json' => array_merge($exam->settings_json ?? [], [
            'shuffle_questions' => (bool) $data['shuffle_questions'],
            'shuffle_options' => (bool) ($data['shuffle_options'] ?? $exam->settings_json['shuffle_options'] ?? false),
            'multi_marking'     => $data['multi_marking'],
        ])]);

        return back()->with('success', 'Paper settings saved.');
    }

    /** The paper exactly as it will be delivered (unshuffled), with answer keys for checking. */
    public function paperPreview(string $tenantId, McqExam $exam, \App\Services\Mcq\McqPaperService $papers)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');

        $banksUrl = "/sahodaya-admin/{$this->sahodaya->id}/mcq/question-banks";
        $assignment = null;
        if (request()->filled('class')) {
            $class = \App\Support\Mcq\McqClassKey::normalize((string) request('class'));
            $assignment = \App\Models\McqExamPaperAssignment::where('exam_id', $exam->id)->get()
                ->first(fn ($row) => in_array($class, $row->mapping_json['classes'], true));
            abort_unless($assignment, 404);
        }
        $source = $assignment ? collect($assignment->questions_json)->map(fn ($row) => (new \App\Models\McqQuestion)->forceFill($row)) : $papers->questionsForExam($exam);
        $questions = $source->values()->map(fn (\App\Models\McqQuestion $q, int $i) => [
            'id'           => $q->id,
            'number'       => $i + 1,
            'type'         => $q->typeValue(),
            'type_label'   => \App\Models\McqQuestion::TYPE_LABELS[$q->typeValue()],
            'body_text'    => $q->body_text,
            'image_url'    => $q->document_path ? ($assignment ? $this->paperPreviewImage($q->document_path) : "{$banksUrl}/{$q->bank_id}/questions/{$q->id}/image") : null,
            'options'      => collect($q->options_json ?? [])->map(fn ($o) => [
                'key'       => strtolower((string) ($o['key'] ?? '')),
                'label'     => (string) ($o['label'] ?? ''),
                'image_url' => filled($o['image_path'] ?? null) ? ($assignment ? $this->paperPreviewImage($o['image_path']) : "{$banksUrl}/{$q->bank_id}/questions/{$q->id}/image?option=".strtolower((string) $o['key'])) : null,
            ])->values(),
            'correct_keys' => $q->typeValue() === \App\Models\McqQuestion::TYPE_NUMERIC ? [] : $q->correctKeys(),
            'show_count'   => $q->showsCorrectCount(),
            'answer_label' => $q->answerKeyLabel(),
            'explanation'  => $q->explanation,
            'marks'        => $q->marksValue(),
            'negative_mark'=> $q->negativeMarkValue(),
            'gradable'     => $q->isGradable(),
        ]);

        return $this->inertia('Sahodaya/Mcq/PaperPreview', [
            'exam'      => $exam->only('id', 'title', 'delivery_mode', 'results_published', 'duration_minutes'),
            'questions' => $questions,
            'paper'     => $assignment ? ['total' => $source->count(), 'max_score' => $assignment->mapping_json['total_marks'], 'warnings' => []] : $papers->summary($exam),
        ]);
    }

    private function paperPreviewImage(string $path): ?string
    {
        $bytes = \App\Support\TenantStorage::get($path);
        if ($bytes === null) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        return in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)
            ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
    }

    public function detachBank(string $tenantId, McqExam $exam, McqQuestionBank $bank)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        $this->assertStaffCan('mcq.manage');
        if ($response = $this->rejectIfPaperLocked($exam)) {
            return $response;
        }
        $exam->questionBanks()->detach($bank->id);

        return back()->with('success', 'Section removed from the paper.');
    }

    private function rejectIfPaperLocked(McqExam $exam)
    {
        return McqRegistration::where('exam_id', $exam->id)->whereNotNull('started_at')->exists()
            ? back()->with('error', 'Students have started this exam, so its paper can no longer be changed.')
            : null;
    }

    public function sessionMonitor(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with(['student', 'school', 'mark', 'proctorEvents'])
            ->orderBy('hall_ticket_no')
            ->get()
            ->map(fn (McqRegistration $r) => [
                'id'                => $r->id,
                'student'           => $r->student?->name,
                'school'            => $r->school?->name,
                'hall_ticket_no'    => $r->hall_ticket_no,
                'status'            => $r->status,
                'attendance_status' => $r->attendance_status,
                'session_status'    => \App\Support\Mcq\McqSessionStatusPresenter::forRegistration($r, $exam),
                'started_at'        => $r->started_at?->toDateTimeString(),
                'submitted_at'      => $r->submitted_at?->toDateTimeString(),
                'score'             => $r->mark?->score,
                'proctor_events'    => $r->proctorEvents->map(fn ($e) => [
                    'type'        => $e->event_type,
                    'occurred_at' => $e->occurred_at?->toDateTimeString(),
                ])->values(),
            ]);

        return $this->inertia('Sahodaya/Mcq/SessionMonitor', compact('exam', 'registrations'));
    }

    public function bulkImportMarks(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($exam->results_published, 422, 'Results are published for this exam. Unpublish results before importing marks.');

        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240']);

        $result = app(\App\Services\Mcq\McqMarksAttendanceImporter::class)
            ->importMarks($request->file('file'), $exam);

        if (! $result['success'] && $result['imported'] === 0) {
            $first = $result['errors'][0]['message'] ?? 'Import failed.';

            return back()->withErrors(['file' => $first])->with('import_errors', $result['errors']);
        }

        $msg = "Imported marks for {$result['imported']} student(s).";
        if ($result['errors'] !== []) {
            $preview = collect($result['errors'])->take(8)->map(fn ($e) => "Row {$e['row']}: {$e['message']}")->implode(' · ');

            return back()->with([
                'success' => $msg,
                'import_errors' => $result['errors'],
                'warning' => $preview !== '' ? "Import issues: {$preview}" : null,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function computeRanking(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $count = app(\App\Services\Mcq\McqRankingService::class)->rankExam($exam);

        app(\App\Services\Audit\PlatformAuditLogger::class)->mcq(
            $exam,
            'mcq.ranking.computed',
            "Talent Search ranking computed for {$exam->title}",
            ['count' => $count],
        );

        return back()->with('success', "Ranking computed for {$count} student(s).");
    }

    public function activity(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $activityLogs = app(\App\Services\Audit\McqExamActivityService::class)->forExam($exam, 100);

        return $this->inertia('Sahodaya/Mcq/Activity', compact('exam', 'activityLogs'));
    }

    public function attendanceCorrections(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $requests = \App\Models\McqAttendanceCorrectionRequest::where('exam_id', $exam->id)
            ->with(['registration.student', 'registration.school', 'requestedBy:id,name', 'reviewedBy:id,name'])
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'status'            => $c->status,
                'status_label'      => $c->statusLabel(),
                'previous_status'   => $c->previous_status,
                'requested_status'  => $c->requested_status,
                'requested_note'    => $c->requested_note,
                'requested_by'      => $c->requestedBy?->name,
                'requested_by_role' => $c->requested_by_role,
                'reviewed_by'       => $c->reviewedBy?->name,
                'reviewed_at'       => $c->reviewed_at?->format('j M Y, g:i A'),
                'review_note'       => $c->review_note,
                'created_at'        => $c->created_at?->format('j M Y, g:i A'),
                'student_name'      => $c->registration?->student?->name,
                'school_name'       => $c->registration?->school?->name,
                'hall_ticket_no'    => $c->registration?->hall_ticket_no,
            ]);

        return $this->inertia('Sahodaya/Mcq/AttendanceCorrections', [
            'exam'     => $exam->only('id', 'title', 'results_published', 'delivery_mode'),
            'requests' => $requests,
        ]);
    }

    public function approveAttendanceCorrection(
        Request $request,
        string $tenantId,
        McqExam $exam,
        \App\Models\McqAttendanceCorrectionRequest $correctionRequest,
    ) {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($correctionRequest->exam_id !== $exam->id, 403);

        $data = $request->validate(['review_note' => 'nullable|string|max:1000']);

        app(\App\Services\Mcq\McqAttendanceCorrectionService::class)->approve(
            $correctionRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return back()->with('success', 'Correction approved and attendance updated.');
    }

    public function rejectAttendanceCorrection(
        Request $request,
        string $tenantId,
        McqExam $exam,
        \App\Models\McqAttendanceCorrectionRequest $correctionRequest,
    ) {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($correctionRequest->exam_id !== $exam->id, 403);

        $data = $request->validate(['review_note' => 'nullable|string|max:1000']);

        app(\App\Services\Mcq\McqAttendanceCorrectionService::class)->reject(
            $correctionRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return back()->with('success', 'Correction request rejected.');
    }
}
