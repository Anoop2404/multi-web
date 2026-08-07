<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>School Topper(s) — Certification</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #0b2558; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 10px; }
        .title { font-size: 17px; font-weight: bold; color: #0b2558; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 13px; font-weight: bold; color: #555; margin: 5px 0 0 0; }
        .meta { font-size: 10px; color: #777; margin-top: 5px; }
        .school-name { font-size: 15px; font-weight: bold; text-align: center; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #475569; font-size: 10px; text-transform: uppercase; }
        td { font-size: 11px; }
        .num { text-align: right; }
        .ref-box { margin-top: 20px; border: 1px dashed #94a3b8; padding: 10px; font-size: 10px; color: #64748b; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .page-number:before { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <h1 class="title">School Topper(s)</h1>
        <h2 class="subtitle">
            Class {{ $boardResult->class }} • {{ $boardResult->examination_type }} • {{ $boardResult->academic_year }}
            @if(!empty($snapshot['stream_label'])) • {{ $snapshot['stream_label'] }} Stream @endif
        </h2>
        <p class="meta">Generated: {{ $generatedAt }}</p>
    </div>

    <div class="school-name">{{ $school->name }}</div>

    @if(empty($snapshot['rows']))
        <p style="text-align:center; color:#94a3b8; padding: 20px 0;">
            No toppers were entered for this category — this stands as the school's Nil declaration for {{ $snapshot['stream_label'] ?? 'this category' }}.
        </p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Name</th>
                    <th>Roll No.</th>
                    <th>Gender</th>
                    <th class="num">Marks</th>
                    <th class="num">Out of</th>
                    <th class="num">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($snapshot['rows'] as $row)
                <tr>
                    <td>{{ $row['rank'] ?? '—' }}</td>
                    <td>{{ $row['name'] }}{{ !empty($row['is_perfect_scorer']) ? ' ⭐' : '' }}</td>
                    <td>{{ $row['roll_no'] ?? '—' }}</td>
                    <td style="text-transform:capitalize;">{{ $row['gender'] ?? '—' }}</td>
                    <td class="num">{{ $row['marks_obtained'] ?? '—' }}</td>
                    <td class="num">{{ $row['total_marks'] ?? '—' }}</td>
                    <td class="num">{{ isset($row['percentage']) ? number_format($row['percentage'], 2) : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="ref-box">
        Report reference: OVERALL-{{ $report->id }} · Package v{{ $report->package->version ?? '—' }} · Data hash: {{ $report->data_hash }}
        <br>This is a system-generated proof report frozen at the time of generation. Any subsequent data correction invalidates this document.
    </div>

    <div class="footer">
        {{ $school->name }} • Principal Verification — School Toppers • <span class="page-number"></span>
    </div>
</body>
</html>
