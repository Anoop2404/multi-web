<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>School-Certified Result Package</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #0b2558; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; color: #0b2558; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 13px; font-weight: bold; color: #555; margin: 5px 0 0 0; }
        .meta { font-size: 10px; color: #777; margin-top: 5px; }
        .school-name { font-size: 15px; font-weight: bold; text-align: center; margin-bottom: 4px; }
        .school-meta { font-size: 11px; text-align: center; color: #555; margin-bottom: 18px; }
        h3.section { font-size: 12px; color: #fff; background: #0b2558; padding: 6px 10px; margin: 20px 0 8px; text-transform: uppercase; letter-spacing: .04em; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #475569; font-size: 10px; text-transform: uppercase; }
        td { font-size: 10.5px; }
        .num { text-align: right; }
        .value { font-weight: bold; color: #0f172a; text-align: right; }
        .status-pill { font-size: 9px; text-transform: uppercase; padding: 2px 6px; border-radius: 3px; }
        .status-accepted { background: #dcfce7; color: #166534; }
        .status-other { background: #fef3c7; color: #92400e; }
        .declaration { font-size: 11px; line-height: 1.7; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; margin-top: 10px; }
        .sig-row { display: table; width: 100%; margin-top: 40px; }
        .sig-col { display: table-cell; width: 50%; vertical-align: top; }
        .sig-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; width: 80%; font-size: 10px; }
        .ref-box { margin-top: 20px; border: 1px dashed #94a3b8; padding: 10px; font-size: 10px; color: #64748b; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .page-number:before { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <h1 class="title">School-Certified Result Package</h1>
        <h2 class="subtitle">Class {{ $boardResult->class }} • {{ $boardResult->examination_type }} • {{ $boardResult->academic_year }}</h2>
        <p class="meta">Generated: {{ $generatedAt }} · Package v{{ $package->version }}</p>
    </div>

    <div class="school-name">{{ $school->name }}</div>
    <div class="school-meta">
        @if($school->affiliation_no) Affiliation No: {{ $school->affiliation_no }} @endif
    </div>

    <h3 class="section">1. Result Summary</h3>
    <table>
        <tbody>
            <tr><th>Total Appeared</th><td class="value">{{ $snapshot['summary']['total_appeared'] ?? '—' }}</td>
                <th>Total Passed</th><td class="value">{{ $snapshot['summary']['pass_count'] ?? '—' }}</td></tr>
            <tr><th>Pass Percentage</th><td class="value" style="color:#059669;">{{ isset($snapshot['summary']['pass_percent']) ? number_format($snapshot['summary']['pass_percent'], 2).'%' : '—' }}</td>
                <th>Distinctions</th><td class="value">{{ $snapshot['summary']['distinctions'] ?? '—' }}</td></tr>
            <tr><th>First Class</th><td class="value">{{ $snapshot['summary']['first_class'] ?? '—' }}</td>
                <th>Highest Mark</th><td class="value">{{ isset($snapshot['summary']['highest_mark']) ? number_format($snapshot['summary']['highest_mark'], 2) : '—' }}</td></tr>
            <tr><th>Average Mark</th><td class="value">{{ isset($snapshot['summary']['average_mark']) ? number_format($snapshot['summary']['average_mark'], 2) : '—' }}</td>
                <th>Marks Out Of</th><td class="value">{{ $snapshot['summary']['total_marks'] ?? '—' }}</td></tr>
        </tbody>
    </table>

    <h3 class="section">2. Category-Review &amp; Signed-Proof Checklist</h3>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Status</th>
                <th>Rows</th>
                <th>Signer</th>
                <th>Role</th>
                <th>Signed At</th>
                <th>Accepted At</th>
                <th>Signed-File Hash</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($snapshot['reports'] ?? []) as $r)
            <tr>
                <td>{{ $r['label'] }}</td>
                <td><span class="status-pill {{ $r['status'] === 'accepted' ? 'status-accepted' : 'status-other' }}">{{ $r['status'] }}</span></td>
                <td class="num">{{ $r['row_count'] ?? '—' }}</td>
                <td>{{ $r['signed_by'] ?? '—' }}</td>
                <td>{{ $r['signer_role'] ?? '—' }}</td>
                <td>{{ $r['signed_at'] ? \Illuminate\Support\Carbon::parse($r['signed_at'])->format('d M Y, H:i') : '—' }}</td>
                <td>{{ $r['accepted_at'] ? \Illuminate\Support\Carbon::parse($r['accepted_at'])->format('d M Y, H:i') : '—' }}</td>
                <td style="font-size:8px; word-break: break-all;">{{ $r['signed_pdf_hash'] ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="8">No individual reports recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 class="section">3. Proof-Document &amp; Package Reference</h3>
    <table>
        <tbody>
            <tr><th>Package Version</th><td class="value">v{{ $package->version }}</td>
                <th>Generated</th><td class="value">{{ optional($package->generated_at)->format('d M Y, H:i') ?? '—' }}</td></tr>
            <tr><th colspan="1">Package Data Hash</th><td colspan="3" style="font-size:9px; word-break: break-all;">{{ $package->data_hash }}</td></tr>
        </tbody>
    </table>

    <h3 class="section">4. Declaration</h3>
    <div class="declaration">
        I/We confirm that:
        <br>1. I have checked the figures above against the official board result.
        <br>2. The topper, subject-wise, stream-wise, and Full A1 details listed in the signed reports referenced above are correct.
        <br>3. The uploaded documents bear the authorized signature and school seal.
        <br><br>
        This consolidated certification and every individually signed report referenced in Section 2 together form the school's complete verified submission to Sahodaya for
        Class {{ $boardResult->class }} {{ $boardResult->examination_type }}, {{ $boardResult->academic_year }}.
    </div>

    <div class="sig-row">
        <div class="sig-col">
            <div class="sig-line">
                Signed by: {{ $package->signedBy?->name ?? '_________________________' }}<br>
                Designation: {{ $package->signer_role === 'school_principal' ? 'Principal' : ($package->signer_role === 'school_vice_principal' ? 'Vice Principal' : ($package->signer_role ?? '_________________________')) }}<br>
                Date: {{ optional($package->signed_at)->format('d M Y') ?? '_________________________' }}
            </div>
        </div>
        <div class="sig-col">
            <div class="sig-line">
                School Seal
            </div>
        </div>
    </div>

    <div class="ref-box">
        Consolidated report reference: PKG-{{ $package->id }}-v{{ $package->version }} · Board Result #{{ $boardResult->id }} · Data hash: {{ $package->data_hash }}
        <br>This document is generated from the frozen, hashed data snapshot listed above. Any subsequent correction to the underlying result invalidates this document and requires a new package version with fresh signatures.
    </div>

    <div class="footer">
        {{ $school->name }} • School-Certified Result Package • <span class="page-number"></span>
    </div>
</body>
</html>
