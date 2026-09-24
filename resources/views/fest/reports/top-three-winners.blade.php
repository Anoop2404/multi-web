<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — Top 3 Winners</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 12mm 16mm 12mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .event-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            margin: 3px 0 2px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            font-size: 10px;
            color: #b45309;
            margin: 0 0 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: bold;
        }
        .summary-bar {
            margin-bottom: 12px;
            padding: 5px 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 9px;
            color: #475569;
        }
        .item-card {
            margin-bottom: 11px;
            border: 1px solid #cbd5e1;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .item-header-table {
            width: 100%;
            border-collapse: collapse;
            background: #1e293b;
            color: #ffffff;
        }
        .item-header-table td {
            padding: 5px 8px;
            border: none;
            vertical-align: middle;
        }
        .item-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #ffffff;
        }
        .item-code {
            background: #334155;
            color: #f8fafc;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 9.5px;
            margin-right: 4px;
        }
        .item-meta {
            text-align: right;
            font-size: 8.5px;
            color: #cbd5e1;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        table.winners-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.winners-table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 5px 8px;
            border-bottom: 1px solid #cbd5e1;
            text-align: left;
        }
        table.winners-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
            vertical-align: middle;
        }
        table.winners-table tr:last-child td {
            border-bottom: none;
        }
        table.winners-table tr:nth-child(even) td {
            background: #fffbeb;
        }
        .center {
            text-align: center;
        }
        .rank-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
        }
        .rank-1 {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .rank-2 {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #94a3b8;
        }
        .rank-3 {
            background: #ffedd5;
            color: #9a3412;
            border: 1px solid #ea580c;
        }
        .name-bold {
            font-weight: bold;
            color: #0f172a;
            font-size: 9.5px;
            line-height: 1.3;
        }
        .team-tag {
            font-size: 8px;
            color: #4f46e5;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-top: 1px;
        }
        .school-text {
            color: #1e293b;
            font-size: 9px;
            font-weight: 500;
        }
        .chest-text {
            font-weight: bold;
            color: #334155;
            font-size: 9.5px;
        }
        .grade-badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 8.5px;
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .grade-dash {
            color: #94a3b8;
            font-size: 9px;
        }
        .empty-state {
            text-align: center;
            padding: 30px 15px;
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            font-size: 11px;
            margin-top: 20px;
        }
        .footer-note {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px solid #cbd5e1;
            font-size: 8px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', [
        'orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'),
        'logoSrc' => $logoSrc ?? null,
        'docTitle' => 'Top 3 Winners',
    ])

    <h1 class="event-title">{{ $event->title }}</h1>
    <div class="subtitle">Top 3 Winners Report — All Items</div>

    @if(count($items))
        <div class="summary-bar">
            <strong>Total Items with Declared Top 3 Winners:</strong> {{ count($items) }}
            &bull; <strong>Scope:</strong> {{ $event->title }}
            &bull; <strong>Ranks:</strong> 1st, 2nd, and 3rd Places Only
        </div>

        @foreach($items as $item)
            <div class="item-card">
                <table class="item-header-table">
                    <tr>
                        <td class="item-title">
                            @if(!empty($item['item_code']))
                                <span class="item-code">[{{ $item['item_code'] }}]</span>
                            @endif
                            {{ $item['title'] }}
                        </td>
                        <td class="item-meta">
                            {{ $item['category_label'] ?? '—' }} &bull; {{ $item['gender_label'] ?? '—' }} &bull; {{ $item['type_label'] ?? '—' }}
                        </td>
                    </tr>
                </table>
                <table class="winners-table">
                    <thead>
                        <tr>
                            <th style="width: 55px;" class="center">Rank</th>
                            <th style="width: 65px;" class="center">Chest #</th>
                            <th style="width: 70px;" class="center">Student ID</th>
                            <th style="width: 38%;">Participant / Team</th>
                            <th>School Name</th>
                            <th style="width: 50px;" class="center">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item['winners'] as $winner)
                            <tr>
                                <td class="center">
                                    <span class="rank-badge rank-{{ $winner['position'] }}">
                                        @if($winner['position'] == 1) 1st
                                        @elseif($winner['position'] == 2) 2nd
                                        @elseif($winner['position'] == 3) 3rd
                                        @else {{ $winner['position'] }}
                                        @endif
                                    </span>
                                </td>
                                <td class="center chest-text">{{ $winner['chest_no'] ?? '—' }}</td>
                                <td class="center chest-text">{{ $winner['student_id'] ?? '—' }}</td>
                                <td>
                                    <div class="name-bold">{{ $winner['name'] }}</div>
                                    @if(!empty($item['is_group']))
                                        <div class="team-tag">Team / Group Entry</div>
                                    @endif
                                </td>
                                <td class="school-text">{{ strtoupper($winner['school'] ?? '') }}</td>
                                <td class="center">
                                    @if(!empty($winner['grade']))
                                        <span class="grade-badge">{{ $winner['grade'] }}</span>
                                    @else
                                        <span class="grade-dash">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <div class="empty-state">
            No 1st, 2nd, or 3rd rank winners recorded yet for any item.
        </div>
    @endif

    <div class="footer-note">
        {{ $orgName ?? ($sahodaya->name ?? 'Sahodaya') }} &bull; {{ $event->title }} &bull; Generated on {{ now()->format('d M Y, h:i A') }}
    </div>
</body>
</html>
