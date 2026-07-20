<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mark Entry Sheet — {{ $event->title }}</title>
    <style>
        @page { margin: 16px 20px; size: landscape; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: avoid; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 15px; font-weight: bold; color: #0f172a; text-transform: uppercase; margin: 0; }
        .subtitle { font-size: 12px; font-weight: bold; color: #475569; margin-top: 2px; }
        .meta { margin-top: 6px; font-size: 10px; color: #475569; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #0f172a; color: #ffffff; font-size: 10px; font-weight: bold; text-transform: uppercase; text-align: left; padding: 6px 8px; border: 1px solid #0f172a; }
        .table td { border: 1px solid #cbd5e1; padding: 9px 8px; font-size: 11px; height: 26px; }
        .table tr:nth-child(even) { background-color: #f8fafc; }
        .center { text-align: center; }
        .sign-box { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        .sign-col { float: left; width: 33%; font-size: 10px; font-weight: bold; color: #475569; }
        .clear { clear: both; }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        <div class="sheet">
            <div class="header">
                <table style="width:100%;border-collapse:collapse;margin-bottom:4px;">
                    <tr>
                        @if(!empty($logoSrc))
                            <td style="width:44px;vertical-align:middle;padding:0 8px 0 0;">
                                <img src="{{ $logoSrc }}" alt="" style="width:36px;height:36px;object-fit:contain;">
                            </td>
                        @endif
                        <td style="vertical-align:middle;">
                            <div class="title">{{ $sahodaya->name ?? 'SAHODAYA SCHOOLS COMPLEX' }}</div>
                        </td>
                    </tr>
                </table>
                <div class="subtitle">
                    {{ $event->title }} — MARK ENTRY & SCORE EVALUATION SHEET
                    @if(!empty($sheet['sheet_label']))
                        &nbsp;·&nbsp; {{ $sheet['sheet_label'] }}
                    @endif
                </div>
                <div class="meta">
                    <strong>ITEM:</strong> {{ $sheet['item']->item_code ? "[{$sheet['item']->item_code}] " : '' }}{{ $sheet['item']->title }}
                    &nbsp;|&nbsp; <strong>TOTAL PARTICIPANTS:</strong> {{ count($sheet['rows']) }}
                </div>
            </div>

            @if(!empty($sheet['is_sum_sheet']))
                {{-- Consolidated sheet: one column per judge (their paper subtotal) + Grand Total --}}
                <table class="table">
                    <thead>
                        <tr>
                            <th class="center" style="width: 40px;">SL NO</th>
                            <th style="width: 90px;">CHEST NO.</th>
                            @for($j = 1; $j <= $sheet['judge_count']; $j++)
                                <th>JUDGE {{ $j }}</th>
                            @endfor
                            <th style="width: 100px;">GRAND TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sheet['rows'] as $idx => $row)
                            <tr>
                                <td class="center" style="color: #64748b; font-weight: bold;">{{ $idx + 1 }}</td>
                                <td style="font-weight: bold; font-family: monospace; font-size: 12px; color: #0f172a;">
                                    {{ $row['chest_no'] ? '#'.$row['chest_no'] : '—' }}
                                </td>
                                @for($j = 1; $j <= $sheet['judge_count']; $j++)
                                    <td></td>
                                @endfor
                                <td></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + $sheet['judge_count'] }}" class="center" style="padding: 16px; color: #64748b;">
                                    No approved registrations for this item.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th class="center" style="width: 40px;">SL NO</th>
                            <th style="width: 90px;">CHEST NO.</th>
                            @if($sheet['criteria']->isNotEmpty())
                                @foreach($sheet['criteria'] as $c)
                                    <th>{{ $c->label }}<br><small>/ {{ rtrim(rtrim(number_format($c->max_score, 2), '0'), '.') }}</small></th>
                                @endforeach
                                <th style="width: 90px;">TOTAL</th>
                            @else
                                <th>MARKS / SCORE</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sheet['rows'] as $idx => $row)
                            <tr>
                                <td class="center" style="color: #64748b; font-weight: bold;">{{ $idx + 1 }}</td>
                                <td style="font-weight: bold; font-family: monospace; font-size: 12px; color: #0f172a;">
                                    {{ $row['chest_no'] ? '#'.$row['chest_no'] : '—' }}
                                </td>
                                @if($sheet['criteria']->isNotEmpty())
                                    @foreach($sheet['criteria'] as $c)
                                        <td></td>
                                    @endforeach
                                    <td></td>
                                @else
                                    <td></td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + ($sheet['criteria']->isNotEmpty() ? $sheet['criteria']->count() + 1 : 1) }}" class="center" style="padding: 16px; color: #64748b;">
                                    No approved registrations for this item.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif

            <div class="sign-box">
                <div class="sign-col">Judge 1 Signature: __________________</div>
                <div class="sign-col">Judge 2 Signature: __________________</div>
                <div class="sign-col">Convener Signature: __________________</div>
                <div class="clear"></div>
            </div>
        </div>
    @endforeach
</body>
</html>
