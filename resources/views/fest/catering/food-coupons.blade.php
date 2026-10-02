<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->title ?? 'Food Coupons' }} — 10 per Sheet</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page {
            size: A4 portrait;
            margin: 4mm 6mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #0f172a;
            font-size: 7px;
            background: #ffffff;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page {
            width: 100%;
            page-break-inside: avoid;
            break-inside: avoid;
            margin: 0 auto;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
            break-after: page;
        }
        .coupon-grid {
            width: 194mm;
            margin: 0 auto;
            border-collapse: separate;
            border-spacing: 4mm 2.2mm;
        }
        .coupon-cell {
            width: 95mm;
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
            object-fit: cover;
        }

        /* ------------------------------------------------------------------ */
        /* STUB AREA (Right Section)                                          */
        /* ------------------------------------------------------------------ */
        .stub-serial-outside {
            position: absolute;
            text-align: center;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            letter-spacing: 0.5px;
            line-height: 1.1;
            z-index: 3;
            white-space: nowrap;
        }

        .stub-qr-box {
            position: absolute;
            z-index: 3;
            text-align: center;
            overflow: hidden;
            padding: 0.5mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stub-qr-img {
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            display: block;
            margin: 0 auto;
            object-fit: contain;
        }

        .stub-decoded-outside {
            position: absolute;
            text-align: center;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            letter-spacing: 0.8px;
            line-height: 1.1;
            z-index: 3;
            white-space: nowrap;
        }

        /* ------------------------------------------------------------------ */
        /* MAIN VOUCHER DETAILS (Left Section)                                */
        /* ------------------------------------------------------------------ */
        .meal-pill {
            position: absolute;
            display: inline-block;
            font-family: 'DejaVu Sans', sans-serif;
            font-weight: bold;
            text-transform: uppercase;
            padding: 0.5mm 1.8mm;
            border-radius: 0.8mm;
            letter-spacing: 0.3px;
            line-height: 1;
            vertical-align: middle;
            z-index: 3;
            white-space: nowrap;
        }
        .meal-pill--breakfast { background: #f59e0b; color: #ffffff; }
        .meal-pill--lunch     { background: #10b981; color: #ffffff; }
        .meal-pill--dinner    { background: #4f46e5; color: #ffffff; }
        .meal-pill--snacks    { background: #ec4899; color: #ffffff; }
        .meal-pill--tea       { background: #ea580c; color: #ffffff; }
        .meal-pill--other     { background: #64748b; color: #ffffff; }

        .meta-text {
            position: absolute;
            z-index: 3;
            white-space: nowrap;
            line-height: 1.2;
        }
        .meta-text strong {
            color: #0f172a;
        }

        .left-school-row {
            position: absolute;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
            z-index: 3;
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
            vertical-align: middle;
        }

        /* Serial badge beside "FOOD COUPON" */
        .coupon-serial-badge {
            position: absolute;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            white-space: nowrap;
            z-index: 3;
            line-height: 1.1;
        }
        .coupon-serial-badge--pill {
            background: #dbeafe;
            border: 0.5px solid #93c5fd;
            padding: 0.4mm 1.6mm;
            border-radius: 0.8mm;
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
            left: 71.0%;
            top: 20.5%;
            width: 23.0%;
            height: 56.5%;
            border: 1px solid #0f172a;
            border-radius: 1mm;
            z-index: 2;
        }
    </style>
</head>
<body>
    @php
        $pages = array_chunk($coupons, 10);
        $totalPages = count($pages);
        $l = $layout ?? ($event ? $event->foodCouponLayout($sahodaya) : \App\Models\FestEvent::defaultFoodCouponLayout());
        $qb = $l['qr_box'] ?? [];
        $ss = $l['stub_serial'] ?? [];
        $st = $l['stub_token'] ?? [];
        $mb = $l['meal_badge'] ?? [];
        $dm = $l['date_meta'] ?? [];
        $sn = $l['school_name'] ?? [];
        $vs = $l['voucher_serial'] ?? [];
        $ft = $l['fallback_title'] ?? [];
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
                            @if(!empty($ft['show'] ?? true))
                            <div class="fallback-title" style="top: {{ $ft['top'] ?? 72.0 }}%; left: {{ $ft['left'] ?? 4.5 }}%;">FOOD COUPON</div>
                            @endif
                            <div class="fallback-stub-border"></div>
                            <div class="fallback-box-border"></div>
                        @endif

                        {{-- Left Side: Meal Badge --}}
                        @if(!empty($mb['show'] ?? true))
                        <span class="meal-pill meal-pill--{{ $c['meal_type'] }}"
                              style="top: {{ $mb['top'] ?? 47.5 }}%; left: {{ $mb['left'] ?? 4.5 }}%; font-size: {{ $mb['font_size'] ?? 5.2 }}pt;">
                            {{ ucfirst($c['meal_type']) }}
                        </span>
                        @endif

                        {{-- Left Side: Date & Quantity --}}
                        @if(!empty($dm['show'] ?? true))
                        <div class="meta-text"
                             style="top: {{ $dm['top'] ?? 47.5 }}%; left: {{ $dm['left'] ?? 21.0 }}%; font-size: {{ $dm['font_size'] ?? 5.2 }}pt; color: {{ $dm['color'] ?? '#334155' }};">
                            <strong>Date:</strong> {{ $c['formatted_date'] }} &nbsp; <strong>Qty:</strong> {{ $c['head_count'] ?? 1 }}
                        </div>
                        @endif

                        {{-- Left Side: School Name --}}
                        @if(!empty($sn['show'] ?? true))
                        <div class="left-school-row"
                             style="top: {{ $sn['top'] ?? 56.5 }}%; left: {{ $sn['left'] ?? 4.5 }}%; max-width: {{ $sn['max_width'] ?? 62.0 }}%; font-size: {{ $sn['font_size'] ?? 5.5 }}pt; color: {{ $sn['color'] ?? '#0f172a' }};">
                            <span>{{ $c['school_name'] }}</span>
                            @if(!empty($c['is_extra']))
                                <span class="left-extra-pill">EXTRA BUFFER</span>
                            @endif
                        </div>
                        @endif

                        {{-- Voucher Serial Code Badge beside "FOOD COUPON" --}}
                        @if(!empty($vs['show'] ?? true))
                        <div class="coupon-serial-badge {{ ($vs['style'] ?? 'pill') === 'pill' ? 'coupon-serial-badge--pill' : '' }}"
                             style="top: {{ $vs['top'] ?? 72.0 }}%; left: {{ $vs['left'] ?? 51.5 }}%; font-size: {{ $vs['font_size'] ?? 7.2 }}pt; color: {{ $vs['color'] ?? '#1e3a8a' }};">
                            {{ $c['coupon_code'] }}
                        </div>
                        @endif

                        {{-- Stub Area: Serial Code OUTSIDE above box --}}
                        @if(!empty($ss['show'] ?? true))
                        <div class="stub-serial-outside"
                             style="top: {{ $ss['top'] ?? 6.0 }}%; left: {{ $ss['left'] ?? 69.5 }}%; width: {{ $ss['width'] ?? 26.0 }}%; font-size: {{ $ss['font_size'] ?? 7.5 }}pt; color: {{ $ss['color'] ?? '#0f172a' }};">
                            {{ $c['coupon_code'] }}
                        </div>
                        @endif

                        {{-- Stub Area: QR Code FULLY INSIDE box --}}
                        <div class="stub-qr-box"
                             style="top: {{ $qb['top'] ?? 20.5 }}%; left: {{ $qb['left'] ?? 71.0 }}%; width: {{ $qb['width'] ?? 23.0 }}%; height: {{ $qb['height'] ?? 56.5 }}%; {{ !empty($qb['show_border']) ? 'border: 1px solid #0f172a; border-radius: 1mm;' : '' }}">
                            @if(!empty($c['qr_src']))
                                <img src="{{ $c['qr_src'] }}" class="stub-qr-img" alt="QR" />
                            @endif
                        </div>

                        {{-- Stub Area: Decoded Token OUTSIDE below box --}}
                        @if(!empty($st['show'] ?? true))
                        <div class="stub-decoded-outside"
                             style="top: {{ $st['top'] ?? 81.5 }}%; left: {{ $st['left'] ?? 69.5 }}%; width: {{ $st['width'] ?? 26.0 }}%; font-size: {{ $st['font_size'] ?? 6.2 }}pt; color: {{ $st['color'] ?? '#0f172a' }};">
                            {{ $c['qr_token'] }}
                        </div>
                        @endif
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
