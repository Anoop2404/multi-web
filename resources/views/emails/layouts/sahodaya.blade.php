<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? $sahodayaName ?? config('app.name') }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, p, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; display: block; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #eef6ff; }
        .email-body { font-family: 'Segoe UI', Inter, Arial, sans-serif; font-size: 15px; line-height: 1.65; color: #334155; }
        .email-body h1 { margin: 0 0 8px; font-size: 22px; line-height: 1.3; color: #ffffff; font-weight: 700; }
        .email-body h2 { margin: 0 0 12px; font-size: 18px; line-height: 1.35; color: #041525; font-weight: 700; }
        .email-body p { margin: 0 0 14px; }
        .email-body p:last-child { margin-bottom: 0; }
        .email-body a { color: #0f3d7a; text-decoration: underline; }
        .muted { color: #64748b; font-size: 13px; }
        @media only screen and (max-width: 620px) {
            .wrapper { width: 100% !important; }
            .content-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .header-pad { padding-left: 20px !important; padding-right: 20px !important; }
        }
    </style>
</head>
<body class="email-body" style="margin:0;padding:0;background-color:#eef6ff;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef6ff;">
    <tr>
        <td align="center" style="padding:28px 12px;">
            <table role="presentation" class="wrapper" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

                {{-- Header --}}
                <tr>
                    <td class="header-pad" style="background:linear-gradient(135deg,#041525 0%,#0f3d7a 100%);border-radius:16px 16px 0 0;padding:28px 32px 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                @if(!empty($logoUrl))
                                <td width="56" valign="middle" style="padding-right:14px;">
                                    <img src="{{ $logoUrl }}" alt="{{ $sahodayaName ?? 'Logo' }}" width="52" height="52" style="width:52px;height:52px;border-radius:50%;border:2px solid rgba(251,191,36,0.55);object-fit:cover;">
                                </td>
                                @endif
                                <td valign="middle">
                                    @if(!empty($headerEyebrow))
                                    <p style="margin:0 0 4px;font-size:10px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:#fbbf24;">{{ $headerEyebrow }}</p>
                                    @endif
                                    <h1 style="margin:0;font-size:22px;line-height:1.3;color:#ffffff;font-weight:700;">{{ $headerTitle ?? $sahodayaName ?? config('app.name') }}</h1>
                                    @if(!empty($headerSubtitle))
                                    <p style="margin:6px 0 0;font-size:13px;line-height:1.4;color:rgba(255,255,255,0.82);">{{ $headerSubtitle }}</p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Gold accent --}}
                <tr>
                    <td style="height:4px;background:linear-gradient(90deg,#fbbf24,#f59e0b);font-size:0;line-height:0;">&nbsp;</td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td class="content-pad" style="background:#ffffff;padding:32px;border-left:1px solid #dbeafe;border-right:1px solid #dbeafe;">
                        @yield('content')
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td class="content-pad" style="background:#f8fafc;border:1px solid #dbeafe;border-top:none;border-radius:0 0 16px 16px;padding:20px 32px 24px;">
                        <p style="margin:0 0 6px;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                            {{ $footerNote ?? ('This email was sent by '.($sahodayaName ?? config('app.name')).'.') }}
                        </p>
                        @if(!empty($portalUrl))
                        <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                            <a href="{{ $portalUrl }}" style="color:#0f3d7a;text-decoration:none;font-weight:600;">{{ parse_url($portalUrl, PHP_URL_HOST) ?: $portalUrl }}</a>
                        </p>
                        @endif
                        @if(!empty($contactEmail))
                        <p style="margin:8px 0 0;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                            Questions? <a href="mailto:{{ $contactEmail }}" style="color:#0f3d7a;text-decoration:none;">{{ $contactEmail }}</a>
                        </p>
                        @endif
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
