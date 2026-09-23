{{--
    Participant cards, eight to an A4 page. The chest number is the largest thing on the card because
    it is what a gate marshal and a stage marshal actually read; both the Sahodaya and the School are
    printed because the State competes Sahodaya against Sahodaya but a participant is still from a
    school.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        .grid { width: 100%; }
        .card {
            width: 47%; display: inline-block; vertical-align: top;
            border: 1px dashed #94a3b8; border-radius: 4px; padding: 6px 8px; margin: 0 1% 6px 0;
            min-height: 62px; overflow: hidden;
        }
        .chest { float: right; font-size: 20px; font-weight: bold; color: #1e293b; line-height: 1; }
        .name { font-size: 10px; font-weight: bold; margin: 0 0 1px; }
        .line { color: #475569; margin: 0; }
        .event { color: #64748b; font-size: 7px; margin-top: 3px; border-top: 1px solid #e2e8f0; padding-top: 2px; }
        .entries { margin: 2px 0 0; padding: 0; list-style: none; color: #334155; font-size: 7.5px; }
        .entries li { display: inline-block; margin-right: 6px; }
        .entries b { font-family: DejaVu Sans Mono, monospace; }
    </style>
</head>
<body>
    <div class="grid">
        @foreach ($cards as $card)
            <div class="card">
                {{-- One number when there is one entry; otherwise every item with its own number,
                     because a participant wears a different number for each item. --}}
                @if ($card['chest_number'])
                    <span class="chest">{{ $card['chest_number'] }}</span>
                @endif
                <p class="name">{{ $card['name'] }}</p>
                <p class="line">{{ $card['sahodaya'] }}</p>
                <p class="line">{{ $card['school'] }}</p>
                @if (! $card['chest_number'])
                    <ul class="entries">
                        @foreach ($card['entries'] as $entry)
                            <li>{{ $entry['item_code'] }} <b>{{ $entry['chest_number'] ?: '—' }}</b></li>
                        @endforeach
                    </ul>
                @endif
                <p class="event">{{ $eventName }}@if ($card['class_name']) · {{ $card['class_name'] }} @endif</p>
            </div>
        @endforeach
    </div>
</body>
</html>
