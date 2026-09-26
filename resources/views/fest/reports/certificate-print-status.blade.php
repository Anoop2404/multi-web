<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — Certificate Print Status</title>
    <style>
        @page { margin: {{ ($isDomPdf ?? true) ? '22px 28px 24px 28px' : '32mm 10mm 14mm 10mm' }}; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        .school + .school { page-break-before: always; }
        h2 { font-size: 15px; font-weight: bold; color: #1d3557; text-transform: uppercase; margin: 0 0 4px; padding-bottom: 5px; border-bottom: 2px solid #1d3557; }
        .summary { font-size: 10.5px; color: #475569; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #1d3557; color: #fff; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 0 8px; height: 26px; border: 1px solid #1d3557; }
        td { border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 10.5px; vertical-align: top; }
        td.num, th.num { width: 28px; text-align: center; padding: 5px 2px; }
        td.cls, th.cls { width: 42px; text-align: center; }
        td.st, th.st { width: 92px; }
        .printed { color: #166534; font-weight: bold; }
        .ready { color: #1d4ed8; font-weight: bold; }
        .awaiting { color: #b45309; font-weight: bold; }
        .small { color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
    @if($isDomPdf ?? true)
        @include('partials.pdf-generated-footer', ['generatedAt' => null])
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null, 'docTitle' => 'Certificate Print Status'])
    @endif

    @php
        $scope = ['printed' => 'printed students', 'unprinted' => 'students not printed yet', 'all' => 'all students'][$status ?? 'all'];
        $labels = ['printed' => 'Printed', 'ready' => 'Ready to print', 'awaiting' => 'Awaiting results'];
    @endphp

    @forelse($schools as $school)
        <div class="school">
            <h2>{{ $school['name'] }}</h2>
            <div class="summary">
                Showing {{ $scope }} · {{ $school['counts']['total'] }} students ·
                <span class="printed">{{ $school['counts']['printed'] }} printed</span> ·
                <span class="ready">{{ $school['counts']['ready'] }} ready to print</span> ·
                <span class="awaiting">{{ $school['counts']['awaiting'] }} awaiting results</span>
            </div>
            <table>
                <thead>
                    <tr><th class="num">#</th><th style="width: 26%;">Student</th><th class="cls">Class</th><th>Items</th><th class="st">Status</th></tr>
                </thead>
                <tbody>
                    @foreach($school['students'] as $i => $student)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td><strong>{{ $student['name'] }}</strong></td>
                            <td class="cls">{{ $student['class'] }}</td>
                            <td>
                                {{ implode(', ', $student['items']) }}
                                @if($student['status'] === 'awaiting' && count($student['awaiting']))
                                    <div class="small">Awaiting: {{ implode(', ', $student['awaiting']) }}</div>
                                @endif
                            </td>
                            <td class="st">
                                <span class="{{ $student['status'] }}">{{ $labels[$student['status']] }}</span>
                                @if($student['printed_at'])
                                    <div class="small">{{ $student['printed_at']->format('d M Y') }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align: center; color: #64748b; padding: 30px;">No students match this filter.</p>
    @endforelse
</body>
</html>
