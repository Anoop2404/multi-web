@php
    $design = \App\Support\Mcq\McqHallTicketDesign::normalize($design ?? null);
    $sample = $sample ?? [];
    
    // Header & Org branding
    $orgName = $design['organization_name'];
    $subHeading = $design['sub_heading'];
    $subTitle = $design['sub_title'];
    $headerTitle = $design['header_title'];
    $titleLeft = $design['title_left'];
    $primary = $design['primary_color'];
    $accent = $design['accent_color'];
    $layout = $design['layout'];
    $logoUrl = $logoUrl ?? null;
    $boardLogoUrl = $boardLogoUrl ?? null;

    // Feature toggles
    $showSchoolAdmissionNo = $design['show_reg_no'];
    $showRollNo = $design['show_roll_no'];
    $showSchool = $design['show_school'];
    $showPhoto = $design['show_photo'];
    $showQr = $design['show_qr'];
    $showSignature = $design['show_signature'];
    $showCandidateSig = $design['show_candidate_sig'];
    $showInvigilatorSig = $design['show_invigilator_sig'];
    $showControllerSig = $design['show_controller_sig'];
    $signatoryTitle = $design['signatory_title'];
    $showAuditBar = $design['show_audit_bar'];
    $showCenterBox = $design['show_center_box'];
    $showExamSchedule = $design['show_exam_schedule'];
    $examModeLabel = $design['exam_mode_label'];
    $instructionsTitle = $design['instructions_title'];
    $instructions = $design['instructions'] ?? [];
    $disclaimer = $design['disclaimer'];
    $footerNote = $design['footer_note'] ?? null;

    // Participant data
    $participantType = $sample['participant_type'] ?? 'student';
    $participantName = $sample['participant_name'] ?? $sample['student_name'] ?? 'Sample Student';
    $secondaryLabel = $sample['secondary_label'] ?? 'School admission no.';
    $secondaryValue = $sample['secondary_value'] ?? ($sample['student_reg_no'] ?? ($sample['admission_no'] ?? null));
    $tertiaryLabel = $sample['tertiary_label'] ?? 'Class';
    $tertiaryValue = $sample['tertiary_value'] ?? ($sample['class_name'] ?? 'Class 10');
    $className = $sample['class_name'] ?? ($sample['tertiary_value'] ?? 'Class 10');
    $sectionName = $sample['section_name'] ?? null;
    $schoolName = $sample['school_name'] ?? 'Sample Model School';
    $fatherName = $sample['father_name'] ?? null;
    $motherName = $sample['mother_name'] ?? null;
    $academicYear = $sample['academic_year'] ?? null;
    $examRegNo = $sample['hall_ticket_no'] ?? '10001';

    // Seating & Venue
    $venue = $sample['venue'] ?? null;
    $centerCode = $sample['center_code'] ?? 'CEN-101';
    $centerName = $sample['center_name'] ?? ($venue ?? 'Designated Examination Centre');
    $centerAddress = $sample['center_address'] ?? ($venue ?? null);
    $hallRoom = $sample['hall_room'] ?? null;
    $seatNo = $sample['seat_no'] ?? null;

    // Exam specs (Per-Class)
    $examTitle = $sample['exam_title'] ?? 'MCQ Talent Search Examination';
    $paperName = $sample['paper_name'] ?? $examTitle;
    $examCode = $sample['exam_code'] ?? null;
    $examDate = $sample['exam_date'] ?? 'TBA';
    $examTime = $sample['exam_time'] ?? ($sample['scheduled_at_label'] ?? 'TBA');
    $scheduledLabel = $sample['scheduled_at_label'] ?? 'TBA';
    $reportTimeLabel = $sample['report_time_label'] ?? null;
    $gateClosureLabel = $sample['gate_closure_label'] ?? null;
    $durationMinutes = $sample['duration_minutes'] ?? 60;
    $totalQuestions = $sample['total_questions'] ?? 60;
    $maxMarks = $sample['max_marks'] ?? $totalQuestions;
    $generatedAt = $sample['generated_at'] ?? now()->format('d-m-Y H:i:s');
    $securityRef = $sample['security_ref'] ?? 'SEC-VERIFIED';

    $photoSrc = $sample['photo_src'] ?? null;
    $qrSrc = $sample['qr_src'] ?? null;

    $isCompact = ($layout === 'compact');
    $cardPadding = $isCompact ? '10px 12px' : '14px 16px';
    $fontSize = $isCompact ? '11px' : '12px';
