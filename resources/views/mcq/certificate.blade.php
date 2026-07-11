<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Talent Search Certificate — {{ $fields['student_name'] ?? 'Student' }}</title>
    <style>
        body { font-family: Georgia, serif; padding: 40px; color: #111; background: #fff; }
        .cert { max-width: 720px; margin: 0 auto; border: 6px double #1e3a8a; padding: 48px 40px; text-align: center; }
        h1 { font-size: 28px; margin: 0 0 8px; color: #1e3a8a; }
        .body { font-size: 18px; line-height: 1.7; margin: 32px 0; }
        .meta { font-size: 13px; color: #475569; margin-top: 24px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
@if(!empty($isSample))
    <p style="text-align:center;font-family:system-ui,sans-serif;font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fcd34d;padding:10px 16px;margin:16px auto;max-width:720px;border-radius:8px;">
        <strong>Sample certificate</strong> — for client demo only.
    </p>
@endif
@php
    $body = $design['body'] ?? 'This is to certify that {student_name} of {school_name} participated in {exam_title} and achieved grade {grade}.';
    foreach ($fields as $key => $value) {
        $body = str_replace('{'.$key.'}', (string) $value, $body);
    }
@endphp
    <div class="cert">
        <h1>{{ $sahodaya->name ?? 'Sahodaya' }}</h1>
        <p class="text-sm" style="letter-spacing:.2em;text-transform:uppercase;color:#64748b;">Certificate of Achievement</p>
        <p class="body">{{ $body }}</p>
        <p class="meta">
            {{ $fields['exam_title'] ?? '' }}
            @if(!empty($fields['rank'])) · Rank {{ $fields['rank'] }} @endif
            @if(!empty($fields['score'])) · Score {{ $fields['score'] }} @endif
            <br>{{ $fields['certificate_date'] ?? '' }}
        </p>
        <p class="meta" style="font-size:11px;">Verification: {{ $certificate->verification_uuid }}</p>
        <p class="meta" style="font-size:10px;">Verify at: {{ url('/verify/'.$certificate->verification_uuid) }}</p>
    </div>
    <p class="no-print" style="text-align:center;margin-top:16px;"><button onclick="window.print()">Print</button></p>
</body>
</html>
