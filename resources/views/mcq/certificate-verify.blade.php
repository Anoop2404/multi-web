<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate verification</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 560px; margin: 40px auto; padding: 0 16px; color: #1e293b; }
        .card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.25rem; margin-bottom: 8px; }
        .ok { color: #15803d; font-weight: 600; }
        .bad { color: #b91c1c; font-weight: 600; }
        dl { margin-top: 16px; display: grid; grid-template-columns: 120px 1fr; gap: 8px 12px; font-size: 14px; }
        dt { color: #64748b; }
        a { color: #1e3a8a; }
        code { font-size: 0.85rem; word-break: break-all; }
    </style>
</head>
<body>
    <div class="card">
        @if($valid ?? false)
            <h1>Talent Search certificate</h1>
            <p class="ok">✓ Valid certificate</p>
            <dl>
                <dt>Recipient</dt><dd>{{ $recipient }}</dd>
                <dt>Exam</dt><dd>{{ $examTitle }}@if(!empty($examCode)) ({{ $examCode }})@endif</dd>
                <dt>School</dt><dd>{{ $schoolName }}</dd>
                <dt>Organizer</dt><dd>{{ $sahodayaName }}</dd>
                <dt>Issued</dt><dd>{{ $issuedAt }}</dd>
                <dt>Verification ID</dt><dd><code>{{ $uuid }}</code></dd>
            </dl>
            @if(!empty($printUrl))
                <p style="margin-top:20px;font-size:14px;">
                    <a href="{{ $printUrl }}" target="_blank">View certificate ↗</a>
                </p>
            @endif
        @else
            <h1>Certificate verification</h1>
            <p class="bad">✗ Certificate not found</p>
            <p style="font-size:14px;color:#64748b;margin-top:12px;">No Talent Search or programme certificate matches this verification ID.</p>
            <p style="margin-top:16px;font-size:13px;"><code>{{ $uuid }}</code></p>
        @endif
    </div>
</body>
</html>
