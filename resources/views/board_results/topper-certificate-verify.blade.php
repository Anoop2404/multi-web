<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Verify topper certificate</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 3rem auto; padding: 0 1rem; color: #0f172a; }
        .ok { color: #166534; font-weight: 600; }
        .card { border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1.5rem; }
        a { color: #0f3d7a; }
    </style>
</head>
<body>
    <div class="card">
        <p class="ok">Valid certificate</p>
        <h1 style="margin:.5rem 0 1rem;font-size:1.5rem;">{{ $fieldValues['recipient_name'] ?? '—' }}</h1>
        <p>{{ $sahodaya->name ?? '' }} · Class {{ $fieldValues['class'] ?? '—' }} · {{ $fieldValues['academic_year'] ?? '—' }}</p>
        <p>{{ $fieldValues['percentage'] ?? '' }} · Rank {{ $fieldValues['rank'] ?? '—' }}</p>
        <p style="margin-top:1.5rem;"><a href="{{ $printUrl }}">Print certificate</a></p>
        <p style="font-size:.75rem;color:#94a3b8;margin-top:1rem;">ID: {{ $certificate->verification_uuid }}</p>
    </div>
</body>
</html>
