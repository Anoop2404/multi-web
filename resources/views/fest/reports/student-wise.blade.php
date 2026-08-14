<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student-Wise Participant Report</title>
    <style>
        @page {
            margin: 25px 30px 25px 30px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1e293b;
            line-height: 1.4;
        }
        h2 {
            text-align: center;
            margin: 12px 0 4px;
            font-size: 17px;
            font-weight: bold;
            color: #0f172a;
        }
        .meta {
            text-align: center;
            color: #64748b;
            margin-bottom: 16px;
            font-size: 11.5px;
        }
        .student-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 16px;
            page-break-inside: avoid;
            background-color: #ffffff;
            overflow: hidden;
        }
        .card-header {
            background-color: #f8fafc;
            border-b: 1px solid #e2e8f0;
            padding: 8px 12px;
        }
        .student-info {
            display: table;
            width: 100%;
        }
        .student-photo-cell {
            display: table-cell;
            width: 48px;
            vertical-align: middle;
        }
        .student-photo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
        }
        .avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #4338ca;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            line-height: 40px;
        }
        .student-details-cell {
            display: table-cell;
            vertical-align: middle;
            padding-left: 10px;
        }
        .student-name {
            font-size: 13.5px;
            font-weight: bold;
            color: #0f172a;
        }
        .school-name {
            font-size: 11px;
            color: #475569;
            font-weight: 500;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #cbd5e1;
            padding: 5px 10px;
            font-size: 10.5px;
            text-transform: uppercase;
            text-align: left;
        }
        .items-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 6px 10px;
            font-size: 11.5px;
            color: #334155;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-approved { background-color: #dcfce7; color: #15803d; }
        .badge-pending { background-color: #fef3c7; color: #b45309; }
    </style>
</head>
<body>
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2>{{ $event->title }} — Student-Wise Participant Report</h2>
    <p class="meta">Student Participant Entries & Registered Items · Generated on {{ date('d M Y, h:i A') }}</p>

    @foreach($students as $idx => $st)
    <div class="student-card">
        <div class="card-header">
            <div class="student-info">
                <div class="student-photo-cell">
                    @if(!empty($st['photo_url']))
                        <img src="{{ $st['photo_url'] }}" class="student-photo" alt="{{ $st['name'] }}">
                    @else
                        <div class="avatar-placeholder">{{ strtoupper(substr($st['name'] ?? 'S', 0, 1)) }}</div>
                    @endif
                </div>
                <div class="student-details-cell">
                    <div class="student-name">
                        {{ $idx + 1 }}. {{ $st['name'] }}
                        @if(!empty($st['reg_no']))
                            <span style="font-size: 11px; font-weight: normal; color: #64748b;">({{ $st['reg_no'] }})</span>
                        @endif
                    </div>
                    <div class="school-name">🏫 {{ $st['school_name'] ?? '—' }} · {{ $st['item_count'] }} item(s) registered</div>
                </div>
            </div>
        </div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Item Name</th>
                    <th style="width: 25%;">Category / Head</th>
                    <th style="width: 15%; text-align: center;">Chest No</th>
                    <th style="width: 10%; text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($st['items'] as $itemIdx => $item)
                <tr>
                    <td style="color: #94a3b8; font-size: 10.5px;">{{ $itemIdx + 1 }}</td>
                    <td><strong>{{ $item['item_title'] }}</strong></td>
                    <td>{{ $item['head_name'] ?? '—' }}</td>
                    <td style="text-align: center; font-family: monospace; font-weight: bold;">{{ $item['chest_no'] ?? '—' }}</td>
                    <td style="text-align: center;">
                        <span class="badge {{ ($item['status'] ?? '') === 'approved' ? 'badge-approved' : 'badge-pending' }}">
                            {{ $item['status'] ?? '—' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach
</body>
</html>
