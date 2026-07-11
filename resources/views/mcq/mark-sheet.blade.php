<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark sheet — {{ $exam->title }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { font-family: DejaVu Sans, sans-serif; color: #0f2744; font-size: 10px; margin: 0; padding: 0; }
        .page { margin: 32px 40px; }
        .header { border-bottom: 2px solid #0f2744; padding-bottom: 8px; margin-bottom: 10px; }
        .header table { width: 100%; }
        .logo { width: 42px; height: 42px; object-fit: contain; }
        .org { font-size: 13px; font-weight: 700; }
        .tag { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .title { font-size: 14px; font-weight: 700; margin: 6px 0 2px; }
        .meta { font-size: 9px; color: #64748b; margin-bottom: 10px; }
        .note { font-size: 9px; margin-bottom: 10px; padding: 5px 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th { background: #0f2744; color: #fff; text-align: left; padding: 6px 5px; font-size: 8px; text-transform: uppercase; }
        table.sheet td { padding: 6px 5px; border: 1px solid #cbd5e1; height: 28px; }
        table.sheet tr:nth-child(even) td { background: #f8fafc; }
        .sl { width: 28px; text-align: center; }
        .num { width: 70px; text-align: center; }
        .empty { text-align: center; color: #94a3b8; padding: 20px 0; }
        .footer { margin-top: 12px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <table>
            <tr>
                @if(!empty($logoSrc))
                    <td style="width:50px;vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="tag">Talent Search · Mark entry sheet</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $exam->title }}@if($exam->code) <span style="font-weight:400;font-size:11px">({{ $exam->code }})</span>@endif</div>
    <div class="meta">
        Present candidates only · {{ count($rows) }} row(s)
        @if($totalQuestions) · {{ $totalQuestions }} question(s) @endif
        @if($exam->pass_mark) · Pass mark {{ $exam->pass_mark }}% @endif
    </div>
    <div class="note">Enter correct / wrong / unanswered counts and total score. Leave blank cells for offline marking.</div>

    @if(empty($rows))
        <p class="empty">No present candidates yet.</p>
    @else
        <table class="sheet">
            <thead>
                <tr>
                    <th class="sl">Sl</th>
                    <th>Reg. no.</th>
                    <th>Name</th>
                    <th>School</th>
                    <th class="num">Correct</th>
                    <th class="num">Wrong</th>
                    <th class="num">Unans.</th>
                    <th class="num">Score</th>
                    <th class="num">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td class="sl">{{ $row['sl'] }}</td>
                        <td>{{ $row['hall_ticket_no'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['school'] }}</td>
                        <td class="num">&nbsp;</td>
                        <td class="num">&nbsp;</td>
                        <td class="num">&nbsp;</td>
                        <td class="num">&nbsp;</td>
                        <td class="num">&nbsp;</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Generated {{ $generatedAt }} · Blank mark entry for physical / OMR use</div>
</div>
</body>
</html>
