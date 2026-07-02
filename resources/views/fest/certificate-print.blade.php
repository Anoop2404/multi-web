<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate — {{ $student?->name ?? 'Participant' }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { font-family: Georgia, 'Times New Roman', serif; margin: 0; background: #f1f5f9; }
        .cert {
            width: 297mm; min-height: 210mm; margin: 0 auto; background: #fff;
            border: 12px double #b45309; box-sizing: border-box; padding: 2.5rem 3rem;
            position: relative;
        }
        .inner { border: 2px solid #d97706; padding: 2rem 2.5rem; text-align: center; min-height: 160mm; }
        .org { font-size: .85rem; letter-spacing: .2em; text-transform: uppercase; color: #92400e; }
        h1 { font-size: 2.4rem; margin: .75rem 0 .25rem; color: #0f172a; font-weight: normal; }
        .subtitle { color: #64748b; font-size: 1rem; margin-bottom: 2rem; }
        .name { font-size: 2rem; font-weight: bold; color: #1e3a5f; margin: 1rem 0; border-bottom: 1px solid #e2e8f0; display: inline-block; padding: 0 2rem .5rem; }
        .detail { font-size: 1.1rem; color: #334155; line-height: 1.8; margin: 1.5rem 0; }
        .position { font-size: 1.5rem; color: #b45309; font-weight: bold; }
        .meta { position: absolute; bottom: 2rem; left: 3rem; right: 3rem; display: flex; justify-content: space-between; font-size: .75rem; color: #94a3b8; }
        .actions { text-align: center; padding: 1rem; }
        @media print {
            body { background: #fff; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()" style="padding:.5rem 1.25rem;font-size:1rem;cursor:pointer">Print / Save as PDF</button>
    </div>
    <div class="cert">
        <div class="inner">
            <p class="org">{{ ($recordBreak ?? null) ? 'Record Break Achievement' : 'Certificate of Achievement' }}</p>
            <h1>{{ $event?->title ?? 'Kalotsav' }}</h1>
            <p class="subtitle">{{ $item?->title ?? '' }}</p>
            <p class="detail">This is to certify that</p>
            <p class="name">{{ $student?->name ?? 'Participant' }}</p>
            @if($recordBreak)
            <p class="position">{{ $recordBreak->prize_label }}</p>
            <p class="detail">
                Broke the standing record:
                <strong>{{ $recordBreak->previous_value }}</strong> →
                <strong>{{ $recordBreak->new_value }} {{ $recordBreak->record_unit }}</strong>
            </p>
            @elseif($mark?->position)
            <p class="position">Position #{{ $mark->position }}</p>
            @endif
            @if($mark?->grade)
            <p class="detail">Grade: <strong>{{ $mark->grade }}</strong></p>
            @endif
            @if($recordBreak)
            <p class="detail">has achieved this athletic record distinction.</p>
            @else
            <p class="detail">has participated and achieved this distinction.</p>
            @endif
        </div>
        <div class="meta">
            <span>ID: {{ $certificate->verification_uuid }}</span>
            <span>{{ $certificate->generated_at?->format('d M Y') ?? now()->format('d M Y') }}</span>
        </div>
    </div>
</body>
</html>
