<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $label }} — {{ $programTitle }}</title>
    <style>
        @page { margin: 28px; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f2744;
            background: #fff;
        }
        .poster {
            border: 2px solid #0f2744;
            min-height: 980px;
            position: relative;
            overflow: hidden;
        }
        .header {
            background: #0f2744;
            color: #fff;
            padding: 22px 28px 20px;
            border-bottom: 5px solid #1e5a9e;
        }
        .header-row {
            width: 100%;
        }
        .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            vertical-align: middle;
        }
        .logo-cell {
            width: 88px;
            vertical-align: middle;
        }
        .org-cell {
            vertical-align: middle;
            padding-left: 14px;
        }
        .org-name {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: 0.2px;
        }
        .org-tag {
            margin-top: 6px;
            font-size: 11px;
            color: #bad2eb;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .body {
            padding: 36px 40px 48px;
            text-align: center;
        }
        .badge {
            display: inline-block;
            background: #1e5a9e;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 7px 16px;
            margin-bottom: 16px;
        }
        .program-title {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.3;
            color: #0f2744;
            margin: 0 auto 12px;
            max-width: 520px;
        }
        .meta {
            font-size: 12px;
            color: #334155;
            line-height: 1.5;
            margin-bottom: 4px;
        }
        .qr-wrap {
            margin: 28px auto 18px;
            width: 320px;
            padding: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .qr-wrap img {
            width: 280px;
            height: 280px;
            display: block;
            margin: 0 auto;
        }
        .instruction {
            font-size: 14px;
            font-weight: 700;
            color: #1e5a9e;
            margin-top: 8px;
        }
        .url {
            margin-top: 12px;
            font-size: 8px;
            color: #64748b;
            word-break: break-all;
            overflow-wrap: anywhere;
            line-height: 1.5;
            max-width: 460px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 12px;
        }
        .url span {
            display: block;
        }
        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 18px;
            background: #0f2744;
        }
    </style>
</head>
<body>
    <div class="poster">
        <div class="header">
            <table class="header-row" cellpadding="0" cellspacing="0">
                <tr>
                    @if(!empty($logoSrc))
                        <td class="logo-cell">
                            <img class="logo" src="{{ $logoSrc }}" alt="{{ $orgName }}">
                        </td>
                    @endif
                    <td class="org-cell">
                        <div class="org-name">{{ $orgName }}</div>
                        <div class="org-tag">Teacher Training</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="body">
            <div class="badge">{{ $label }}</div>
            <div class="program-title">{{ $programTitle }}</div>
            @if(!empty($dates))
                <div class="meta">{{ $dates }}</div>
            @endif
            @if(!empty($venue))
                <div class="meta">{{ $venue }}</div>
            @endif

            <div class="qr-wrap">
                <img src="{{ $qrDataUri }}" alt="QR code">
            </div>

            <div class="instruction">{{ $instruction }}</div>
            <div class="url">
                @foreach(str_split((string) $url, 46) as $chunk)
                    <span>{{ $chunk }}</span>
                @endforeach
            </div>
        </div>

        <div class="footer"></div>
    </div>
</body>
</html>
