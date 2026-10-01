<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — Clash Form</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm;
        }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; }
        {{-- Two identical copies side by side on one A4 landscape sheet (one for team manager, one for Sahodaya desk) --}}
        .sheet { display: table; width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 8mm 0; }
        .copy { display: table-cell; width: 50%; vertical-align: top; border: 1.5px solid #0f172a; padding: 8px 12px; }
        .header { text-align: center; margin-bottom: 5px; }
        .header img { width: 38px; height: 38px; object-fit: contain; vertical-align: middle; margin-right: 6px; }
        .header .org-name { font-size: 13.5px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; }
        .header .org-sub { font-size: 8.5px; color: #475569; margin-top: 2px; }
        .header .org-contact { font-size: 8px; color: #64748b; margin-top: 1px; }
        h1 {
            text-align: center; font-size: 11.5px; font-weight: bold; margin: 6px 0 8px;
            border-top: 1px solid #0f172a; border-bottom: 1px solid #0f172a; padding: 3px 0;
            letter-spacing: 0.2px; text-transform: uppercase;
        }
        .field { margin-bottom: 5px; font-size: 10px; line-height: 14px; }
        .field .label { font-weight: bold; }
        .field .fill { display: inline-block; border-bottom: 1px solid #475569; min-height: 13px; padding: 0 4px; }
        .field-value { min-width: 60%; }
        .item-box { border: 1px solid #94a3b8; border-radius: 3px; padding: 5px 8px; margin-bottom: 6px; background-color: #fafbfc; }
        .item-box .item-line { margin-bottom: 4px; font-size: 9.5px; }
        .item-box .item-line:last-child { margin-bottom: 0; }
        .sign-block { margin-top: 8px; font-size: 9.5px; }
        .sign-block .line { margin-bottom: 6px; }
        .sign-block .fill { display: inline-block; border-bottom: 1px solid #94a3b8; min-width: 55%; min-height: 12px; }
    </style>
</head>
<body>
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
                    <span class="fill" style="min-width: 140px;">{{ $date ?? '' }}</span>
                </div>
                <div class="field">
                    <span class="label">Name of the Student:</span>
                    <span class="fill field-value" style="display:inline-block; min-width: 65%;">{{ $studentName ?? '' }}</span>
                </div>
                <div class="field">
                    <span class="label">Roll No:</span>
                    <span class="fill" style="min-width: 80px;">{{ $rollNo ?? '' }}</span>
                    &nbsp;&nbsp;&nbsp;&nbsp;<span class="label">Category:</span>
                    <span class="fill" style="min-width: 100px;">{{ $category ?? '' }}</span>
                </div>
                <div class="field">
                    <span class="label">School Name:</span>
                    <span class="fill field-value" style="display:inline-block; min-width: 70%;">{{ $schoolName ?? '' }}</span>
                </div>

                @foreach($items as $item)
                    <div class="item-box">
                        <div class="item-line">
                            <span class="label">Item Name:</span>
                            <span class="fill" style="min-width: 65%;">{{ $item['title'] ?? '' }}</span>
                        </div>
                        <div class="item-line">
                            <span class="label">Room/Stage No:</span>
                            <span class="fill" style="min-width: 90px;">{{ $item['stage'] ?? '' }}</span>
                            &nbsp;&nbsp;&nbsp;&nbsp;<span class="label">Time:</span>
                            <span class="fill" style="min-width: 80px;">{{ $item['time'] ?? '' }}</span>
                        </div>
                    </div>
                @endforeach

                <div class="sign-block">
                    <div class="line">Name &amp; Signature of Team Manager: <span class="fill"></span></div>
                    <div class="line">Remarks of Sahodaya Official: <span class="fill" style="display:block; min-height: 20px; width: 100%; margin-top: 2px;"></span></div>
                    <div class="line">Signature of Sahodaya Official: <span class="fill"></span></div>
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
