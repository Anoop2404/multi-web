<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — Clash Form</title>
    <style>
        @page { margin: 16px 14px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1e293b; margin: 0; }
        {{-- Two identical copies side by side on one A4 page (one for the team manager to keep,
             one for the Sahodaya desk to keep) -- same layout as the paper form this replaces. --}}
        .sheet { display: table; width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 10px 0; }
        .copy { display: table-cell; width: 50%; vertical-align: top; border: 1.5px solid #0f172a; padding: 10px 12px; box-sizing: border-box; }
        .header { text-align: center; margin-bottom: 6px; }
        .header img { width: 40px; height: 40px; object-fit: contain; vertical-align: middle; margin-right: 8px; }
        .header .org-name { font-size: 14px; font-weight: bold; color: #0f172a; text-transform: uppercase; }
        .header .org-sub { font-size: 9px; color: #475569; margin-top: 2px; }
        .header .org-contact { font-size: 8.5px; color: #64748b; margin-top: 1px; }
        h1 {
            text-align: center; font-size: 12.5px; font-weight: bold; margin: 8px 0 10px;
            border-top: 1px solid #0f172a; border-bottom: 1px solid #0f172a; padding: 4px 0;
        }
        .field { margin-bottom: 7px; font-size: 11px; }
        .field .label { font-weight: bold; }
        .field .fill { display: inline-block; border-bottom: 1px solid #475569; min-height: 13px; padding: 0 3px; }
        .field-value { min-width: 60%; }
        .item-box { border: 1px solid #94a3b8; border-radius: 3px; padding: 6px 8px; margin-bottom: 8px; min-height: 44px; }
        .item-box .item-line { margin-bottom: 5px; }
        .sign-block { margin-top: 10px; font-size: 10px; }
        .sign-block .line { margin-bottom: 8px; }
        .sign-block .fill { display: inline-block; border-bottom: 1px solid #94a3b8; min-width: 55%; min-height: 12px; }
    </style>
</head>
<body>
    @php
        // One shared copy of the fields, rendered twice -- the include below is called
        // with the same $items/$field values both times.
    @endphp
    <div class="sheet">
        @for ($copy = 0; $copy < 2; $copy++)
            <div class="copy">
                <div class="header">
                    @if(!empty($logoSrc))
                        <img src="{{ $logoSrc }}" alt="Logo">
                    @endif
                    <div class="org-name">{{ $orgName ?? 'Sahodaya' }}</div>
                    @if(!empty($orgSubtitle))
                        <div class="org-sub">{{ $orgSubtitle }}</div>
                    @endif
                    @if(!empty($orgContact))
                        <div class="org-contact">{{ $orgContact }}</div>
                    @endif
                </div>

                <h1>Off Stage / Stage Events — Clash Form {{ $year ?? now()->format('Y') }}</h1>

                <div class="field">
                    <span class="label">Date:</span>
                    <span class="fill field-value">{{ $date ?? '' }}</span>
                </div>
                <div class="field">
                    <span class="label">Name of the Student:</span><br>
                    <span class="fill field-value" style="display:block;">{{ $studentName ?? '' }}</span>
                </div>
                <div class="field">
                    <span class="label">Roll No:</span>
                    <span class="fill" style="min-width: 90px;">{{ $rollNo ?? '' }}</span>
                    &nbsp;&nbsp;<span class="label">Category</span>
                    <span class="fill" style="min-width: 90px;">{{ $category ?? '' }}</span>
                </div>
                <div class="field">
                    <span class="label">School Name:</span>
                    <span class="fill field-value">{{ $schoolName ?? '' }}</span>
                </div>

                @foreach($items as $item)
                    <div class="item-box">
                        <div class="item-line">
                            <span class="label">Item Name:</span>
                            <span class="fill" style="min-width: 60%;">{{ $item['title'] ?? '' }}</span>
                        </div>
                        <div class="item-line">
                            <span class="label">Room/Stage No:</span>
                            <span class="fill" style="min-width: 100px;">{{ $item['stage'] ?? '' }}</span>
                            &nbsp;&nbsp;<span class="label">Time</span>
                            <span class="fill" style="min-width: 100px;">{{ $item['time'] ?? '' }}</span>
                        </div>
                    </div>
                @endforeach

                <div class="sign-block">
                    <div class="line">Name &amp; Signature of Team Manager: <span class="fill"></span></div>
                    <div class="line">Remarks of Sahodaya Official: <span class="fill" style="display:block; min-height: 24px;"></span></div>
                    <div class="line">Signature of Sahodaya Official: <span class="fill"></span></div>
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