@endphp

<div class="mcq-hall-ticket" style="border:1.5px solid {{ $primary }};padding:{{ $cardPadding }};font-family:Arial,Helvetica,sans-serif;color:#1e293b;background:#ffffff;width:100%;box-sizing:border-box;font-size:{{ $fontSize }};line-height:1.35;page-break-inside:avoid;break-inside:avoid;margin-bottom:16px;">

    <!-- ==================================
         1. DUAL HEADER: SCHOOL & BOARD
    =================================== -->
    <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid {{ $primary }};padding-bottom:{{ $isCompact ? '6px' : '10px' }};gap:10px;">
        <div style="width:{{ $isCompact ? '55px' : '70px' }};height:{{ $isCompact ? '55px' : '70px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
            @else
                <div style="width:100%;height:100%;border:1px dashed #94a3b8;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;color:#64748b;text-align:center;">LOGO</div>
            @endif
        </div>

        <div style="flex:1;text-align:center;padding:0 6px;">
            <h1 style="margin:0;font-size:{{ $isCompact ? '14px' : '17px' }};font-weight:800;color:{{ $primary }};text-transform:uppercase;letter-spacing:0.02em;">
                {{ $orgName }}
            </h1>
            @if($subHeading)
                <div style="font-size:{{ $isCompact ? '10px' : '11.5px' }};font-weight:600;color:#334155;margin-top:2px;">
                    {{ $subHeading }}
                </div>
            @endif
            @if($subTitle)
                <div style="font-size:{{ $isCompact ? '9px' : '10px' }};color:#64748b;margin-top:1px;">
                    {{ $subTitle }}
                </div>
            @endif
        </div>

        <div style="width:{{ $isCompact ? '55px' : '70px' }};height:{{ $isCompact ? '55px' : '70px' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            @if($boardLogoUrl)
                <img src="{{ $boardLogoUrl }}" alt="Board Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
            @elseif($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
            @else
                <div style="width:100%;height:100%;border:1px dashed #94a3b8;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;color:#64748b;text-align:center;">SEAL</div>
            @endif
        </div>
    </div>

    <!-- ==================================
         2. INVERTED TITLE BAR
    =================================== -->
    <div style="background:{{ $primary }};color:#ffffff;display:flex;align-items:center;justify-content:space-between;padding:{{ $isCompact ? '5px 10px' : '6px 14px' }};margin-top:8px;font-weight:700;font-size:{{ $isCompact ? '11px' : '12.5px' }};text-transform:uppercase;letter-spacing:0.03em;">
        <div style="width:30%;text-align:left;">
            {{ $titleLeft }}
        </div>
        <div style="width:40%;text-align:center;color:#f8fafc;">
            {{ $examTitle }}
        </div>
        <div style="width:30%;text-align:right;color:#e2e8f0;">
            {{ $className }}
        </div>
    </div>

    <!-- ==================================
         3. CANDIDATE PARTICULARS + QR + PHOTO
    =================================== -->
    <div style="display:flex;align-items:flex-start;gap:12px;padding:{{ $isCompact ? '8px 0 6px' : '10px 0 8px' }};border-bottom:1px solid #cbd5e1;">
        <div style="flex:1;">
            <table style="width:100%;border-collapse:collapse;font-size:{{ $isCompact ? '10.5px' : '11.5px' }};">
                @if($showRollNo)
                <tr>
                    <td style="width:{{ $isCompact ? '110px' : '135px' }};padding:2.5px 0;color:#475569;font-weight:600;">Roll / Ticket No</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-size:{{ $isCompact ? '13px' : '15px' }};font-weight:900;color:{{ $accent }};letter-spacing:0.04em;">
                        {{ $examRegNo }}
                    </td>
                </tr>
                @endif
                @if($showSchoolAdmissionNo && filled($secondaryValue))
                <tr>
                    <td style="padding:2.5px 0;color:#475569;font-weight:600;">{{ $secondaryLabel }}</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-weight:700;color:#0f172a;text-transform:uppercase;">{{ $secondaryValue }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:2.5px 0;color:#475569;font-weight:600;">Candidate Name</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-weight:800;color:#0f172a;text-transform:uppercase;">{{ $participantName }}</td>
                </tr>
                <tr>
                    <td style="padding:2.5px 0;color:#475569;font-weight:600;">Class / Section</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-weight:700;color:#0f172a;text-transform:uppercase;">
                        {{ $className }} @if($sectionName) - {{ $sectionName }} @endif
                    </td>
                </tr>
                @if($showSchool && filled($schoolName))
                <tr>
                    <td style="padding:2.5px 0;color:#475569;font-weight:600;">School Name</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-weight:700;color:#0f172a;">{{ $schoolName }}</td>
                </tr>
                @endif
                @if(filled($fatherName))
                <tr>
                    <td style="padding:2.5px 0;color:#475569;font-weight:600;">Father's Name</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-weight:700;color:#0f172a;text-transform:uppercase;">{{ $fatherName }}</td>
                </tr>
                @endif
                @if(filled($motherName))
                <tr>
                    <td style="padding:2.5px 0;color:#475569;font-weight:600;">Mother's Name</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-weight:700;color:#0f172a;text-transform:uppercase;">{{ $motherName }}</td>
                </tr>
                @endif
                @if(filled($academicYear))
                <tr>
                    <td style="padding:2.5px 0;color:#475569;font-weight:600;">Academic Year</td>
                    <td style="width:12px;text-align:center;font-weight:700;color:#64748b;">:</td>
                    <td style="padding:2.5px 0;font-weight:700;color:#0f172a;">{{ $academicYear }}</td>
                </tr>
                @endif
            </table>
        </div>

        @if($showQr)
        <div style="width:{{ $isCompact ? '80px' : '100px' }};text-align:center;flex-shrink:0;">
            @if($qrSrc)
                <img src="{{ $qrSrc }}" alt="QR" style="width:{{ $isCompact ? '75px' : '90px' }};height:{{ $isCompact ? '75px' : '90px' }};object-fit:contain;border:1px solid #cbd5e1;padding:2px;">
            @else
                <div style="width:{{ $isCompact ? '75px' : '90px' }};height:{{ $isCompact ? '75px' : '90px' }};border:1px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:8px;color:#94a3b8;">QR Code</div>
            @endif
            <div style="font-size:8px;color:#64748b;font-weight:700;text-transform:uppercase;margin-top:2px;">Scan to Verify</div>
        </div>
        @endif

        @if($showPhoto)
        <div style="width:{{ $isCompact ? '80px' : '100px' }};text-align:center;flex-shrink:0;">
            @if($photoSrc)
                <img src="{{ $photoSrc }}" alt="Photo" style="width:{{ $isCompact ? '80px' : '95px' }};height:{{ $isCompact ? '95px' : '115px' }};object-fit:cover;border:1.5px solid #334155;">
            @else
                <div style="width:{{ $isCompact ? '80px' : '95px' }};height:{{ $isCompact ? '95px' : '115px' }};border:1.5px dashed #94a3b8;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:8.5px;color:#94a3b8;text-transform:uppercase;text-align:center;line-height:1.2;">
                    Affix Passport<br>Photograph
                </div>
            @endif
        </div>
        @endif
    </div>

    <!-- ==================================
         4. AUDIT / SECURITY METADATA BAR
    =================================== -->
    @if($showAuditBar)
    <div style="display:flex;border:1px solid #334155;margin:7px 0;background:#f8fafc;font-size:9.5px;font-weight:600;">
        <div style="width:50%;padding:3px 8px;border-right:1px solid #334155;color:#334155;">
            <strong>Hall Ticket Downloaded:</strong> {{ $generatedAt }}
        </div>
        <div style="width:50%;padding:3px 8px;color:#334155;">
            <strong>Security Ref / Verification:</strong> {{ $securityRef }}
        </div>
    </div>
    @endif

    <!-- ==================================
         5. EXAM CENTER & SEATING
    =================================== -->
    @if($showCenterBox && ($centerName || $venue || $hallRoom || $seatNo))
    <div style="border:1px solid #334155;display:flex;margin-bottom:7px;">
        <div style="width:{{ $isCompact ? '105px' : '130px' }};background:#f1f5f9;border-right:1px solid #334155;display:flex;align-items:center;justify-content:center;text-align:center;font-weight:700;font-size:{{ $isCompact ? '10px' : '11px' }};color:#0f172a;padding:5px;text-transform:uppercase;">
            Exam Center<br>& Seating
        </div>
        <div style="flex:1;padding:5px 8px;font-size:{{ $isCompact ? '10px' : '11px' }};line-height:1.4;">
            <div style="font-weight:800;color:{{ $primary }};">Center Code: {{ $centerCode }}</div>
            <div style="font-weight:700;color:#0f172a;">{{ $centerName }}</div>
            @if($centerAddress && $centerAddress !== $centerName)
                <div style="color:#475569;">{{ $centerAddress }}</div>
            @endif
            @if($hallRoom || $seatNo)
                <div style="margin-top:3px;font-weight:700;">
                    @if($hallRoom) Hall / Room: <span style="color:{{ $accent }};">{{ $hallRoom }}</span> &nbsp;|&nbsp; @endif
                    @if($seatNo) Seat No: <span style="color:{{ $accent }};">{{ $seatNo }}</span> @endif
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- ==================================
         6. CLASS MCQ EXAM SCHEDULE (PER CLASS, NOT SUBJECTS)
    =================================== -->
    @if($showExamSchedule)
    <table style="width:100%;border-collapse:collapse;margin-bottom:7px;border:1px solid #334155;font-size:{{ $isCompact ? '10px' : '11px' }};">
        <thead>
            <tr style="background:#1e293b;color:#ffffff;text-align:center;font-weight:700;letter-spacing:0.03em;">
                <th colspan="2" style="padding:4px;border:1px solid #334155;font-size:{{ $isCompact ? '10.5px' : '11.5px' }};">
                    MCQ EXAMINATION SCHEDULE & PAPER SPECIFICATIONS
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width:28%;padding:4px 8px;border:1px solid #334155;background:#f8fafc;font-weight:700;color:#334155;">Class Level / Scope</td>
                <td style="padding:4px 8px;border:1px solid #334155;font-weight:700;color:#0f172a;">
                    {{ $className }}
                    <span style="display:inline-block;background:#e0f2fe;color:#0369a1;font-weight:700;padding:1px 6px;border-radius:3px;font-size:9.5px;margin-left:6px;">
                        Single Session Composite Test
                    </span>
                </td>
            </tr>
            <tr>
                <td style="padding:4px 8px;border:1px solid #334155;background:#f8fafc;font-weight:700;color:#334155;">Examination Paper</td>
                <td style="padding:4px 8px;border:1px solid #334155;font-weight:600;color:#0f172a;">
                    {{ $paperName }} @if($examCode) <span style="color:#64748b;">(Code: {{ $examCode }})</span> @endif
                </td>
            </tr>
            <tr>
                <td style="padding:4px 8px;border:1px solid #334155;background:#f8fafc;font-weight:700;color:#334155;">Date of Examination</td>
                <td style="padding:4px 8px;border:1px solid #334155;font-weight:800;color:{{ $primary }};">
                    {{ $examDate }}
                </td>
            </tr>
            <tr>
                <td style="padding:4px 8px;border:1px solid #334155;background:#f8fafc;font-weight:700;color:#334155;">Exam Timings & Duration</td>
                <td style="padding:4px 8px;border:1px solid #334155;font-weight:700;color:#0f172a;">
                    {{ $examTime }} &nbsp; (Duration: {{ $durationMinutes }} Minutes)
                </td>
            </tr>
            <tr>
                <td style="padding:4px 8px;border:1px solid #334155;background:#f8fafc;font-weight:700;color:#334155;">Reporting & Gate Closure</td>
                <td style="padding:4px 8px;border:1px solid #334155;font-weight:600;color:#0f172a;">
                    Reporting Time: <strong style="color:#166534;">{{ $reportTimeLabel ?? 'TBA' }}</strong>
                    &nbsp;|&nbsp;
                    Gate Closure: <strong style="color:{{ $accent }};">{{ $gateClosureLabel ?? 'At Exam Start' }} Strictly</strong>
                </td>
            </tr>
            <tr>
                <td style="padding:4px 8px;border:1px solid #334155;background:#f8fafc;font-weight:700;color:#334155;">Paper Pattern & Mode</td>
                <td style="padding:4px 8px;border:1px solid #334155;font-weight:600;color:#0f172a;">
                    Total Questions: <strong>{{ $totalQuestions }} MCQs</strong> &nbsp;|&nbsp;
                    Max Marks: <strong>{{ $maxMarks }}</strong> &nbsp;|&nbsp;
                    Mode: <strong>{{ $examModeLabel }}</strong>
                </td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- ==================================
         7. MCQ INSTRUCTIONS
    =================================== -->
    @if(!empty($instructions))
    <div style="border:1px solid #cbd5e1;background:#fcfcfc;padding:5px 8px;margin-bottom:7px;">
        <div style="font-size:{{ $isCompact ? '10px' : '11px' }};font-weight:800;color:{{ $accent }};text-transform:uppercase;margin-bottom:3px;letter-spacing:0.02em;">
            {{ $instructionsTitle }}
        </div>
        <ol style="margin:0;padding-left:16px;font-size:{{ $isCompact ? '9px' : '9.5px' }};color:#1e293b;line-height:1.4;">
            @foreach($instructions as $inst)
                <li style="margin-bottom:2px;">{{ $inst }}</li>
            @endforeach
        </ol>
    </div>
    @endif

    <!-- ==================================
         8. TRI-SIGNATORY AUTHENTICATION
    =================================== -->
    @if($showSignature)
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:{{ $isCompact ? '10px' : '16px' }};padding-top:6px;border-top:1px dashed #94a3b8;">
        @if($showCandidateSig)
        <div style="width:31%;text-align:center;">
            <div style="border-top:1.2px solid #334155;margin-top:{{ $isCompact ? '24px' : '30px' }};padding-top:3px;font-size:9.5px;font-weight:700;color:#334155;text-transform:uppercase;">
                Candidate's Signature
            </div>
            <div style="font-size:8px;color:#64748b;">(In presence of Invigilator)</div>
        </div>
        @endif

        @if($showInvigilatorSig)
        <div style="width:31%;text-align:center;">
            <div style="border-top:1.2px solid #334155;margin-top:{{ $isCompact ? '24px' : '30px' }};padding-top:3px;font-size:9.5px;font-weight:700;color:#334155;text-transform:uppercase;">
                Invigilator's Signature
            </div>
            <div style="font-size:8px;color:#64748b;">(Verified Photo & OMR Code)</div>
        </div>
        @endif

        @if($showControllerSig)
        <div style="width:31%;text-align:center;">
            <div style="border-top:1.2px solid #334155;margin-top:{{ $isCompact ? '24px' : '30px' }};padding-top:3px;font-size:9.5px;font-weight:700;color:#334155;text-transform:uppercase;">
                {{ $signatoryTitle }}
            </div>
            <div style="font-size:8px;color:#64748b;">(Official Seal & Authentication)</div>
        </div>
        @endif
    </div>
    @endif

    <!-- ==================================
         9. FOOTER NOTE & DISCLAIMER
    =================================== -->
    @if($footerNote)
        <div style="margin-top:5px;font-size:9px;color:#334155;text-align:center;font-weight:600;">
            {{ $footerNote }}
        </div>
    @endif

    @if($disclaimer)
        <div style="margin-top:4px;font-size:8px;color:#64748b;text-align:center;border-top:1px solid #e2e8f0;padding-top:3px;line-height:1.3;">
            <strong>Disclaimer:</strong> {{ $disclaimer }}
        </div>
    @endif

</div>
