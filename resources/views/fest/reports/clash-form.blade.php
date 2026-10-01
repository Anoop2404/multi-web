<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — Clash Form</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 8mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #0f172a;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }
        {{-- Two identical copies side by side spanning the full A4 landscape sheet --}}
        .sheet {
            display: table;
            width: 100%;
            height: 188mm;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 7mm 0;
        }
        .copy {
            display: table-cell;
            width: 50%;
            height: 188mm;
            vertical-align: top;
            border: 2px solid #0f172a;
            padding: 10px 14px;
            background: #ffffff;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
        }
        .header img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            vertical-align: middle;
            margin-bottom: 3px;
        }
        .header .org-name {
            font-size: 14.5px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header .org-sub {
            font-size: 8.5px;
            color: #475569;
            margin-top: 2px;
        }
        .header .org-contact {
            font-size: 8px;
            color: #64748b;
            margin-top: 1px;
        }
        .banner {
            background: #0f172a;
            color: #ffffff;
            text-align: center;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            padding: 4px 6px;
            border-radius: 2px;
            margin: 6px 0 8px;
        }
        .field-group {
            margin-bottom: 6px;
        }
        .field {
            margin-bottom: 5px;
            font-size: 10.5px;
        }
        .field .label {
            font-weight: 700;
            color: #1e293b;
        }
        .field .value {
            display: inline-block;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 1px solid #475569;
            min-height: 15px;
            padding: 0 4px;
        }
        .items-heading {
            font-size: 10px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 6px 0 4px;
        }
        .item-box {
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 6px;
            background-color: #f8fafc;
        }
        .item-box .item-line {
            margin-bottom: 3px;
            font-size: 10px;
        }
        .item-box .item-line:last-child {
            margin-bottom: 0;
        }
        .item-box .item-title {
            font-weight: 700;
            font-size: 10.5px;
            color: #0f172a;
        }
        .sign-section {
            margin-top: 8px;
            font-size: 9.5px;
        }
        .sign-section .line {
            margin-bottom: 6px;
        }
        .sign-section .fill-line {
            display: inline-block;
            border-bottom: 1px solid #64748b;
            min-width: 50%;
            min-height: 13px;
        }
        .remarks-box {
            border: 1px dashed #94a3b8;
            border-radius: 3px;
            background: #ffffff;
            min-height: 32px;
            margin-top: 2px;
            padding: 4px;
        }
        .copy-footer {
            margin-top: 8px;
            padding-top: 4px;
            border-top: 1px dotted #cbd5e1;
            text-align: right;
            font-size: 8px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="sheet">
        @for ($copy = 0; $copy < 2; $copy++)
            <div class="copy">
                <div class="header">
                    @if(!empty($logoSrc))
                        <div><img src="{{ $logoSrc }}" alt="Logo"></div>
                    @endif
                    <div class="org-name">{{ $orgName ?? 'Sahodaya' }}</div>
                    @if(!empty($orgSubtitle))
                        <div class="org-sub">{{ $orgSubtitle }}</div>
                    @endif
                    @if(!empty($orgContact))
                        <div class="org-contact">{{ $orgContact }}</div>
                    @endif
                </div>

                <div class="banner">
                    Off Stage / Stage Events — Clash Form {{ $year ?? now()->format('Y') }}
                </div>

                <div class="field-group">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 50%; padding-bottom: 4px;">
                                <span class="label">Date:</span>
                                <span class="value" style="min-width: 110px;">{{ $date ?? '' }}</span>
                            </td>
                            <td style="width: 50%; padding-bottom: 4px;">
                                <span class="label">Roll / Chest No:</span>
                                <span class="value" style="min-width: 70px;">{{ $rollNo ?? '' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-bottom: 4px;">
                                <span class="label">Name of Student:</span>
                                <span class="value" style="min-width: 72%;">{{ $studentName ?? '' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-bottom: 4px;">
                                <span class="label">Category / Group:</span>
                                <span class="value" style="min-width: 72%;">{{ $category ?? '' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-bottom: 4px;">
                                <span class="label">School Name:</span>
                                <span class="value" style="min-width: 76%;">{{ $schoolName ?? '' }}</span>
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
                        <div class="item-line" style="margin-top: 3px;">
                            <span class="label">Stage / Room:</span>
                            <span class="value" style="min-width: 100px; border-bottom: {{ empty($item['stage']) ? '1px solid #475569' : 'none' }};">
                                {{ $item['stage'] ?? '' }}
                            </span>
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <span class="label">Scheduled Time:</span>
                            <span class="value" style="min-width: 80px; border-bottom: {{ empty($item['time']) ? '1px solid #475569' : 'none' }};">
                                {{ $item['time'] ?? '' }}
                            </span>
                        </div>
                    </div>
                @endforeach

                <div class="sign-section">
                    <div class="line">
                        <span class="label">Name &amp; Signature of Team Manager:</span>
                        <span class="fill-line" style="min-width: 45%;"></span>
                    </div>
                    <div class="line">
                        <span class="label">Remarks / Action by Sahodaya Official:</span>
                        <div class="remarks-box"></div>
                    </div>
                    <div class="line" style="margin-top: 8px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="width: 65%;">
                                    <span class="label">Signature of Official:</span>
                                    <span class="fill-line" style="min-width: 120px;"></span>
                                </td>
                                <td style="width: 35%; text-align: right;">
                                    <span class="label">Time:</span>
                                    <span class="fill-line" style="min-width: 60px;"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="copy-footer">
                    {{ $copy === 0 ? '[ Copy 1: Team Manager ]' : '[ Copy 2: Sahodaya Official Desk ]' }}
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
