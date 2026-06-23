<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate Verification</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 2rem; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2rem; }
        h1 { margin: 0 0 .5rem; font-size: 1.5rem; color: #0f172a; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; padding: .25rem .75rem; border-radius: 999px; font-size: .75rem; font-weight: 700; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: .5rem 1rem; margin-top: 1.5rem; }
        dt { color: #64748b; font-size: .875rem; }
        dd { margin: 0; font-weight: 600; color: #0f172a; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">Verified Certificate</span>
        <h1>{{ $student?->name ?? 'Participant' }}</h1>
        <p style="color:#64748b;margin:.5rem 0 0">{{ $event?->title ?? 'Event' }} · {{ $item?->title ?? '' }}</p>
        <dl>
            @if($mark?->position)
            <dt>Position</dt><dd>#{{ $mark->position }}</dd>
            @endif
            @if($mark?->grade)
            <dt>Grade</dt><dd>{{ $mark->grade }}</dd>
            @endif
            <dt>Certificate ID</dt><dd style="font-family:monospace;font-size:.85rem">{{ $certificate->verification_uuid }}</dd>
        </dl>
    </div>
</body>
</html>
