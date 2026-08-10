<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Result Summary & Proof — Certification</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12.5px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #0b2558; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 10px; }
        .title { font-size: 19px; font-weight: bold; color: #0b2558; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 14.5px; font-weight: bold; color: #555; margin: 5px 0 0 0; }
        .meta { font-size: 12px; color: #777; margin-top: 5px; }
        .school-info { text-align: center; margin-bottom: 25px; }
        .school-name { font-size: 17px; font-weight: bold; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #475569; font-size: 12px; text-transform: uppercase; }
        td { font-size: 13.5px; }
        .value { font-weight: bold; color: #0f172a; text-align: right; }
        .ref-box { margin-top: 25px; border: 1px dashed #94a3b8; padding: 10px; font-size: 11px; color: #64748b; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10.5px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .page-number:before { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <h1 class="title">Result Summary &amp; Proof — Principal Verification</h1>
        <h2 class="subtitle">Class {{ $snapshot['class'] ?? $boardResult->class }} • {{ $snapshot['examination_type'] ?? $boardResult->examination_type }} • {{ $snapshot['academic_year'] ?? $boardResult->academic_year }}</h2>
        <p class="meta">Generated: {{ $generatedAt }}</p>
    </div>

    <div class="school-info">
        <div class="school-name">{{ $school->name }}</div>
        @if($school->affiliation_no)
            <div style="font-size: 12px; color: #666; margin-top: 4px;">Affiliation No: {{ $school->affiliation_no }}</div>
        @endif
    </div>

    <table>
        <tbody>
            <tr><th>Total Appeared</th><td class="value">{{ $snapshot['total_appeared'] ?? '—' }}</td></tr>
            <tr><th>Total Passed</th><td class="value">{{ $snapshot['pass_count'] ?? '—' }}</td></tr>
            <tr><th>Pass Percentage</th><td class="value" style="color:#059669;">{{ isset($snapshot['pass_percent']) ? number_format($snapshot['pass_percent'], 2).'%' : '—' }}</td></tr>
            <tr><th>Distinctions</th><td class="value">{{ $snapshot['distinctions'] ?? '—' }}</td></tr>
            <tr><th>First Class</th><td class="value">{{ $snapshot['first_class'] ?? '—' }}</td></tr>
            <tr><th>Highest Mark</th><td class="value">{{ isset($snapshot['highest_mark']) ? number_format($snapshot['highest_mark'], 2) : '—' }}</td></tr>
            <tr><th>Average Mark</th><td class="value">{{ isset($snapshot['average_mark']) ? number_format($snapshot['average_mark'], 2) : '—' }}</td></tr>
        </tbody>
    </table>

    @if(!empty($snapshot['remarks']))
    <div style="margin-top: 20px;">
        <strong style="font-size: 12px; color: #475569;">School Remarks:</strong>
        <p style="font-size: 12px; color: #333; background: #f8fafc; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; margin-top: 5px;">{{ $snapshot['remarks'] }}</p>
    </div>
    @endif

    <div class="ref-box">
        Report reference: SUMMARY-{{ $report->id }} · Package v{{ $report->package->version ?? '—' }} · Data hash: {{ $report->data_hash }}
        <br>This is a system-generated proof report frozen at the time of generation. Any subsequent data correction invalidates this document.
    </div>

    <div class="footer">
        {{ $school->name }} • Principal Verification — Result Summary • <span class="page-number"></span>
    </div>
</body>
</html>
