<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Unique Participant Count Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 24px 30px 24px 30px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11.5px;
            color: #1e293b;
            line-height: 1.35;
        }
        h2 {
            text-align: center;
            margin: 10px 0 3px;
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.2px;
        }
        .meta {
            text-align: center;
            color: #64748b;
            margin-bottom: 14px;
            font-size: 10.5px;
        }
        .summary-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-align: center;
            vertical-align: middle;
        }
        .summary-val {
            font-size: 14px;
            font-weight: bold;
            color: #0f3d7a;
        }
        .summary-lbl {
            font-size: 9.5px;
            color: #64748b;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        th {
            background-color: #0f3d7a;
            color: #ffffff;
            border: 1px solid #0f3d7a;
            padding: 6px 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-align: center;
        }
        th.school-th {
            text-align: left;
        }
        td {
            border: 1px solid #e2e8f0;
            padding: 5px 8px;
            font-size: 11px;
            color: #334155;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .total-row td {
            background-color: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            border-top: 2px solid #94a3b8;
        }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2>{{ $event->title }} — Unique Participant Count Report</h2>
    <p class="meta">
        Category-wise Class Breakdown &amp; School-wise Participation Summary · Generated on {{ date('d M Y, h:i A') }}
    </p>

    <table class="summary-bar">
        <tr>
            <td class="summary-box">
                <div class="summary-val">{{ $totals['total_schools'] }}</div>
                <div class="summary-lbl">Participating Schools</div>
            </td>
            <td class="summary-box">
                <div class="summary-val">{{ $totals['total_unique_participants'] }}</div>
                <div class="summary-lbl">Total Unique Students</div>
            </td>
            <td class="summary-box">
                <div class="summary-val">{{ $totals['total_registrations'] }}</div>
                <div class="summary-lbl">Total Registrations</div>
            </td>
            @foreach($categories as $cat)
            <td class="summary-box">
                <div class="summary-val" style="color: #047857;">{{ $totals['category_counts'][$cat['key']] ?? 0 }}</div>
                <div class="summary-lbl">{{ $cat['label'] }}</div>
            </td>
            @endforeach
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th class="school-th" style="width: 30%;">School</th>
                @foreach($categories as $cat)
                    <th>{{ $cat['label'] }}</th>
                @endforeach
                <th style="width: 10%;">Unique Students</th>
                <th style="width: 10%;">Registrations</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $idx => $row)
            <tr>
                <td class="text-center" style="color: #94a3b8; font-size: 10px;">{{ $idx + 1 }}</td>
                <td>
                    <strong style="color: #0f172a;">{{ $row['school_name'] }}</strong>
                    @if(!empty($row['school_code']))
                        <span style="font-size: 9.5px; color: #64748b;">({{ $row['school_code'] }})</span>
                    @endif
                </td>
                @foreach($categories as $cat)
                    @php $cnt = $row['category_counts'][$cat['key']] ?? 0; @endphp
                    <td class="text-center {{ $cnt > 0 ? 'font-bold' : '' }}" style="{{ $cnt > 0 ? 'color: #047857;' : 'color: #cbd5e1;' }}">
                        {{ $cnt }}
                    </td>
                @endforeach
                <td class="text-center font-bold" style="color: #0f3d7a;">{{ $row['total_unique_participants'] }}</td>
                <td class="text-center font-bold" style="color: #475569;">{{ $row['total_registrations'] }}</td>
            </tr>
            @endforeach
            @if(!count($rows))
            <tr>
                <td colspan="{{ count($categories) + 4 }}" class="text-center" style="color: #94a3b8; padding: 18px;">
                    No approved participants found for this event yet.
                </td>
            </tr>
            @endif
        </tbody>
        @if(count($rows))
        <tfoot>
            <tr class="total-row">
                <td class="text-center">Σ</td>
                <td>TOTALS ({{ count($rows) }} Schools)</td>
                @foreach($categories as $cat)
                    <td class="text-center font-bold" style="color: #047857;">
                        {{ $totals['category_counts'][$cat['key']] ?? 0 }}
                    </td>
                @endforeach
                <td class="text-center font-bold" style="color: #0f3d7a;">{{ $totals['total_unique_participants'] }}</td>
                <td class="text-center font-bold" style="color: #1e293b;">{{ $totals['total_registrations'] }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
