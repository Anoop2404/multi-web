<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $exam->title }} — Absent report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 16px; }
        .header { margin-bottom: 14px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo { max-height: 40px; float: right; }
        .title { font-size: 16px; font-weight: bold; color: #0f172a; margin: 0 0 4px; }
        .subtitle { font-size: 12px; color: #64748b; margin: 0; }
        .meta { font-size: 10px; color: #94a3b8; margin-top: 4px; }
        h2 { font-size: 12px; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: 0.04em; color: #334155; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #000; padding: 4px 6px; }
        th { background-color: #f8fafc; color: #334155; font-size: 10px; text-transform: uppercase; }
        td { font-size: 10.5px; }
        .text-center { text-align: center; }
        .absent { font-weight: bold; color: #b91c1c; }
        .empty { text-align: center; color: #94a3b8; padding: 18px; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoSrc))
            <img src="{{ $logoSrc }}" class="logo" alt="Logo">
        @endif
        <div class="title">{{ $exam->title }}</div>
        <div class="subtitle">Absent attendance report</div>
        <div class="meta">
            {{ $orgName }}
            @if($school) · {{ $school->name }} @endif
            @if(!empty($selectedClass) && strtolower(trim((string) $selectedClass)) !== 'all') · Class {{ $selectedClass }} @endif
            · {{ count($rows) }} absentee(s)
            · Generated on {{ $generatedAt }}
        </div>
    </div>

    @if(count($schoolRows))
        <h2>School-wise summary</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:28px;" class="text-center">#</th>
                    <th>School</th>
                    <th class="text-center">Registered</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Absent %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schoolRows as $i => $row)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ strtoupper($row['school_name'] ?? '') }}</td>
                        <td class="text-center">{{ $row['registered'] }}</td>
                        <td class="text-center">{{ $row['present'] }}</td>
                        <td class="text-center absent">{{ $row['absent'] }}</td>
                        <td class="text-center">{{ $row['absent_rate'] !== null ? $row['absent_rate'].'%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($classRows))
        <h2>Class-wise summary</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:28px;" class="text-center">#</th>
                    <th>Class</th>
                    <th class="text-center">Registered</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Absent %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($classRows as $i => $row)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $row['class_name'] }}</td>
                        <td class="text-center">{{ $row['registered'] }}</td>
                        <td class="text-center">{{ $row['present'] }}</td>
                        <td class="text-center absent">{{ $row['absent'] }}</td>
                        <td class="text-center">{{ $row['absent_rate'] !== null ? $row['absent_rate'].'%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Absentee list</h2>
    <table>
        <thead>
            <tr>
                <th style="width:28px;" class="text-center">#</th>
                <th>Hall ticket</th>
                <th>Student</th>
                <th>Reg. no</th>
                <th>Class</th>
                <th>School</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $row['hall_ticket_no'] ?: '—' }}</td>
                    <td>{{ $row['student_name'] ?: '—' }}</td>
                    <td>{{ $row['reg_no'] ?: '—' }}</td>
                    <td>{{ $row['class_name'] ?: '—' }}</td>
                    <td>{{ strtoupper($row['school_name'] ?? '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">No absentees for the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
