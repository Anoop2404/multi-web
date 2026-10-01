<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->title ?? 'Food Coupons' }} — 10 per Sheet</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page {
            size: A4 portrait;
            margin: 6mm 7mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #0f172a;
            font-size: 7px;
            background: #ffffff;
        }
        .page {
            width: 100%;
            height: 284mm;
            page-break-inside: avoid;
        }
        .page-break {
            page-break-after: always;
        }
        .coupon-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4mm 5.5mm;
        }
        .coupon-cell {
            width: 50%;
            height: 40.63mm;
            vertical-align: top;
            padding: 0;
        }
        .coupon-card {
            position: relative;
            width: 95mm;
            height: 40.63mm;
            max-height: 40.63mm;
            overflow: hidden;
            background: #ffffff;
            border-radius: 2mm;
        }

        /* Border when no custom background image is used */
        .coupon-card--bordered {
            border: 1px dashed #94a3b8;
        }

        .coupon-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        /* ------------------------------------------------------------------ */
        /* STUB AREA (Right Section)                                          */
        /* - Serial Code: OUTSIDE above the box                               */
        /* - QR Code: FULLY INSIDE the designated square box                  */
        /* - Decoded QR Token: OUTSIDE below the box                          */
        /* ------------------------------------------------------------------ */
        .stub-serial-outside {
            position: absolute;
            left: 68%;
            top: 7%;
            width: 28%;
            text-align: center;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            font-size: 7.8pt;
            color: #0f172a;
            letter-spacing: 0.5px;
            line-height: 1.1;
            z-index: 3;
            white-space: nowrap;
        }

        .stub-qr-box {
            position: absolute;
            left: 70.41%;
            top: 22.60%;
            width: 23.54%;
            height: 55.48%;
            z-index: 3;
            text-align: center;
            overflow: hidden;
            padding: 1mm;
        }

        .stub-qr-img {
            width: 20mm;
            height: 20mm;
            max-width: 100%;
            max-height: 100%;
            display: inline-block;
            vertical-align: middle;
        }

        .stub-decoded-outside {
            position: absolute;
            left: 68%;
            top: 79.5%;
            width: 28%;
            text-align: center;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            font-size: 6.2pt;
            color: #0f172a;
            letter-spacing: 0.8px;
            line-height: 1.1;
            z-index: 3;
            white-space: nowrap;
        }

        /* ------------------------------------------------------------------ */
        /* MAIN VOUCHER DETAILS (Left Section)                                */
        /* ------------------------------------------------------------------ */
        .left-details-container {
            position: absolute;
            left: 4.5%;
            top: 46.5%;
            width: 58%;
            height: 24.5%;
            z-index: 3;
            overflow: hidden;
        }

        .left-meta-row {
            line-height: 1;
            margin-bottom: 0.8mm;
            white-space: nowrap;
        }

        .meal-pill {
            display: inline-block;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 5.2pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 0.5mm 1.8mm;
            border-radius: 0.8mm;
            letter-spacing: 0.3px;
            line-height: 1;
            vertical-align: middle;
        }
        .meal-pill--breakfast { background: #f59e0b; color: #ffffff; }
        .meal-pill--lunch     { background: #10b981; color: #ffffff; }
        .meal-pill--dinner    { background: #4f46e5; color: #ffffff; }
        .meal-pill--snacks    { background: #ec4899; color: #ffffff; }
        .meal-pill--tea       { background: #ea580c; color: #ffffff; }
        .meal-pill--other     { background: #64748b; color: #ffffff; }

        .meta-text {
            font-size: 5.2pt;
            color: #334155;
            margin-left: 1.5mm;
            vertical-align: middle;
        }
        .meta-text strong {
            color: #0f172a;
        }

        .left-school-row {
            font-size: 5.5pt;
            font-weight: bold;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        .left-extra-pill {
            display: inline-block;
            background: #fffbeb;
            color: #b45309;
            border: 0.5px solid #fde68a;
            font-size: 4.8pt;
            font-weight: bold;
            padding: 0.3mm 1.5mm;
            border-radius: 0.8mm;
            text-transform: uppercase;
            margin-left: 1.5mm;
        }

        /* Serial badge beside "FOOD COUPON" */
        .coupon-serial-badge {
            position: absolute;
            left: 51.5%;
            top: 72.5%;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            font-size: 7.2pt;
            color: #1e3a8a;
            background: #dbeafe;
            border: 0.5px solid #93c5fd;
            padding: 0.5mm 1.8mm;
            border-radius: 1mm;
            white-space: nowrap;
            z-index: 3;
            line-height: 1.1;
        }

        /* Fallback elements when NO template background exists */
        .fallback-header {
            position: absolute;
            left: 4.5%;
            top: 4%;
            width: 59%;
            z-index: 3;
        }
        .fallback-event-title {
            font-size: 7pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .fallback-sahodaya {
            font-size: 5.5pt;
            color: #64748b;
        }
        .fallback-title {
            position: absolute;
            left: 4.5%;
            top: 72%;
            font-size: 11pt;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.5px;
            z-index: 3;
        }
        .fallback-stub-border {
            position: absolute;
            left: 67%;
            top: 0;
            bottom: 0;
            border-left: 0.5px dashed #94a3b8;
            z-index: 2;
        }
        .fallback-box-border {
            position: absolute;
            left: 70.41%;
            top: 22.60%;
            width: 23.54%;
            height: 55.48%;
            border: 1px solid #0f172a;
            z-index: 2;
        }
    </style>
</head>
<body>
    @php
        $pages = array_chunk($coupons, 10);
        $totalPages = count($pages);
    @endphp

    @foreach($pages as $pageIndex => $pageCoupons)
    <div class="page {{ $pageIndex < $totalPages - 1 ? 'page-break' : '' }}">
        <table class="coupon-grid">
            @foreach(array_chunk($pageCoupons, 2) as $row)
            <tr>
                @foreach($row as $c)
                <td class="coupon-cell">
                    <div class="coupon-card {{ empty($bgDataUri) ? 'coupon-card--bordered' : '' }}">
                        @if(!empty($bgDataUri))
                            <img src="{{ $bgDataUri }}" class="coupon-bg" alt="" />
                        @else
                            <div class="fallback-header">
                                <div class="fallback-event-title">{{ $event->title ?? 'Sahodaya Event' }}</div>
                                <div class="fallback-sahodaya">{{ $sahodaya->name ?? 'Sahodaya' }}</div>
                            </div>
                            <div class="fallback-title">FOOD COUPON</div>
                            <div class="fallback-stub-border"></div>
                            <div class="fallback-box-border"></div>
                        @endif

                        {{-- Left Portion: Dynamic details (Meal, Date, Entitlement, School) --}}
                        <div class="left-details-container">
                            <div class="left-meta-row">
                                <span class="meal-pill meal-pill--{{ $c['meal_type'] }}">{{ ucfirst($c['meal_type']) }}</span>
                                <span class="meta-text"><strong>Date:</strong> {{ $c['formatted_date'] }}</span>
                                <span class="meta-text"><strong>Qty:</strong> {{ $c['head_count'] ?? 1 }}</span>
                            </div>
                            <div class="left-school-row">
                                <span>{{ $c['school_name'] }}</span>
                                @if(!empty($c['is_extra']))
                                    <span class="left-extra-pill">EXTRA BUFFER</span>
                                @endif
                            </div>
                        </div>

                        {{-- Serial No Badge beside "FOOD COUPON" --}}
                        <div class="coupon-serial-badge">
                            {{ $c['coupon_code'] }}
                        </div>

                        {{-- Stub Area: Serial Code OUTSIDE above box, QR Code FULLY INSIDE box, Decoded Token OUTSIDE below box --}}
                        <div class="stub-serial-outside">
                            {{ $c['coupon_code'] }}
                        </div>

                        <div class="stub-qr-box">
                            @if(!empty($c['qr_src']))
                                <img src="{{ $c['qr_src'] }}" class="stub-qr-img" alt="QR" />
                            @endif
                        </div>

                        <div class="stub-decoded-outside">
                            {{ $c['qr_token'] }}
                        </div>
                    </div>
                </td>
                @endforeach
                @if(count($row) === 1)
                <td class="coupon-cell"></td>
                @endif
            </tr>
            @endforeach
        </table>
    </div>
    @endforeach
</body>
</html>
