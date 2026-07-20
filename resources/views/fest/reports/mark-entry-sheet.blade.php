<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mark Entry Sheet — {{ $event->title }}</title>
    <style>
        @page { margin: 18px 24px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: avoid; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 15px; font-weight: bold; color: #0f172a; text-transform: uppercase; margin: 0; }
        .subtitle { font-size: 12px; font-weight: bold; color: #475569; margin-top: 2px; }
        .meta { margin-top: 6px; font-size: 10px; color: #475569; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #0f172a; color: #ffffff; font-size: 10px; font-weight: bold; text-transform: uppercase; text-align: left; padding: 6px 8px; border: 1px solid #0f172a; }
        .table td { border: 1px solid #cbd5e1; padding: 7px 8px; font-size: 11px; }
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
                <div class="title">{{ $sahodaya->name ?? 'SAHODAYA SCHOOLS COMPLEX' }}</div>
                <div class="subtitle">{{ $event->title }} — MARK ENTRY & SCORE EVALUATION SHEET</div>
                <div class="meta">
                    <strong>ITEM:</strong> {{ $sheet['item']->code ? "[{$sheet['item']->code}] " : '' }}{{ $sheet['item']->title }}
                    @if($sheet['item']->category) &nbsp;|&nbsp; <strong>CATEGORY:</strong> {{ strtoupper($sheet['item']->category) }} @endif
                    &nbsp;|&nbsp; <strong>TOTAL PARTICIPANTS:</strong> {{ count($sheet['rows']) }}
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th class="center" style="width: 30px;">#</th>
                        <th style="width: 90px;">CHEST NO.</th>
                        <th style="width: 110px;">REG NO.</th>
                        <th class="center" style="width: 100px;">ATTENDANCE</th>
                        <th class="center" style="width: 90px;">MARKS / SCORE</th>
                        <th class="center" style="width: 70px;">GRADE</th>
                        <th class="center" style="width: 80px;">RANK</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sheet['rows'] as $idx => $row)
                        <tr>
                            <td class="center" style="color: #64748b; font-weight: bold;">{{ $idx + 1 }}</td>
                            <td style="font-weight: bold; font-family: monospace; font-size: 12px; color: #0f172a;">
                                {{ $row['chest_no'] ? '#'.$row['chest_no'] : '—' }}
                            </td>
                            <td style="font-family: monospace; font-size: 10px; color: #334155;">
                                {{ $row['reg_no'] ?? '—' }}
                            </td>
                            <td class="center" style="font-size: 9px; color: #475569;">
                                [ &nbsp; ] P &nbsp;&nbsp; [ &nbsp; ] A
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="center" style="padding: 16px; color: #64748b;">
                                No approved registrations for this item.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

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
