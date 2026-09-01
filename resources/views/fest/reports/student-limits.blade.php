<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student Item Limits Report</title>
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
        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 16px;
        }
        .summary-cell {
            display: table-cell;
            text-align: center;
            border: 1px solid #e2e8f0;
            padding: 6px 4px;
        }
        .summary-cell .num {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }
        .summary-cell .label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
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
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 12px;
        }
        .student-info {
            display: table;
            width: 100%;
        }
        .student-photo-cell {
            display: table-cell;
            width: 40px;
            vertical-align: middle;
        }
        .student-photo {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
        }
        .avatar-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #4338ca;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            line-height: 32px;
        }
        .student-details-cell {
            display: table-cell;
            vertical-align: middle;
            padding-left: 8px;
        }
        .student-name {
            font-size: 13.5px;
            font-weight: bold;
            color: #0f172a;
        }
        .badges {
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9.5px;
            font-weight: bold;
            margin-right: 4px;
            background-color: #f1f5f9;
            color: #334155;
        }
        .badge-exceeds { background-color: #fee2e2; color: #b91c1c; }
        .badge-flag { background-color: #fee2e2; color: #b91c1c; margin-left: 4px; }
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
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-approved { background-color: #dcfce7; color: #15803d; }
        .status-pending { background-color: #fef3c7; color: #b45309; }
    </style>
</head>
<body>
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2>{{ $event->title }} — Student Item Limits Report</h2>
    <p class="meta">Per-student on-stage, off-stage, individual and group item usage vs limits · Generated on {{ date('d M Y, h:i A') }}</p>

    <div class="summary-row">
        <div class="summary-cell"><div class="num">{{ $summary['total_students'] ?? 0 }}</div><div class="label">Students</div></div>
        <div class="summary-cell"><div class="num">{{ $summary['exceeding_students'] ?? 0 }}</div><div class="label">Exceeding a limit</div></div>
        <div class="summary-cell"><div class="num">{{ $summary['exceeding_on_stage'] ?? 0 }}</div><div class="label">Over on-stage</div></div>
        <div class="summary-cell"><div class="num">{{ $summary['exceeding_off_stage'] ?? 0 }}</div><div class="label">Over off-stage</div></div>
        <div class="summary-cell"><div class="num">{{ $summary['exceeding_individual'] ?? 0 }}</div><div class="label">Over individual</div></div>
        <div class="summary-cell"><div class="num">{{ $summary['exceeding_group'] ?? 0 }}</div><div class="label">Over group</div></div>
    </div>

    @foreach($rows as $idx => $st)
    <div class="student-card">
        <div class="card-header">
            <div class="student-info">
                <div class="student-photo-cell">
                    @if(!empty($st['photo_data_uri']) || !empty($st['photo_url']))
                        <img src="{{ $st['photo_data_uri'] ?? $st['photo_url'] }}" class="student-photo" alt="{{ $st['name'] }}">
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
                        @if($st['exceeds_any'] ?? false)
                            <span class="badge-flag">Exceeds limit</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="badges">
                @foreach(['on_stage' => 'On-stage', 'off_stage' => 'Off-stage', 'individual' => 'Individual', 'group' => 'Group', 'total' => 'Total'] as $key => $label)
                    @php $dim = $st[$key] ?? ['used' => 0, 'limit' => null, 'exceeds' => false]; @endphp
                    <span class="badge {{ ($dim['exceeds'] ?? false) ? 'badge-exceeds' : '' }}">
                        {{ $label }}: {{ $dim['used'] ?? 0 }}{{ isset($dim['limit']) && $dim['limit'] !== null ? ' / '.$dim['limit'] : '' }}
                    </span>
                @endforeach
            </div>
        </div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 40%;">Item Title</th>
                    <th style="width: 26%;">Category</th>
                    <th style="width: 15%; text-align: center;">Type</th>
                    <th style="width: 15%; text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($st['items'] as $itemIdx => $item)
                <tr>
                    <td style="color: #94a3b8; font-size: 10.5px;">{{ $itemIdx + 1 }}</td>
                    <td><strong>{{ $item['item_title'] }}</strong></td>
                    <td>{{ $item['category_label'] ?? '—' }}</td>
                    <td style="text-align: center;">
                        @php
                            $dimensionLabels = ['on_stage' => 'On-stage', 'off_stage' => 'Off-stage', 'group' => 'Group'];
                        @endphp
                        {{ $dimensionLabels[$item['dimension'] ?? null] ?? '—' }}
                    </td>
                    <td style="text-align: center;">
                        <span class="status-badge {{ ($item['status'] ?? '') === 'approved' ? 'status-approved' : 'status-pending' }}">
                            {{ $item['status'] ?? '—' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    @if(empty($rows))
        <p class="meta">No students found.</p>
    @endif
</body>
</html>
