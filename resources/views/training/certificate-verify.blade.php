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
        dl { margin-top: 16px; display: grid; grid-template-columns: 120px 1fr; gap: 8px 12px; font-size: 14px; }
        dt { color: #64748b; }
        a { color: #1e3a8a; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Training certificate</h1>
        <p class="ok">✓ Valid certificate</p>
        <dl>
            <dt>Recipient</dt><dd>{{ $fieldValues['recipient_name'] ?? $registration->teacher?->name }}</dd>
            <dt>Programme</dt><dd>{{ $fieldValues['program_title'] ?? $registration->program?->title }}</dd>
            <dt>School</dt><dd>{{ $fieldValues['school_name'] ?? '' }}</dd>
            <dt>Organizer</dt><dd>{{ $sahodaya->name }}</dd>
            <dt>Days attended</dt><dd>{{ $daysPresent }}</dd>
            <dt>Date(s)</dt><dd>{{ $fieldValues['conducted_on'] ?? '' }}</dd>
            <dt>Verification ID</dt><dd><code>{{ $certificate->verification_uuid }}</code></dd>
        </dl>
        <p style="margin-top:20px;font-size:14px;">
            <a href="{{ route('certificates.print', $certificate->verification_uuid) }}" target="_blank">View certificate ↗</a>
        </p>
    </div>
</body>
</html>
