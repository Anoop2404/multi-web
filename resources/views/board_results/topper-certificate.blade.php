<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Congratulations — {{ $fieldValues['recipient_name'] ?? 'Topper' }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { font-family: Georgia, 'Times New Roman', serif; margin: 0; background: #f1f5f9; }
        .cert {
            width: 297mm; min-height: 210mm; margin: 0 auto; background: #fff;
            border: 12px double #0f3d7a; box-sizing: border-box; padding: 2.5rem 3rem;
            position: relative;
        }
        .inner { border: 2px solid #1e5a9c; padding: 2rem 2.5rem; text-align: center; min-height: 160mm; }
        .org { font-size: .85rem; letter-spacing: .2em; text-transform: uppercase; color: #0f3d7a; }
        h1 { font-size: 2.2rem; margin: .75rem 0 .25rem; color: #0f172a; font-weight: normal; }
        .name { font-size: 2rem; font-weight: bold; color: #1e3a5f; margin: 1rem 0; border-bottom: 1px solid #e2e8f0; display: inline-block; padding: 0 2rem .5rem; }
        .detail { font-size: 1.05rem; color: #334155; line-height: 1.8; margin: 1.25rem auto; max-width: 40rem; text-align: left; }
        .meta { position: absolute; bottom: 2rem; left: 3rem; right: 3rem; display: flex; justify-content: space-between; font-size: .75rem; color: #94a3b8; }
        .actions { text-align: center; padding: 1rem; }
        @media print { body { background: #fff; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()" style="padding:.5rem 1.25rem;font-size:1rem;cursor:pointer">Print / Save as PDF</button>
    </div>
    <div class="cert">
        <div class="inner">
            <p class="org">{{ $sahodaya->name ?? 'Sahodaya' }}</p>
            <h1>{{ $template->title ?? 'Certificate of Congratulations' }}</h1>
            <p class="name">{{ $fieldValues['recipient_name'] ?? '—' }}</p>
            <div class="detail">{!! $bodyHtml !!}</div>
        </div>
        <div class="meta">
            <span>ID: {{ $certificate->verification_uuid }}</span>
            <span>{{ $certificate->generated_at?->format('d M Y') ?? now()->format('d M Y') }}</span>
        </div>
    </div>
</body>
</html>
