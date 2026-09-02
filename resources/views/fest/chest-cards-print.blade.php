<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif — Chest Cards</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; color: #111827; background: #fff; }
        .sheet { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10mm; padding: 10mm; page-break-after: always; }
        .card { min-height: 80mm; border: 3px solid #111827; border-radius: 12px; padding: 8mm; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .chest { font-size: 120px; line-height: 1; font-weight: 900; letter-spacing: .02em; }
    </style>
</head>
<body>
    <div style="padding: 10mm 10mm 0;">
        @include('partials.pdf-branding-header', [
            'orgName' => $orgName ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'Chest Cards'.(!empty($item) ? ' — '.$item->title : ''),
        ])
    </div>

    @foreach($rows->chunk(4) as $chunk)
        <section class="sheet">
            @foreach($chunk as $row)
                <article class="card">
                    <div class="chest">{{ $row['chest_no'] }}</div>
                </article>
            @endforeach
        </section>
    @endforeach
</body>
</html>
