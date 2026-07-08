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
        .toolbar button { padding: 8px 14px; border: 1px solid #111827; border-radius: 6px; background: #111827; color: #fff; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10mm; padding: 10mm; page-break-after: always; }
        .card { min-height: 88mm; border: 3px solid #111827; border-radius: 12px; padding: 8mm; display: flex; flex-direction: column; justify-content: space-between; }
        .event { font-size: 12px; text-transform: uppercase; letter-spacing: .12em; font-weight: 700; color: #475569; }
        .chest { font-size: 78px; line-height: .95; font-weight: 900; text-align: center; letter-spacing: .04em; }
        .name { font-size: 22px; font-weight: 800; text-align: center; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; }
        .label { color: #64748b; text-transform: uppercase; font-size: 10px; letter-spacing: .08em; }
        .value { font-weight: 700; }
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
                    <div>
                        <div class="event">{{ $event->title }}@if(!empty($item)) · {{ $item->title }}@endif</div>
                        <div class="chest">{{ $row['chest_no'] }}</div>
                        <div class="name">{{ $row['name'] }}</div>
                    </div>
                    <div class="meta">
                        <div>
                            <div class="label">School</div>
                            <div class="value">{{ $row['school'] }}</div>
                        </div>
                        <div>
                            <div class="label">Item</div>
                            <div class="value">{{ $row['item'] }}</div>
                        </div>
                        <div>
                            <div class="label">Fest ID</div>
                            <div class="value">{{ $row['fest_id'] ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="label">Item Reg</div>
                            <div class="value">{{ $row['item_reg'] ?? '—' }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
    @endforeach
</body>
</html>
