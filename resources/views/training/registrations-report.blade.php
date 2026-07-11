<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registrations — {{ $program->title }}</title>
    <style>
        /* DomPDF: `* { margin:0 }` zeroes @page margins — use .page margin instead. */
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f2744;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .page { margin: 36px 40px; }
        .header {
            border-bottom: 2px solid #0f2744;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header table { width: 100%; }
        .logo {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }
        .org {
            font-size: 15px;
            font-weight: 700;
            color: #0f2744;
        }
        .tag {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-top: 3px;
        }
        .title {
            font-size: 16px;
            font-weight: 700;
            margin: 10px 0 4px;
        }
        .meta {
            font-size: 10px;
            color: #64748b;
            margin-bottom: 14px;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
        }
        table.report th {
            background: #0f2744;
            color: #fff;
            text-align: left;
            padding: 7px 8px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        table.report td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.report tr:nth-child(even) td {
            background: #f8fafc;
        }
        .sl { width: 48px; text-align: center; }
        .empty {
            text-align: center;
            color: #94a3b8;
            padding: 28px 0;
        }
        .footer {
            margin-top: 18px;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <table>
            <tr>
                @if(!empty($logoSrc))
                    <td style="width:64px;vertical-align:middle;">
                        <img class="logo" src="{{ $logoSrc }}" alt="">
                    </td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="tag">Teacher training · Registration report</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $program->title }}</div>
    <div class="meta">
        {{ $rows->count() }} registration(s)
        @if($program->start_date)
            · {{ $program->start_date->format('d M Y') }}
            @if($program->end_date && ! $program->end_date->isSameDay($program->start_date))
                – {{ $program->end_date->format('d M Y') }}
            @endif
        @endif
        @if($program->venue)
            · {{ $program->venue }}
        @endif
    </div>

    @if($rows->isEmpty())
        <p class="empty">No registrations yet.</p>
    @else
        <table class="report">
            <thead>
                <tr>
                    <th class="sl">Sl No</th>
                    <th>Teacher name</th>
                    <th>Category</th>
                    <th>School</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td class="sl">{{ $row[0] }}</td>
                        <td>{{ $row[1] !== '' ? $row[1] : '—' }}</td>
                        <td>{{ $row[2] !== '' ? $row[2] : '—' }}</td>
                        <td>{{ $row[3] !== '' ? $row[3] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Generated {{ $generatedAt }}</div>
</div>
</body>
</html>
