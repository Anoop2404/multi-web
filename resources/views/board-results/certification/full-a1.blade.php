<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Full A1 Achievers — Certification</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #0b2558; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; color: #0b2558; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 13.5px; font-weight: bold; color: #555; margin: 5px 0 0 0; }
        .meta { font-size: 11.5px; color: #777; margin-top: 5px; }
        .school-name { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #475569; font-size: 11px; text-transform: uppercase; }
        td { font-size: 11.5px; }
        .ref-box { margin-top: 20px; border: 1px dashed #94a3b8; padding: 10px; font-size: 10.5px; color: #64748b; }
        .signature-section { margin-top: 40px; page-break-inside: avoid; }
        .signature-row { display: table; width: 100%; }
        .signature-col { display: table-cell; width: 50%; vertical-align: top; padding: 0 10px; }
        .sig-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .sig-line { border-bottom: 1.5px solid #334155; min-height: 36px; margin-bottom: 6px; }
        .sig-name { font-size: 12px; font-weight: bold; color: #1e293b; }
        .sig-designation { font-size: 11px; color: #475569; }
        .sig-date { font-size: 11px; color: #64748b; margin-top: 4px; }
        .seal-box { border: 1.5px dashed #94a3b8; height: 80px; text-align: center; padding-top: 28px; font-size: 10px; color: #94a3b8; letter-spacing: 0.3px; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .page-number:before { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <h1 class="title">Full A1 Achievers</h1>
        <h2 class="subtitle">
            Class {{ $boardResult->class }} • {{ $boardResult->examination_type }} • {{ $boardResult->academic_year }}
            @if(!empty($snapshot['stream_label'])) • {{ $snapshot['stream_label'] }} Stream @endif
        </h2>
        <p class="meta">Generated: {{ $generatedAt }} · Full A1 achiever subject marks register.</p>
    </div>

    <div class="school-name">{{ $school->name }}</div>

    @if(empty($snapshot['rows']))
        <p style="text-align:center; color:#94a3b8; padding: 20px 0;">
            No Full A1 achievers were entered for {{ $snapshot['stream_label'] ?? 'this category' }} — this stands as the school's Nil declaration.
        </p>
    @else
        @foreach($snapshot['rows'] as $row)
            <table>
                <thead>
                    <tr><th colspan="2">{{ $row['name'] }} @if(!empty($row['roll_no'])) (Roll {{ $row['roll_no'] }}) @endif</th></tr>
                </thead>
                <tbody>
                    @foreach(($row['subject_marks'] ?? []) as $subject => $marks)
                    <tr><td>{{ $subject }}</td><td>{{ $marks }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

    {{-- Signature Section --}}
    <div class="signature-section">
        <div class="signature-row">
            <div class="signature-col">
                <div class="sig-label">Signature &amp; School Seal</div>
                <div class="seal-box">Affix School Seal Here</div>
            </div>
            <div class="signature-col">
                <div class="sig-label">Authorized Signatory</div>
                <div class="sig-line"></div>
                <div class="sig-name">________________________________</div>
                <div class="sig-designation">Principal / Vice Principal</div>
                <div class="sig-date">Name: ___________________________</div>
                <div class="sig-date">Date: &nbsp;&nbsp;___________________________</div>
            </div>
        </div>
    </div>

    <div class="ref-box">
        Report reference: FULLA1-{{ $report->id }} · Package v{{ $report->package->version ?? '—' }} · Data hash: {{ $report->data_hash }}
        <br>This is a system-generated proof report frozen at the time of generation. Any subsequent data correction invalidates this document.
    </div>

    <div class="footer">
        {{ $school->name }} • Principal Verification — Full A1 Achievers • <span class="page-number"></span>
    </div>
</body>
</html>
