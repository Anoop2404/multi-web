<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Principals &amp; Contacts — {{ $sahodaya->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 8mm 12mm 8mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8.5px; color: #1e293b; margin: 0; }
        .head { border-bottom: 2px solid #0f3d7a; padding-bottom: 6px; margin-bottom: 10px; }
        .head table { width: 100%; border-collapse: collapse; }
        .org { font-size: 14px; font-weight: bold; color: #0f3d7a; text-transform: uppercase; }
        .title { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .meta { font-size: 8px; color: #64748b; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th { background: #f1f5f9; color: #334155; font-size: 8px; text-transform: uppercase; text-align: left; padding: 5px 6px; border: 1px solid #cbd5e1; }
        table.data td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .sub { color: #64748b; font-size: 7.5px; }
    </style>
</head>
<body>
    <div class="head">
        <table><tr>
            @if($logoSrc)<td style="width: 48px;"><img src="{{ $logoSrc }}" style="width: 44px; height: 44px;"></td>@endif
            <td>
                <div class="org">{{ $sahodaya->name }}</div>
                <div class="title">School Principals &amp; Contacts</div>
                <div class="meta">{{ count($rows) }} member schools &bull; Generated {{ $generatedAt }}</div>
            </td>
        </tr></table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 24px;">Sl</th>
                <th>School</th>
                @foreach($roles as $role)<th>{{ $role['label'] }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            <tr>
                <td>{{ $r['sl_no'] }}</td>
                <td>{{ $r['school'] }}@if($r['code'])<div class="sub">{{ $r['code'] }}</div>@endif @if($r['school_phone'])<div class="sub">{{ $r['school_phone'] }}</div>@endif</td>
                @foreach($roles as $role)
                @php($n = $role['prefix'].'_name') @php($ph = $role['prefix'].'_phone') @php($em = $role['prefix'].'_email')
                <td>
                    @if($r[$n] || $r[$ph] || $r[$em])
                        {{ $r[$n] ?: '—' }}
                        @if($r[$ph])<div class="sub">{{ $r[$ph] }}</div>@endif
                        @if($r[$em])<div class="sub">{{ $r[$em] }}</div>@endif
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
