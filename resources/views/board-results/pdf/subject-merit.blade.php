<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject-Wise Merit Register — {{ $academicYear }}</title>
    <style>
        @page { margin: 30px 36px; }
        * { box-sizing: border-box; }
        html, body { font-family: DejaVu Sans, sans-serif; color: #0f2744; font-size: 9px; margin: 0; padding: 0; }
        
        .subject-page { page-break-after: always; }
        .subject-page:last-child { page-break-after: avoid; }

        .header { border-bottom: 2px solid #0f3d7a; padding-bottom: 8px; margin-bottom: 12px; }
        .header table { width: 100%; }
        .logo { width: 44px; height: 44px; object-fit: contain; }
        .org { font-size: 14px; font-weight: 700; color: #0f3d7a; }
        .tag { font-size: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        
        .subject-header-banner {
            background: #0f3d7a;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .subject-header-banner table { width: 100%; }
        .subj-title { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #ffffff; }
        .subj-meta { font-size: 9px; color: #e2e8f0; text-align: right; }

        .summary-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-size: 8.5px;
            color: #475569;
        }

        table.report-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.report-table th { background: #1e293b; color: #ffffff; text-align: left; padding: 6px; font-size: 8px; text-transform: uppercase; border: 1px solid #1e293b; }
        table.report-table td { padding: 6px; border: 1px solid #cbd5e1; vertical-align: middle; }
        table.report-table tr:nth-child(even) td { background: #f8fafc; }

        .rank-col { width: 35px; text-align: center; font-weight: bold; }
        .student-name { font-weight: bold; color: #0f172a; font-size: 9.5px; }
        .roll-no { font-family: monospace; font-size: 8.5px; color: #334155; }
        .school-name { font-size: 8.5px; color: #334155; }
        .marks-col { font-weight: bold; color: #166534; text-align: center; font-size: 10.5px; }
    </style>
</head>
<body>

    @php
        $grouped = collect($rows)->groupBy(function($r) {
            $subj = $r['subject'] ?? 'Subject';
            $cls = !empty($r['class']) ? ' (Class '.$r['class'].')' : '';
            return $subj . $cls;
        });
    @endphp

    @if($grouped->isEmpty())
        <div class="header">
            <table>
                <tr>
                    @if(!empty($logoSrc))
                        <td style="width:50px; vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
                    @endif
                    <td style="vertical-align:middle;">
                        <div class="org">{{ $orgName }}</div>
                        <div class="tag">Academic Results · Subject-Wise Merit Register</div>
                    </td>
                </tr>
            </table>
        </div>
        <p style="text-align:center; color:#64748b; margin-top:30px;">No subject toppers found for the selected criteria.</p>
    @else
        @foreach($grouped as $subjectLabel => $subjectRows)
            <div class="subject-page">
                <!-- PAGE HEADER -->
                <div class="header">
                    <table>
                        <tr>
                            @if(!empty($logoSrc))
                                <td style="width:50px; vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
                            @endif
                            <td style="vertical-align:middle;">
                                <div class="org">{{ $orgName }}</div>
                                <div class="tag">Academic Board Results · Subject-Wise Merit Register</div>
                            </td>
                            <td style="text-align:right; vertical-align:middle;">
                                <div style="font-size:11px; font-weight:700; color:#1e293b;">Academic Year: {{ $academicYear }}</div>
                                <div style="font-size:8px; color:#64748b;">Generated: {{ $generatedAt }}</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- SUBJECT TITLE BANNER -->
                <div class="subject-header-banner">
                    <table>
                        <tr>
                            <td class="subj-title">📘 {{ strtoupper($subjectLabel) }}</td>
                            <td class="subj-meta">
                                Highest Mark: <strong>{{ $subjectRows->max('marks') }}/100</strong> · Total Merit Performers: <strong>{{ $subjectRows->count() }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TOPPER TABLE FOR THIS SUBJECT -->
                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="rank-col">Rank</th>
                            <th>Student Name</th>
                            <th>Roll No.</th>
                            <th>Member School</th>
                            <th style="width:70px; text-align:center;">Marks / 100</th>
                            <th style="width:70px; text-align:center;">Stream</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subjectRows as $i => $row)
                            <tr>
                                <td class="rank-col">
                                    @if(($row['rank'] ?? 0) == 1)
                                        <span style="color:#d97706; font-weight:bold;">🥇 1</span>
                                    @elseif(($row['rank'] ?? 0) == 2)
                                        <span style="color:#475569; font-weight:bold;">🥈 2</span>
                                    @elseif(($row['rank'] ?? 0) == 3)
                                        <span style="color:#b45309; font-weight:bold;">🥉 3</span>
                                    @else
                                        #{{ $row['rank'] ?? ($i + 1) }}
                                    @endif
                                </td>
                                <td class="student-name">{{ $row['student_name'] }}</td>
                                <td class="roll-no">{{ $row['roll_no'] ?: '—' }}</td>
                                <td class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</td>
                                <td class="marks-col">{{ $row['marks'] }}</td>
                                <td style="text-align:center; font-size:8.5px; color:#475569;">{{ $row['stream'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

</body>
</html>
