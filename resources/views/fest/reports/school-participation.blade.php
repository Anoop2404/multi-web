<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>School Participation Counts Report</title>
    <style>
        @page {
            margin: 30px 35px 30px 35px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12.5px;
            color: #1e293b;
            line-height: 1.4;
        }
        h2 {
            text-align: center;
            margin: 12px 0 4px;
            font-size: 17px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.2px;
        }
        .meta {
            text-align: center;
            color: #64748b;
            margin-bottom: 16px;
            font-size: 11.5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 11.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            font-size: 12.5px;
            color: #334155;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2>{{ $event->title }} — School Participation Counts</h2>
    <p class="meta">
        {{ $totals['schools'] }} schools · {{ $totals['active_registrations'] }} active registrations ·
        {{ $totals['unique_students'] }} unique students · Generated on {{ date('d M Y, h:i A') }}
    </p>

    <table>
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="{{ $usesPhases ? 'width: 35%;' : 'width: 47%;' }}">School</th>
                @if($usesPhases)
                    <th style="width: 18%;">Phase</th>
                @endif
                <th style="width: 14%; text-align: center;">Active regs</th>
                <th style="width: 12%; text-align: center;">Items</th>
                <th style="width: 16%; text-align: center;">Unique students</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $idx => $row)
            <tr>
                <td class="text-center" style="color: #94a3b8; font-size: 11px;">{{ $idx + 1 }}</td>
                <td><strong style="color: #0f172a;">{{ $row['school_name'] }}</strong></td>
                @if($usesPhases)
                    <td>{{ $row['phase_name'] }}</td>
                @endif
                <td class="text-center font-bold" style="color: #047857;">{{ $row['active_count'] }}</td>
                <td class="text-center">{{ $row['item_count'] }}</td>
                <td class="text-center font-bold" style="color: #1e1b4b;">{{ $row['unique_student_count'] }}</td>
            </tr>
            @endforeach
            @if(!count($rows))
            <tr>
                <td colspan="{{ $usesPhases ? 6 : 5 }}" class="text-center" style="color: #94a3b8;">No schools have an active registration for this event yet.</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
