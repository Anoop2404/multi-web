<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Subject-wise Toppers — Certification</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #0b2558; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; color: #0b2558; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 13.5px; font-weight: bold; color: #555; margin: 5px 0 0 0; }
        .meta { font-size: 11.5px; color: #777; margin-top: 5px; }
        .school-name { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 15px; }
        h3.subject { font-size: 12.5px; color: #0b2558; background: #eef2ff; padding: 6px 8px; margin: 16px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #475569; font-size: 11px; text-transform: uppercase; }
        td { font-size: 11.5px; }
        .num { text-align: right; }
        .ref-box { margin-top: 20px; border: 1px dashed #94a3b8; padding: 10px; font-size: 10.5px; color: #64748b; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .page-number:before { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <h1 class="title">Subject-wise Toppers</h1>
        <h2 class="subtitle">Class {{ $boardResult->class }} • {{ $boardResult->examination_type }} • {{ $boardResult->academic_year }}</h2>
        <p class="meta">Generated: {{ $generatedAt }}</p>
    </div>

    <div class="school-name">{{ $school->name }}</div>

    @if(empty($snapshot['subjects']))
        <p style="text-align:center; color:#94a3b8; padding: 20px 0;">No subject-wise toppers were entered — this stands as the school's Nil declaration.</p>
    @else
        @foreach($snapshot['subjects'] as $subject)
            <h3 class="subject">{{ $subject['subject'] }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Roll No.</th>
                        <th>Gender</th>
                        @if((int) $boardResult->class === 12)<th>Stream</th>@endif
                        <th class="num">Marks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subject['rows'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['roll_no'] ?? '—' }}</td>
                        <td style="text-transform:capitalize;">{{ $row['gender'] ?? '—' }}</td>
                        @if((int) $boardResult->class === 12)<td>{{ $row['stream_label'] ?? '—' }}</td>@endif
                        <td class="num">{{ $row['marks'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

    <div class="ref-box">
        Report reference: SUBJECT-{{ $report->id }} · Package v{{ $report->package->version ?? '—' }} · Data hash: {{ $report->data_hash }}
        <br>This is a system-generated proof report frozen at the time of generation. Any subsequent data correction invalidates this document.
    </div>

    <div class="footer">
        {{ $school->name }} • Principal Verification — Subject-wise Toppers • <span class="page-number"></span>
    </div>
</body>
</html>
