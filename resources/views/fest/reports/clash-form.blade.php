<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — Clash Form</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 7mm;
        }
        * { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 11px;
            color: #0f172a;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        {{-- Two identical copies side by side spanning the full A4 landscape sheet --}}
        .sheet-table {
            width: 100%;
            height: 196mm;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }
        .copy-cell {
            width: 48.5%;
            height: 196mm;
            vertical-align: top;
            border: 2px solid #0f172a;
            border-radius: 4px;
            padding: 12px 16px;
            background: #ffffff;
        }
        .copy-divider {
            width: 3%;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
        }
        .header img {
            max-width: 48px;
            max-height: 48px;
            object-fit: contain;
            vertical-align: middle;
            margin-bottom: 3px;
        }
        .header .org-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            line-height: 1.2;
        }
        .header .org-sub {
            font-size: 9.5px;
            color: #475569;
            margin-top: 3px;
            font-weight: 500;
        }
        .header .org-contact {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }
        .banner {
            background: #0f172a;
            color: #ffffff;
            text-align: center;
            font-size: 12.5px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 5px 8px;
            border-radius: 3px;
            margin: 8px 0 10px;
        }
        .field-group {
            margin-bottom: 8px;
        }
        .field-group table {
            width: 100%;
            border-collapse: collapse;
        }
        .field-group td {
            padding-bottom: 6px;
            vertical-align: middle;
        }
        .label {
            font-weight: 700;
            color: #1e293b;
            font-size: 11px;
        }
        .value {
            display: inline-block;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 1px solid #64748b;
            min-height: 18px;
            padding: 1px 6px;
            font-size: 11.5px;
        }
        .items-heading {
            font-size: 11px;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 10px 0 6px;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 3px;
        }
        .item-box {
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 7px;
            background-color: #f8fafc;
        }
        .item-box .item-line {
            font-size: 11px;
            line-height: 1.3;
        }
        .item-box .item-title {
            font-weight: 800;
            font-size: 12px;
            color: #0f172a;
        }
        .sign-section {
            margin-top: 10px;
            font-size: 10.5px;
        }
        .sign-section .line {
            margin-bottom: 8px;
        }
        .sign-section .fill-line {
            display: inline-block;
            border-bottom: 1px solid #475569;
            min-width: 50%;
            min-height: 16px;
        }
        .remarks-box {
            border: 1.5px dashed #94a3b8;
            border-radius: 4px;
            background: #ffffff;
            min-height: 55px;
            margin-top: 4px;
            padding: 6px;
        }
        .copy-footer {
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px dashed #cbd5e1;
            text-align: right;
            font-size: 9px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
    </style>
</head>
<body>
    <table class="sheet-table" cellpadding="0" cellspacing="0">
        <tr>
            @for ($copy = 0; $copy < 2; $copy++)
                <td class="copy-cell">
                    <div class="header">
                        @if(!empty($logoSrc))
                            <div><img src="{{ $logoSrc }}" alt="Logo"></div>
                        @endif
                        <div class="org-name">{{ $orgName ?? 'Sahodaya' }}</div>
                    </div>

                    <div class="banner">
                        Off Stage / Stage Events — Clash Form {{ $year ?? now()->format('Y') }}
                    </div>

                    <div class="field-group">
                        <table>
                            <tr>
                                <td style="width: 52%;">
                                    <span class="label">Date:</span>
                                    <span class="value" style="min-width: 100px;">{{ $date ?? '' }}</span>
                                </td>
                                <td style="width: 48%;">
                                    <span class="label">Roll / Fest ID:</span>
                                    <span class="value" style="min-width: 70px;">{{ $rollNo ?? '' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <span class="label">Name of Student:</span>
                                    <span class="value" style="min-width: 68%;">{{ $studentName ?? '' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <span class="label">Category / Group:</span>
                                    <span class="value" style="min-width: 67%;">{{ $category ?? '' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <span class="label">School Name:</span>
                                    <span class="value" style="min-width: 72%;">{{ $schoolName ?? '' }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="items-heading">Overlapping / Clashing Events</div>

                    @foreach($items as $item)
                        <div class="item-box">
                            <div class="item-line">
                                <span class="label">Item:</span>
                                <span class="value item-title" style="min-width: 70%; border-bottom: {{ empty($item['title']) ? '1px solid #475569' : 'none' }};">
                                    {{ $item['title'] ?? '' }}
                                </span>
                            </div>
                            <div class="item-line" style="margin-top: 4px;">
                                <span class="label">Stage / Room:</span>
                                <span class="value" style="min-width: 90px; border-bottom: {{ empty($item['stage']) ? '1px solid #475569' : 'none' }};">
                                    {{ $item['stage'] ?? '' }}
                                </span>
                                &nbsp;&nbsp;&nbsp;&nbsp;
                                <span class="label">Scheduled Time:</span>
                                <span class="value" style="min-width: 140px; border-bottom: {{ empty($item['time']) ? '1px solid #475569' : 'none' }};">
                                    {{ $item['time'] ?? '' }}
                                </span>
                            </div>
                        </div>
                    @endforeach

                    <div class="sign-section">
                        <div class="line">
                            <span class="label">Name &amp; Signature of Team Manager:</span>
                            <span class="fill-line" style="min-width: 40%;"></span>
                        </div>
                        <div class="line">
                            <span class="label">Remarks / Action by Sahodaya Official:</span>
                            <div class="remarks-box"></div>
                        </div>
                        <div class="line" style="margin-top: 6px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 65%;">
                                        <span class="label">Signature of Official:</span>
                                        <span class="fill-line" style="min-width: 110px;"></span>
                                    </td>
                                    <td style="width: 35%; text-align: right;">
                                        <span class="label">Time:</span>
                                        <span class="fill-line" style="min-width: 55px;"></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="copy-footer">
                        {{ $copy === 0 ? '[ Copy 1: Team Manager ]' : '[ Copy 2: Sahodaya Official Desk ]' }}
                    </div>
                </td>
                @if($copy === 0)
                    <td class="copy-divider"></td>
                @endif
            @endfor
        </tr>
    </table>
</body>
</html>
