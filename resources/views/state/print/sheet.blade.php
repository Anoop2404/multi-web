{{--
    One template for every operational sheet. They differ only in columns and what goes in them, so
    the layout — item heading, time and venue, numbered rows with room to write — is shared.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        .head { border-bottom: 2px solid #1e293b; padding-bottom: 4px; margin-bottom: 8px; }
        h1 { font-size: 13px; margin: 0; }
        .meta { font-size: 8px; color: #555; }
        .item { margin-bottom: 14px; page-break-inside: avoid; }
        .item h2 { font-size: 10px; margin: 0 0 3px; background: #f1f5f9; padding: 3px 5px; }
        .item .when { font-weight: normal; color: #475569; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e293b; color: #fff; font-size: 7.5px; text-align: left; padding: 4px 5px; }
        td { border: 1px solid #cbd5e1; padding: 5px; height: 14px; }
        .empty { padding: 16px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $title }}</h1>
        <p class="meta">
            {{ $eventName }} · State Kalotsav · generated {{ $generated }}
            @if ($subtitle) <br>{{ $subtitle }} @endif
        </p>
    </div>

    @forelse ($groups as $group)
        <div class="item">
            <h2>
                {{ $group['item_code'] }}
                <span class="when">
                    @if ($group['when']) — {{ $group['when'] }} @endif
                    @if ($group['starts_at']) at {{ $group['starts_at'] }} @endif
                    @if ($group['venue']) · {{ $group['venue'] }} @endif
                    · {{ $group['count'] }} entries
                </span>
            </h2>
            <table>
                <thead>
                    <tr>@foreach ($columns as $column)<th style="width: {{ $column['width'] }}">{{ $column['label'] }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($group['rows'] as $row)
                        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="empty">Nothing matches this selection.</p>
    @endforelse
</body>
</html>
