<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Chest Cards — {{ $event->title }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; color: #111827; background: #fff; }
        .toolbar { padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .toolbar button { padding: 8px 14px; border: 1px solid #111827; border-radius: 6px; background: #111827; color: #fff; cursor: pointer; font-weight: 600; }
        .sheet { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10mm; padding: 10mm; page-break-after: always; }
        .card { min-height: 88mm; border: 3px solid #111827; border-radius: 12px; padding: 8mm; display: flex; align-items: center; justify-content: center; text-align: center; }
        .chest { font-size: 130px; line-height: 1; font-weight: 900; letter-spacing: .02em; }
        @media print {
            .toolbar { display: none; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print chest cards</button>
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
