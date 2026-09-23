{{--
    Shared layout for every tabular State report. One template rather than one per report: they all
    render a title, the event it came from, and a table, and a report that needs more than that
    should get its own view rather than bending this one.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { font-size: 8px; color: #666; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e293b; color: #fff; font-size: 8px; text-align: left; padding: 5px 6px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .empty { padding: 18px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">{{ $eventName }} · State Kalotsav · generated {{ $generated }}</p>

    @if (count($rows) === 0)
        <p class="empty">No rows matched this report's filters.</p>
    @else
        <table>
            <thead>
                <tr>@foreach ($headers as $header)<th>{{ $header }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
