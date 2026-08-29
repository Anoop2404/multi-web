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
        .badge-result-pending { background-color: #f1f5f9; color: #64748b; }
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
                    </div>
                    <div class="school-name">🏫 {{ $st['school_name'] ?? '—' }}@if(!empty($st['school_code'])) ({{ $st['school_code'] }})@endif · {{ $st['item_count'] }} item(s) registered</div>
                </div>
            </div>
        </div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: {{ ($showChestNo ?? true) ? '19' : '23' }}%;">Item Name</th>
                    <th style="width: 14%;">Category / Head</th>
                    <th style="width: 12%; text-align: center;">Stage / Type</th>
                    @if($showChestNo ?? true)
                    <th style="width: 9%; text-align: center;">Chest No</th>
                    @endif
                    <th style="width: 8%; text-align: center;">Status</th>
                    <th style="width: 8%; text-align: center;">Rank</th>
                    <th style="width: 8%; text-align: center;">Mark</th>
                    <th style="width: 8%; text-align: center;">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($st['items'] as $itemIdx => $item)
                <tr>
                    <td style="color: #94a3b8; font-size: 10.5px;">{{ $itemIdx + 1 }}</td>
                    <td><strong>{{ $item['item_title'] }}</strong></td>
                    <td>
                        @if(!empty($item['category_label']) && !empty($item['head_name']))
                            {{ $item['category_label'] }} · {{ $item['head_name'] }}
                        @elseif(!empty($item['category_label']))
                            {{ $item['category_label'] }}
                        @else
                            {{ $item['head_name'] ?? '—' }}
                        @endif
                    </td>
                    <td style="text-align: center;">
                        @php
                            $stageLabel = !empty($item['stage_type']) ? ($item['stage_type'] === 'on_stage' ? 'On stage' : 'Off stage') : null;
                            $participantLabels = ['individual' => 'Individual', 'group' => 'Group', 'team' => 'Team'];
                            $participantLabel = !empty($item['participant_type']) ? ($participantLabels[$item['participant_type']] ?? $item['participant_type']) : null;
                        @endphp
                        @if($stageLabel && $participantLabel)
                            {{ $stageLabel }} · {{ $participantLabel }}
                        @elseif($stageLabel || $participantLabel)
                            {{ $stageLabel ?? $participantLabel }}
                        @else
                            —
                        @endif
                    </td>
                    @if($showChestNo ?? true)
                    <td style="text-align: center; font-family: monospace; font-weight: bold;">{{ $item['chest_no'] ?? '—' }}</td>
                    @endif
                    <td style="text-align: center;">
                        <span class="badge {{ ($item['status'] ?? '') === 'approved' ? 'badge-approved' : 'badge-pending' }}">
                            {{ $item['status'] ?? '—' }}
                        </span>
                    </td>
                    @if($item['results_published'] ?? false)
                        <td style="text-align: center; font-weight: bold;">{{ $item['position'] ?? '—' }}</td>
                        <td style="text-align: center;">{{ $item['score'] ?? '—' }}</td>
                        <td style="text-align: center; font-weight: bold;">{{ $item['grade'] ?? '—' }}</td>
                    @else
                        <td style="text-align: center;" colspan="3">
                            <span class="badge badge-result-pending">Result Pending</span>
                        </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach
</body>
</html>
