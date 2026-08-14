<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Item Registration Counts Report</title>
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
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-indiv { background-color: #e2e8f0; color: #334155; }
        .badge-team { background-color: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2>{{ $event->title }} — Item Registration Counts</h2>
    <p class="meta">Submitted and Approved Registrations · Generated on {{ date('d M Y, h:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 32%;">Item Name</th>
                <th style="width: 16%;">Category / Class</th>
                <th style="width: 10%; text-align: center;">Type</th>
                <th style="width: 9%; text-align: center;">Schools</th>
                <th style="width: 9%; text-align: center;">Total</th>
                <th style="width: 9%; text-align: center;">Approved</th>
                <th style="width: 10%; text-align: center;">Students / Teams</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $idx => $item)
            <tr>
                <td class="text-center" style="color: #94a3b8; font-size: 11px;">{{ $idx + 1 }}</td>
                <td>
                    <strong style="color: #0f172a;">{{ $item->title }}</strong>
                </td>
                <td>{{ strtoupper($item->class_group ?? '—') }}</td>
                <td class="text-center">
                    <span class="badge {{ ($item->participant_type ?? 'individual') === 'individual' ? 'badge-indiv' : 'badge-team' }}">
                        {{ ($item->participant_type ?? 'individual') === 'individual' ? 'Indiv' : 'Team' }}
                    </span>
                </td>
                <td class="text-center font-bold">{{ $item->school_count ?? 0 }}</td>
                <td class="text-center font-bold" style="color: #1e1b4b;">{{ $item->registered_count }}</td>
                <td class="text-center font-bold" style="color: #047857;">{{ $item->approved }}</td>
                <td class="text-center font-bold">
                    @if(($item->participant_type ?? 'individual') === 'individual')
                        {{ $item->participants }} <span style="font-weight: normal; font-size: 11px; color: #64748b;">students</span>
                    @else
                        {{ $item->registered_count }} <span style="font-weight: normal; font-size: 11px; color: #4338ca;">teams</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
