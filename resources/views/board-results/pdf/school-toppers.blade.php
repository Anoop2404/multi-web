<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>School Toppers Report</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #0b2558; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 10px; }
        .title { font-size: 18px; font-weight: bold; color: #0b2558; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 14px; font-weight: bold; color: #555; margin: 5px 0 0 0; }
        .meta { font-size: 11.5px; color: #777; margin-top: 5px; }
        
        .section-title { font-size: 14.5px; font-weight: bold; color: #0b2558; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-top: 30px; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; vertical-align: middle; }
        th { background-color: #f8fafc; font-weight: bold; color: #475569; font-size: 11px; text-transform: uppercase; }
        td { font-size: 11.5px; }
        .center { text-align: center; }
        
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        .page-number:before { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <h1 class="title">{{ $school->name }}</h1>
        <h2 class="subtitle">Toppers & Achievers • Class {{ $selectedClass }} ({{ $academicYear }})</h2>
        <p class="meta">Generated: {{ $generatedAt }}</p>
    </div>

    @php
        $overall = $result->toppers->where('entry_type', 'overall')->sortByDesc('percentage');
        $subjects = $result->toppers->where('entry_type', 'subject')->sortBy(function($t) {
            return collect($t->subject_marks)->keys()->first() ?? '';
        });
        $fullA1 = $result->toppers->where('entry_type', 'full_a1')->sortBy('name');
    @endphp

    <div class="section-title">Overall Toppers @if($selectedClass == 12) (Stream-wise) @endif</div>
    @if($overall->isEmpty())
        <p style="color: #666; font-style: italic;">No overall toppers recorded.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th class="center" style="width: 40px;">Rank</th>
                    <th>Student Name</th>
                    <th>Roll No</th>
                    @if($selectedClass == 12) <th>Stream</th> @endif
                    <th class="center">Marks</th>
                    <th class="center">Percentage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($overall as $idx => $t)
                <tr>
                    <td class="center" style="font-weight: bold;">#{{ $t->rank ?: ($idx + 1) }}</td>
                    <td style="font-weight: bold;">{{ $t->name }}</td>
                    <td style="font-family: monospace; color: #555;">{{ $t->roll_no ?: '—' }}</td>
                    @if($selectedClass == 12) <td>{{ $t->stream ?: '—' }}</td> @endif
                    <td class="center">{{ $t->marks_obtained }}/{{ $t->total_marks }}</td>
                    <td class="center" style="font-weight: bold; color: #059669;">{{ number_format($t->percentage, 2) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title" style="page-break-before: auto;">Subject-wise Toppers</div>
    @if($subjects->isEmpty())
        <p style="color: #666; font-style: italic;">No subject-wise toppers recorded.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Student Name</th>
                    <th>Roll No</th>
                    <th class="center">Marks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subjects as $t)
                @php 
                    $subjName = collect($t->subject_marks)->keys()->first() ?? '—';
                    $subjMark = collect($t->subject_marks)->values()->first() ?? '—';
                @endphp
                <tr>
                    <td style="font-weight: bold; color: #334155;">{{ $subjName }}</td>
                    <td style="font-weight: bold;">{{ $t->name }}</td>
                    <td style="font-family: monospace; color: #555;">{{ $t->roll_no ?: '—' }}</td>
                    <td class="center" style="font-weight: bold; color: #059669;">{{ $subjMark }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title" style="page-break-before: auto;">Full A1 Achievers</div>
    @if($fullA1->isEmpty())
        <p style="color: #666; font-style: italic;">No Full A1 Achievers recorded.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Roll No</th>
                    <th>Subjects (All A1)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($fullA1 as $t)
                <tr>
                    <td style="font-weight: bold;">{{ $t->name }}</td>
                    <td style="font-family: monospace; color: #555;">{{ $t->roll_no ?: '—' }}</td>
                    <td>
                        @foreach(($t->subject_marks ?? []) as $subject => $mark)
                            <span style="display: inline-block; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 2px 4px; margin: 2px; border-radius: 3px; font-size: 9px; color: #475569;">
                                {{ $subject }}: <strong style="color: #059669;">{{ $mark }}</strong>
                            </span>
                        @endforeach
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ $school->name }} • Toppers & Achievers • <span class="page-number"></span>
    </div>
</body>
</html>
