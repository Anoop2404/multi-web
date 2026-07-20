<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Approved Registrations — {{ $event->title }}</title>
    <style>
        @page { margin: 16px 20px; size: A4 portrait; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
        .header { margin-bottom: 12px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #0f172a; color: #ffffff; font-size: 9px; font-weight: bold; text-transform: uppercase; text-align: left; padding: 6px 8px; border: 1px solid #0f172a; }
        .table td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 10px; }
        .table tr:nth-child(even) { background-color: #f8fafc; }
        .center { text-align: center; }
        .badge-approved { background-color: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 3px; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        .meta-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; margin-top: 8px; }
        .meta-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .meta-table td { padding: 2px 0; }
        .footer-sign { margin-top: 24px; border-top: 1px solid #cbd5e1; padding-top: 10px; }
        .sign-col { float: left; width: 50%; font-size: 10px; font-weight: bold; color: #475569; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
            <tr>
                @if(!empty($logoSrc))
                    <td style="width: 55px; vertical-align: middle; padding-right: 12px;">
                        <img src="{{ $logoSrc }}" alt="Logo" style="width: 48px; height: 48px; object-fit: contain;">
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <div style="font-size: 16px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.1;">
                        {{ $sahodaya->name ?? 'SAHODAYA SCHOOLS COMPLEX' }}
                    </div>
                    <div style="font-size: 10px; font-weight: 600; color: #475569; margin-top: 3px;">
                        CBSE Sahodaya Inter-School Competitions & Events
                    </div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="display: inline-block; background: #0f172a; color: #ffffff; padding: 4px 10px; border-radius: 4px; font-size: 9px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;">
                        APPROVED LIST
                    </div>
                </td>
            </tr>
        </table>

        <div style="border-bottom: 2px solid #0f172a; margin-bottom: 8px;"></div>

        <div class="meta-box">
            <table class="meta-table">
                <tr>
                    <td><strong>EVENT:</strong> {{ strtoupper($event->title) }}</td>
                    <td style="text-align: right;"><strong>REPORT:</strong> APPROVED REGISTRATIONS MASTER LIST</td>
                </tr>
                <tr>
                    <td><strong>TOTAL APPROVED REGISTRATIONS:</strong> {{ count($rows) }}</td>
                    <td style="text-align: right;"><strong>DATE:</strong> {{ date('d-M-Y H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th class="center" style="width: 35px;">SL NO</th>
                <th style="width: 70px;">CHEST NO.</th>
                <th>PARTICIPANT / TEAM</th>
                <th>SCHOOL</th>
                <th>COMPETITION ITEM</th>
                <th style="width: 90px;">FEST ID</th>
                <th class="center" style="width: 70px;">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $idx => $r)
                <tr>
                    <td class="center" style="color: #64748b; font-weight: bold;">{{ $idx + 1 }}</td>
                    <td style="font-weight: bold; font-family: monospace; font-size: 11px; color: #0f172a;">
                        {{ $r['chest_no'] ? '#'.$r['chest_no'] : '—' }}
                    </td>
                    <td style="font-weight: 600; color: #0f172a;">
                        {{ $r['participant_name'] }}
                        @if($r['is_team'])
                            <span style="font-size: 9px; color: #4338ca; font-weight: normal;"> (Team)</span>
                        @endif
                    </td>
                    <td style="font-size: 9.5px; text-transform: uppercase; color: #334155;">{{ $r['school_name'] }}</td>
                    <td style="font-weight: 600; color: #1e293b;">{{ $r['item_title'] }}</td>
                    <td style="font-family: monospace; font-size: 9.5px; color: #0f3d7a;">{{ $r['fest_id'] }}</td>
                    <td class="center">
                        <span class="badge-approved">APPROVED</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center" style="padding: 16px; color: #64748b;">
                        No approved registrations found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-sign">
        <div class="sign-col">Verified By: __________________________</div>
        <div class="sign-col" style="text-align: right;">Authorized Signature: __________________________</div>
        <div class="clear"></div>
    </div>
</body>
</html>
