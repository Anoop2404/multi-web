<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Result Declaration Sheet — {{ $event->title }}</title>
    <style>
        @page { margin: 16px 20px; size: portrait; margin-bottom: 24px;}
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11.5px; color: #1e293b; line-height: 1.4; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: avoid; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #0f172a; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; text-align: left; padding: 6px 8px; border: 1px solid #0f172a; }
        .table td { border: 1px solid #cbd5e1; padding: 9px 8px; font-size: 11.5px; height: 30px; }
        .table tr:nth-child(even) { background-color: #f8fafc; }
        .center { text-align: center; }
        .sign-box { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        .sign-col { float: left; width: 50%; font-size: 11px; font-weight: bold; color: #475569; }
        .clear { clear: both; }
        .published-at { margin-top: 18px; font-size: 13px; font-weight: bold; color: #0f172a; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @foreach($sheets as $sheet)
        <div class="sheet">
            <div class="header" style="margin-bottom: 12px;">
                @include('partials.pdf-branding-header', [
                    'orgName' => $sahodaya->name ?? 'Sahodaya',
                    'logoSrc' => $logoSrc ?? null,
                    'docTitle' => 'RESULT DECLARATION SHEET',
                ])
                <div style="font-size: 12px; font-weight: 800; color: #0f172a; margin: 0 0 8px;">{{ $event->title }}</div>

                @include('partials.pdf-item-info-bar', ['item' => $sheet['item'], 'category' => $sheet['category_label'] ?? null])

                <div style="font-size: 10px; color: #475569; text-align: right; margin-top: -6px;">
                    Generated on {{ now()->format('d M Y, h:i A') }}
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th class="center" style="width: 40px;">SL NO</th>
                        <th style="width: 140px;">CHEST NO.</th>
                        <th style="width: 140px;">POINTS</th>
                        <th style="width: 140px;">RANK</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 1; $i <= 10; $i++)
                        <tr>
                            <td class="center" style="color: #64748b; font-weight: bold;">{{ $i }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div class="sign-box">
                <div class="sign-col" style="width: 100%;">Convenor Signature: __________________________________</div>
                <div class="clear"></div>
                <div class="published-at">Published At: ______________________________________________ (Date &amp; Time)</div>
            </div>
        </div>
    @endforeach
</body>
</html>
