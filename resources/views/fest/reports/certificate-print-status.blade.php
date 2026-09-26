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
        th { background: #1d3557; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 0 8px; height: 30px; border: 1px solid #1d3557; }
        td { border: 1px solid #94a3b8; padding: 0 8px; height: 30px; font-size: 11.5px; vertical-align: middle; }
        td.num, th.num { width: 34px; text-align: center; padding: 0 2px; }
        td.id, th.id { width: 62px; text-align: center; }
        td.cat, th.cat { width: 56px; text-align: center; }
        td.done, th.done { width: 74px; text-align: center; font-weight: bold; color: #166534; }
        td.chk, th.chk { width: 78px; text-align: center; }
        td.items { font-size: 10px; line-height: 1.35; padding-top: 3px; padding-bottom: 3px; }
        .box { display: inline-block; width: 14px; height: 14px; border: 1.5px solid #475569; vertical-align: middle; }
</style>
</head>
<body>
    @if($isDomPdf ?? true)
        @include('partials.pdf-generated-footer', ['generatedAt' => null])
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null, 'docTitle' => 'Certificate Print Status'])
    @endif

    @forelse($schools as $school)
        <div class="school">
            <h2>{{ $school['name'] }}</h2>
            <div class="summary">{{ count($school['students']) }} student{{ count($school['students']) === 1 ? '' : 's' }}</div>
            <table>
                <thead>
                    <tr>
                        <th class="num">Sl No</th>
                        <th style="width: 24%;">Student</th>
                        <th class="id">Fest ID</th>
                        <th class="cat">Category</th>
                        <th>Items</th>
                        <th class="done">Complete</th>
                        <th class="chk">Verification</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($school['students'] as $i => $student)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td><strong>{{ $student['name'] }}</strong></td>
                            <td class="id">{{ $student['fest_id'] }}</td>
                            <td class="cat">{{ $student['category'] }}</td>
                            <td class="items">{{ implode(', ', $student['items']) }}</td>
                            <td class="done">{{ $student['complete'] ? 'Complete' : '' }}</td>
                            <td class="chk"><span class="box"></span></td>
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
