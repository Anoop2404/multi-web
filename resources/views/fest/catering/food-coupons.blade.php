<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->title ?? 'Food Coupons' }} — 10 per Sheet</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page {
            size: A4 portrait;
            margin: 5mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #0f172a;
            font-size: 8px;
            background: #ffffff;
        }
        .page {
            width: 100%;
            height: 285mm;
            page-break-inside: avoid;
        }
        .page-break {
            page-break-after: always;
        }
        .coupon-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2.5mm 2mm;
        }
        .coupon-cell {
            width: 50%;
            height: 52mm;
            vertical-align: top;
            padding: 0;
        }
        .coupon-card {
            position: relative;
            width: 96mm;
            height: 52mm;
            max-height: 52mm;
            border: 1px dashed #94a3b8;
            border-radius: 3mm;
            overflow: hidden;
            background: #ffffff;
        }
        .coupon-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        .coupon-inner {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 100%;
            padding: 2.5mm;
        }
        /* Top Banner */
        .coupon-header {
            display: table;
            width: 100%;
            padding-bottom: 1.5mm;
            border-bottom: 0.5px solid #e2e8f0;
        }
        .coupon-org {
            display: table-cell;
            vertical-align: middle;
            font-size: 6.5px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 60mm;
        }
        .coupon-meal-badge {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }
        .meal-pill {
            display: inline-block;
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 1.2px 5px;
            border-radius: 2mm;
            letter-spacing: 0.4px;
        }
        .meal-pill--breakfast { background: #fef3c7; color: #b45309; border: 0.5px solid #f59e0b; }
        .meal-pill--lunch     { background: #ecfdf5; color: #047857; border: 0.5px solid #10b981; }
        .meal-pill--dinner    { background: #e0e7ff; color: #4338ca; border: 0.5px solid #6366f1; }
        .meal-pill--snacks    { background: #fdf2f8; color: #9d174d; border: 0.5px solid #ec4899; }
        .meal-pill--other     { background: #f1f5f9; color: #334155; border: 0.5px solid #94a3b8; }

        /* Main Content */
        .coupon-main {
            display: table;
            width: 100%;
            margin-top: 1.5mm;
            height: 38mm;
        }
        .coupon-left {
            display: table-cell;
            vertical-align: top;
            width: 68mm;
            padding-right: 1.5mm;
        }
        .coupon-code-row {
            margin-bottom: 1mm;
        }
        .coupon-code-label {
            font-size: 5.5px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .coupon-code-val {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'DejaVu Sans Mono', monospace;
            letter-spacing: 0.5px;
            line-height: 1.1;
        }
        .coupon-event {
            font-size: 7px;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 66mm;
            margin-bottom: 1mm;
        }
        .coupon-school {
            font-size: 7px;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 66mm;
            margin-bottom: 0.8mm;
        }
        .coupon-meta {
            font-size: 6px;
            color: #64748b;
            line-height: 1.3;
        }
        .coupon-right {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            width: 24mm;
            padding-left: 1mm;
            border-left: 0.5px dashed #cbd5e1;
        }
        .qr-img {
            width: 19mm;
            height: 19mm;
            display: block;
            margin: 0 auto;
        }
        .decoded-label {
            font-size: 5px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 1mm;
        }
        .decoded-val {
            font-size: 6.5px;
            font-weight: bold;
            font-family: 'DejaVu Sans Mono', monospace;
            color: #0f172a;
            letter-spacing: 0.5px;
            text-align: center;
            word-break: break-all;
        }
        .extra-badge {
            display: inline-block;
            background: #fffbeb;
            color: #b45309;
            border: 0.5px solid #fde68a;
            font-size: 5.5px;
            font-weight: bold;
            padding: 0.5px 3px;
            border-radius: 1mm;
            text-transform: uppercase;
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
                    <div class="coupon-card">
                        @if(!empty($bgDataUri))
                            <img src="{{ $bgDataUri }}" class="coupon-bg" alt="" />
                        @endif

                        <div class="coupon-inner">
                            <div class="coupon-header">
                                <span class="coupon-org">{{ $sahodaya->name ?? 'Sahodaya Event' }}</span>
                                <span class="coupon-meal-badge">
                                    <span class="meal-pill meal-pill--{{ $c['meal_type'] }}">{{ ucfirst($c['meal_type']) }}</span>
                                </span>
                            </div>

                            <div class="coupon-main">
                                <div class="coupon-left">
                                    <div class="coupon-code-row">
                                        <div class="coupon-code-label">Serialized Code</div>
                                        <div class="coupon-code-val">{{ $c['coupon_code'] }}</div>
                                    </div>

                                    <div class="coupon-event">{{ $event->title }}</div>
                                    <div class="coupon-school">
                                        {{ $c['school_name'] }}
                                        @if($c['is_extra'])
                                            <span class="extra-badge">Extra</span>
                                        @endif
                                    </div>

                                    <div class="coupon-meta">
                                        <div><strong>Date:</strong> {{ $c['formatted_date'] }}</div>
                                        <div><strong>Entitlement:</strong> {{ $c['head_count'] ?? 1 }} Person ({{ ucfirst($c['meal_type']) }})</div>
                                    </div>
                                </div>

                                <div class="coupon-right">
                                    @if(!empty($c['qr_src']))
                                        <img src="{{ $c['qr_src'] }}" class="qr-img" alt="QR" />
                                    @endif
                                    <div class="decoded-label">Decoded Value</div>
                                    <div class="decoded-val">{{ $c['qr_token'] }}</div>
                                </div>
                            </div>
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
