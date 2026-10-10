<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $event->title }} — List of Trophies</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
        .header { text-align: center; margin-bottom: 12px; }
        .event-title { font-size: 15px; font-weight: bold; color: #0f172a; margin: 2px 0; text-transform: uppercase; }
        .doc-title { font-size: 13px; font-weight: bold; color: #1d3557; margin: 2px 0 6px; letter-spacing: 0.5px; }
        .meta-line { font-size: 9px; color: #64748b; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #1d3557; color: #ffffff; padding: 6px 8px; font-size: 9px; font-weight: bold; text-align: left; border: 1px solid #1d3557; }
        td { border: 1px solid #000; padding: 5px 8px; font-size: 9.5px; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        
        .col-no { width: 32px; text-align: center; font-weight: bold; color: #1e293b; }
        .col-title { width: 220px; font-weight: 500; }
        .col-badge { display: inline-block; font-size: 8px; font-weight: bold; padding: 1px 4px; border-radius: 3px; background: #e0e7ff; color: #3730a3; text-transform: uppercase; }
        .badge-rolling { background: #fef3c7; color: #92400e; }
        .col-winner { font-weight: bold; color: #0f172a; }
        .col-details { font-size: 8.5px; color: #475569; }
        .col-sign { width: 60px; text-align: center; }
        .checkbox-box { display: inline-block; width: 14px; height: 14px; border: 1px solid #000; }
        .group-heading td { background: #e8eef5; color: #1d3557; font-weight: bold; padding: 8px; }
        .group-heading { page-break-after: avoid; }
        
        .empty-winner { color: #94a3b8; font-style: italic; font-weight: normal; }
        
        .stats-box { margin-top: 14px; padding: 8px 12px; background: #f1f5f9; border-radius: 4px; border: 1px solid #000; font-size: 9px; }
        
        .signatures { margin-top: 40px; width: 100%; page-break-inside: avoid; }
        .sig-col { width: 33.33%; text-align: center; font-size: 9px; font-weight: bold; color: #334155; }
        .sig-line { width: 120px; border-top: 1px solid #000; margin: 0 auto 4px; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => now()])
    @include('partials.pdf-branding-header', ['orgName' => $sahodaya->name ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])

    <div class="header">
        <div class="event-title">{{ $event->title }}</div>
        <div class="doc-title">LIST OF TROPHIES &amp; VALEDICTORY DISTRIBUTION SHEET</div>
        <div class="meta-line">
            {{ $cumulative ? 'Combined Hub Standing · ' : '' }}
            Generated on {{ now()->format('d M Y, h:i A') }} · Total Trophies: {{ count($rows) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-no">#</th>
                <th>Trophy Description</th>
                <th style="width: 75px;">Type</th>
                <th>Recipient / Winner</th>
                <th style="width: 100px;">Score / Points</th>
                <th class="col-sign">Handover</th>
            </tr>
        </thead>
        <tbody>
            @php $previousGroupKey = null; @endphp
            @forelse($rows as $r)
                @php
                    $t = $r['trophy'];
                    $w = $r['winner'];
                    $winners = $w['joint_winners'] ?? [$w];
                    $itemIds = $t['item_ids'] ?? [];
                    sort($itemIds);
                    $groupKey = json_encode([$t['trophy_type'], $t['category_key'] ?? '', $t['gender'] ?? '',
                        $t['item_id'] ?: ($t['item_name_pattern'] ?? ''), $t['item_group_name'] ?? '', $itemIds]);
                    $typeLabels = ['overall' => 'Overall championship', 'category' => 'Category championship',
                        'item' => 'Single item winners', 'item_group' => 'Item group / cluster',
                        'individual_championship' => 'Individual championship'];
                    $groupName = implode(' · ', array_filter([
                        $t['item_group_name'] ?: ($t['item_name'] ?: ($t['item_name_pattern'] ?: ($typeLabels[$t['trophy_type']] ?? $t['trophy_type']))),
                        $t['category_label'] ?? $t['category_key'] ?? null, $t['gender'] ?? null,
                    ]));
                @endphp
                @if($groupKey !== $previousGroupKey)
                    <tr class="group-heading"><td colspan="6">{{ $groupName }}</td></tr>
                    @php $previousGroupKey = $groupKey; @endphp
                @endif
                @foreach($winners as $w)
                <tr>
                    <td class="col-no">{{ $t['trophy_no'] }}</td>
                    <td class="col-title">
                        <div>{{ $t['title'] }}</div>
                        @if(!($t['is_active'] ?? true))
                            <div style="font-size:8px;color:#92400e;">Disabled — admin preview</div>
                        @endif
                        @if(count($winners) > 1)
                            <div style="font-size:8px;color:#92400e;">Joint winner</div>
                        @endif
                        @if($t['is_rolling'])
                            <span class="col-badge badge-rolling">Ever-rolling</span>
                        @endif
                        @if($t['notes'])
                            <div style="font-size: 8px; color: #64748b; margin-top: 2px;">{{ $t['notes'] }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="col-badge">{{ strtoupper(str_replace('_', ' ', $t['trophy_type'])) }}</span>
                    </td>
                    <td class="col-winner">
                        @if($w && !empty($w['name']))
                            <div>{{ strtoupper($w['name']) }}</div>
                            @if(!empty($w['team_members']))
                                <div style="font-size:8px;font-weight:normal;margin-top:3px;">{{ implode(', ', $w['team_members']) }}</div>
                            @endif
                            @if(!empty($w['chest_no']))
                                <div style="font-size: 8px; color: #64748b; font-weight: normal;">Chest No: {{ $w['chest_no'] }}</div>
                            @endif
                        @else
                            <span class="empty-winner">Awaiting results</span>
                        @endif
                    </td>
                    <td class="col-details">
                        @if($w && !empty($w['detail']))
                            {{ $w['detail'] }}
                        @elseif($w && isset($w['points']))
                            {{ $w['points'] }} pts
                        @else
                            —
                        @endif
                    </td>
                    <td class="col-sign">
                        <div class="checkbox-box"></div>
                    </td>
                </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #64748b;">
                        No trophies configured in this template yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td class="sig-col" style="border:none;">
                <div class="sig-line"></div>
                General Convener
            </td>
            <td class="sig-col" style="border:none;">
                <div class="sig-line"></div>
                General Secretary
            </td>
            <td class="sig-col" style="border:none;">
                <div class="sig-line"></div>
                President / Chairperson
            </td>
        </tr>
    </table>
</body>
</html>
