<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $sheetTitle ?? 'Digital Sum Sheet' }} — {{ $event->title }}</title>
    <style>
        @page { margin: 16px 20px; size: {{ $orientation ?? 'portrait' }}; margin-bottom: 24px;}
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11.5px; color: #1e293b; line-height: 1.4; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: avoid; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
        .table th { background: #0f3d7a; color: #ffffff; font-weight: bold; text-transform: uppercase; text-align: left; border: 1px solid #0f3d7a; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; vertical-align: bottom; }
        .table td { border: 1px solid #000; word-wrap: break-word; overflow-wrap: break-word; }
        .table tr:nth-child(even) { background-color: #f8fafc; }
        .num { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @foreach($sheets as $sheet)
        <div class="sheet">
            <div class="header" style="margin-bottom: 12px;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
                    <tr>
                        @if(!empty($logoSrc))
                            <td style="width: 55px; vertical-align: middle; padding-right: 12px;">
                                <img src="{{ $logoSrc }}" alt="Logo" style="width: 48px; height: 48px; object-fit: contain;">
                            </td>
                        @endif
                        <td style="vertical-align: middle;">
                            <div style="font-size: 17px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.1;">
                                {{ $orgName ?? ($sahodaya->name ?? 'SAHODAYA SCHOOLS COMPLEX') }}
                            </div>
                            <div style="font-size: 11px; font-weight: 600; color: #475569; margin-top: 3px;">
                                CBSE Sahodaya Inter-School Competitions & Events
                            </div>
                        </td>
                        <td style="text-align: right; vertical-align: middle;">
                            <div style="display: inline-block; background: #0f172a; color: #ffffff; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;">
                                Online Tabulation
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="border-bottom: 2px solid #0f172a; margin-bottom: 8px;"></div>

                <div style="background: #f8fafc; border: 1px solid #000; border-radius: 4px; padding: 6px 10px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 10px; color: #1e293b;">
                        <tr>
                            <td style="padding: 2px 0;"><strong>EVENT:</strong> {{ strtoupper($event->title) }}</td>
                            <td style="padding: 2px 0; text-align: right;"><strong>SHEET:</strong> {{ strtoupper($sheetTitle ?? 'DIGITAL SUM SHEET') }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0;">
                                <strong>ITEM:</strong> {{ $sheet['item']?->item_code ? "[{$sheet['item']->item_code}] " : '' }}{{ $sheet['item']?->title }}
                                @if(!empty($sheet['category_label']))
                                    <strong>&middot; CATEGORY:</strong> {{ $sheet['category_label'] }}
                                @endif
                            </td>
                            <td style="padding: 2px 0; text-align: right;"><strong>TOTAL PARTICIPANTS:</strong> {{ count($sheet['rows']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 2px 0; text-align: right;"><strong>GENERATED:</strong> {{ now()->format('d M Y, h:i A') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            @php
                $jCount = max(1, (int) ($sheet['judge_count'] ?? 1));
                $colCount = $jCount > 1 ? ($jCount + 4) : 4;

                if ($colCount <= 4) {
                    $thFont = '10.5px';
                    $tdFont = '11px';
                    $thPadding = '6px 8px';
                    $tdPadding = '6px 8px';
                    $slWidth = '38px';
                    $chestWidth = '85px';
                    $totalWidth = '90px';
                    $remarksWidth = '110px';
                } elseif ($colCount <= 6) {
                    $thFont = '10px';
                    $tdFont = '10.5px';
                    $thPadding = '5px 6px';
                    $tdPadding = '6px 6px';
                    $slWidth = '34px';
                    $chestWidth = '70px';
                    $totalWidth = '75px';
                    $remarksWidth = '95px';
                } else {
                    $thFont = '9px';
                    $tdFont = '10px';
                    $thPadding = '4px 4px';
                    $tdPadding = '5px 4px';
                    $slWidth = '30px';
                    $chestWidth = '58px';
                    $totalWidth = '62px';
                    $remarksWidth = '75px';
                }
            @endphp

            <table class="table">
                <colgroup>
                    <col style="width: {{ $slWidth }};">
                    <col style="width: {{ $chestWidth }};">
                    @if($sheet['judge_count'] > 1)
                        @for($j = 1; $j <= $sheet['judge_count']; $j++)
                            <col>
                        @endfor
                        <col style="width: {{ $totalWidth }};">
                    @else
                        <col>
                    @endif
                    <col style="width: {{ $remarksWidth }};">
                </colgroup>
                <thead>
                    <tr>
                        <th class="center" style="font-size: {{ $thFont }}; padding: {{ $thPadding }};">Sl No</th>
                        <th style="font-size: {{ $thFont }}; padding: {{ $thPadding }};">Chest No</th>
                        @if($sheet['judge_count'] > 1)
                            @for($j = 1; $j <= $sheet['judge_count']; $j++)
                                <th style="font-size: {{ $thFont }}; padding: {{ $thPadding }};">Judge {{ $j }}</th>
                            @endfor
                            <th style="font-size: {{ $thFont }}; padding: {{ $thPadding }};">Grand Total</th>
                        @else
                            <th style="font-size: {{ $thFont }}; padding: {{ $thPadding }};">Score</th>
                        @endif
                        <th style="font-size: {{ $thFont }}; padding: {{ $thPadding }};">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sheet['rows'] as $row)
                        <tr>
                            <td class="center" style="font-size: {{ $tdFont }}; padding: {{ $tdPadding }};">{{ $loop->iteration }}</td>
                            <td style="font-weight: bold; font-family: monospace; font-size: {{ $tdFont }}; padding: {{ $tdPadding }};">{{ ($blankChest ?? false) ? '' : ($row['chest_no'] ? '#'.$row['chest_no'] : '—') }}</td>
                            @if($sheet['judge_count'] > 1)
                                @foreach($row['scores'] as $s)
                                    <td class="num" style="font-size: {{ $tdFont }}; padding: {{ $tdPadding }};">{{ $s === null ? '' : rtrim(rtrim(number_format($s, 2), '0'), '.') }}</td>
                                @endforeach
                                <td class="num" style="font-size: {{ $tdFont }}; padding: {{ $tdPadding }};">{{ $row['total'] === null ? '' : rtrim(rtrim(number_format($row['total'], 2), '0'), '.') }}</td>
                            @else
                                <td class="num" style="font-size: {{ $tdFont }}; padding: {{ $tdPadding }};">{{ $row['scores'][0] === null ? '' : rtrim(rtrim(number_format($row['scores'][0], 2), '0'), '.') }}</td>
                            @endif
                            <td style="font-size: {{ $tdFont }}; padding: {{ $tdPadding }};"></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $sheet['judge_count'] > 1 ? 2 + $sheet['judge_count'] + 2 : 4 }}" class="center" style="padding: 16px; color: #64748b;">No participants for this item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
