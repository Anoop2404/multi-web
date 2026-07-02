<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Participation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; background: #fff; }
        .page {
            width: 842px; /* A4 landscape width */
            height: 595px;
            margin: 0 auto;
            position: relative;
            border: 12px double #1e3a8a;
            padding: 48px 56px;
            overflow: hidden;
        }
        .corner {
            position: absolute;
            width: 60px;
            height: 60px;
            border-color: #c7a84a;
        }
        .corner-tl { top: 12px; left: 12px; border-top: 4px solid; border-left: 4px solid; }
        .corner-tr { top: 12px; right: 12px; border-top: 4px solid; border-right: 4px solid; }
        .corner-bl { bottom: 12px; left: 12px; border-bottom: 4px solid; border-left: 4px solid; }
        .corner-br { bottom: 12px; right: 12px; border-bottom: 4px solid; border-right: 4px solid; }
        .header { text-align: center; margin-bottom: 12px; }
        .org-name { font-size: 20px; font-weight: 700; color: #1e3a8a; letter-spacing: 1px; text-transform: uppercase; }
        .subtitle { font-size: 11px; color: #475569; margin-top: 2px; }
        .cert-title {
            text-align: center;
            font-size: 38px;
            font-weight: 700;
            color: #1e3a8a;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 18px 0 10px;
            border-top: 2px solid #c7a84a;
            border-bottom: 2px solid #c7a84a;
            padding: 8px 0;
        }
        .body-text { text-align: center; font-size: 14px; color: #334155; line-height: 1.8; }
        .recipient { font-size: 26px; color: #1e3a8a; font-style: italic; border-bottom: 1px solid #94a3b8; display: inline-block; min-width: 320px; padding-bottom: 2px; }
        .program-name { font-size: 16px; font-weight: 700; color: #0f172a; }
        .meta { font-size: 12px; color: #64748b; margin-top: 10px; }
        .footer {
            position: absolute;
            bottom: 48px;
            left: 56px;
            right: 56px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .sign-block { text-align: center; }
        .sign-line { border-top: 1px solid #0f172a; width: 160px; margin: 0 auto 4px; }
        .sign-label { font-size: 11px; color: #475569; }
        .uuid { font-size: 9px; color: #94a3b8; }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>

        <div class="header">
            <p class="org-name">{{ $sahodaya->name }}</p>
            <p class="subtitle">Sahodaya Schools Complex</p>
        </div>

        <div class="cert-title">Certificate of Participation</div>

        <div class="body-text">
            <p>This is to certify that</p>
            <p><span class="recipient">{{ $fieldValues['recipient_name'] ?? $registration->teacher->name }}</span></p>
            <p style="margin-top: 8px;">has successfully participated in the training programme</p>
            <p class="program-name" style="margin-top: 6px;">{{ $fieldValues['program_title'] ?? $registration->program->title }}</p>
            <p class="meta">
                Conducted by {{ $fieldValues['sahodaya_name'] ?? $sahodaya->name }}
                @if(!empty($fieldValues['conducted_on']))
                    &nbsp;·&nbsp; {{ $fieldValues['conducted_on'] }}
                @elseif($registration->program->registration_open)
                    &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($registration->program->registration_open)->format('d M Y') }}
                @endif
                @if(!empty($fieldValues['designation']))
                    <br>Designation: {{ $fieldValues['designation'] }}
                @endif
            </p>
        </div>

        <div class="footer">
            <div class="sign-block">
                <div class="sign-line"></div>
                <p class="sign-label">Programme Coordinator</p>
            </div>
            <div style="text-align:center">
                <p class="uuid">Verification: {{ $certificate->verification_uuid }}</p>
            </div>
            <div class="sign-block">
                <div class="sign-line"></div>
                <p class="sign-label">Principal / HM</p>
            </div>
        </div>
    </div>
    <p style="text-align:center;margin-top:16px;font-family:sans-serif;font-size:13px;">
        <button onclick="window.print()" style="padding:6px 16px;background:#1e3a8a;color:#fff;border:none;border-radius:6px;cursor:pointer;">Print Certificate</button>
    </p>
</body>
</html>
