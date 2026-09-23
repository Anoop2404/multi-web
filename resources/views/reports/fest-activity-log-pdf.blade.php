<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $event->title }} — Activity Log</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 8mm 12mm 8mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            background: #ffffff;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border-bottom: 2px solid #0f3d7a;
            padding-bottom: 6px;
        }
        .org-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f3d7a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .event-title {
            font-size: 12px;
            font-weight: bold;
            color: #334155;
            margin-top: 2px;
        }
        .doc-badge {
            display: inline-block;
            background: #0f3d7a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 4px;
        }
        .meta-bar {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-size: 8.5px;
            color: #475569;
        }
        .meta-bar table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-bar td {
            vertical-align: middle;
            padding: 2px 4px;
        }
        .meta-label {
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            font-size: 8px;
        }
        .filter-tag {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 8px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.data-table th {
            background: #0f3d7a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 5px 6px;
            border: 1px solid #0f3d7a;
            text-align: left;
        }
        table.data-table td {
            padding: 4px 6px;
            font-size: 8.5px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            line-height: 1.35;
        }
        table.data-table tr.even td {
            background: #f8fafc;
        }
        table.data-table tr {
            page-break-inside: avoid;
        }
        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-page {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .badge-chest {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .badge-item {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .badge-school {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .badge-actor-sahodaya {
            background: #eef2ff;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }
        .badge-actor-school {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }
        .sub-text {
            color: #64748b;
            font-size: 7.5px;
            margin-top: 1px;
        }
        .footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
            font-size: 8px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <div class="org-name">{{ $sahodaya->name ?? 'Sahodaya Schools Complex' }}</div>
                <div class="event-title">{{ $event->title }} &mdash; Activity Audit Log</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div class="doc-badge">Activity Log Report</div>
                <div class="sub-text" style="margin-top: 3px;">Generated: {{ $generatedAt }}</div>
            </td>
        </tr>
    </table>

    <!-- Filters Summary Bar -->
    <div class="meta-bar">
        <table>
            <tr>
                <td style="width: 25%;">
                    <span class="meta-label">Total Logs:</span> <strong>{{ count($logs) }}</strong>
                </td>
                <td style="width: 25%;">
                    <span class="meta-label">Module/Page:</span>
                    @if(!empty($filters['page_label']))
                        <span class="filter-tag">{{ $filters['page_label'] }}</span>
                    @else
                        All Pages
                    @endif
                </td>
                <td style="width: 25%;">
                    <span class="meta-label">Item / Category:</span>
                    @if(!empty($filters['item_title']))
                        <span class="filter-tag">{{ $filters['item_title'] }}</span>
                    @else
                        All Items
                    @endif
                </td>
                <td style="width: 25%;">
                    <span class="meta-label">School:</span>
                    @if(!empty($filters['school_name']))
                        <span class="filter-tag">{{ $filters['school_name'] }}</span>
                    @else
                        All Schools
                    @endif
                </td>
            </tr>
            @if(!empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['search']))
            <tr>
                @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                <td colspan="2">
                    <span class="meta-label">Date Range:</span>
                    <span class="filter-tag">{{ $filters['date_from'] ?: 'Start' }} &rarr; {{ $filters['date_to'] ?: 'Now' }}</span>
                </td>
                @endif
                @if(!empty($filters['search']))
                <td colspan="2">
                    <span class="meta-label">Search Query:</span>
                    <span class="filter-tag">"{{ $filters['search'] }}"</span>
                </td>
                @endif
            </tr>
            @endif
        </table>
    </div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">#</th>
                <th style="width: 80px;">When</th>
                <th style="width: 75px;">Page</th>
                <th>Action & Details</th>
                <th style="width: 140px;">School & Participant</th>
                <th style="width: 110px;">User & IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $idx => $log)
                <tr class="{{ $idx % 2 === 1 ? 'even' : '' }}">
                    <td style="text-align: center; color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <div style="font-weight: bold; color: #1e293b;">
                            {{ !empty($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('d M Y') : '' }}
                        </div>
                        <div class="sub-text" style="font-family: monospace;">
                            {{ !empty($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('h:i:s A') : '' }}
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-page">{{ $log['page_label'] ?? $log['page'] ?? 'General' }}</span>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #0f172a;">{{ $log['description'] ?? $log['action'] }}</div>
                        <div style="margin-top: 2px;">
                            @if(!empty($log['item_title']))
                                <span class="badge badge-item">📌 {{ $log['item_title'] }}</span>
                            @endif
                            @if(!empty($log['item_category']))
                                <span class="badge badge-item">📂 {{ $log['item_category'] }}</span>
                            @endif
                            @if(!empty($log['chest_no']))
                                <span class="badge badge-chest">🏷️ Chest #{{ $log['chest_no'] }}</span>
                            @endif
                            @if(!empty($log['properties']['status']) && $log['properties']['status'] === 'absent')
                                <span class="badge" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca;">ABSENT</span>
                            @elseif(!empty($log['properties']['status']) && $log['properties']['status'] === 'present')
                                <span class="badge" style="background:#dcfce7; color:#166534; border:1px solid #bbf7d0;">PRESENT</span>
                            @endif
                            @if(isset($log['properties']['score']) && $log['properties']['score'] !== null)
                                <span class="badge" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a;">Score: {{ $log['properties']['score'] }}</span>
                            @endif
                            @if(!empty($log['properties']['position']))
                                <span class="badge" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a;">Rank #{{ $log['properties']['position'] }}</span>
                            @endif
                        </div>
                        @if(!empty($log['reason']))
                            <div class="sub-text" style="color: #b45309; font-style: italic; margin-top: 2px;">
                                Reason: {{ $log['reason'] }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if(!empty($log['school']))
                            <div style="font-weight: 600; color: #1e3a8a;">{{ $log['school'] }}</div>
                        @endif
                        @if(!empty($log['participant']))
                            <div class="sub-text" style="color: #475569;">👤 {{ $log['participant'] }}</div>
                        @endif
                        @if(!empty($log['reg_no']))
                            <div class="sub-text" style="font-family: monospace;">🆔 {{ $log['reg_no'] }}</div>
                        @endif
                        @if(empty($log['school']) && empty($log['participant']) && empty($log['reg_no']))
                            <span class="sub-text">&mdash;</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #334155;">{{ $log['user']['name'] ?? 'System' }}</div>
                        @if(!empty($log['actor_type']))
                            <span class="badge {{ $log['actor_type'] === 'School' ? 'badge-actor-school' : 'badge-actor-sahodaya' }}">
                                {{ $log['actor_type'] }}
                            </span>
                        @endif
                        @if(!empty($log['ip_address']))
                            <div class="sub-text" style="font-family: monospace;">🌐 {{ $log['ip_address'] }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #94a3b8;">
                        No activity logs found matching the selected filter criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $event->title }} &mdash; Activity Log &bull; Page 1 of 1 &bull; TrueCampus ERP
    </div>

</body>
</html>
