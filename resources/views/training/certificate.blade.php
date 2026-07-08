<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Participation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Times New Roman", Times, serif; background: #fff; color: #1e293b; }
        .page {
            width: 842px;
            min-height: 595px;
            margin: 0 auto;
            position: relative;
            border: 10px double #1e3a8a;
            padding: 40px 52px 56px;
        }
        .corner { position: absolute; width: 48px; height: 48px; border-color: #c7a84a; }
        .corner-tl { top: 10px; left: 10px; border-top: 3px solid; border-left: 3px solid; }
        .corner-tr { top: 10px; right: 10px; border-top: 3px solid; border-right: 3px solid; }
        .corner-bl { bottom: 10px; left: 10px; border-bottom: 3px solid; border-left: 3px solid; }
        .corner-br { bottom: 10px; right: 10px; border-bottom: 3px solid; border-right: 3px solid; }
        .header { text-align: center; margin-bottom: 8px; }
        .logo { max-height: 72px; max-width: 120px; margin: 0 auto 6px; display: block; }
        .org-name { font-size: 18px; font-weight: 700; color: #1e3a8a; letter-spacing: 1px; text-transform: uppercase; }
        .program-subtitle { font-size: 13px; font-weight: 600; color: #334155; margin-top: 4px; }
        .cert-title {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            color: #1e3a8a;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 16px 0 14px;
            border-top: 2px solid #c7a84a;
            border-bottom: 2px solid #c7a84a;
            padding: 6px 0;
        }
        .body-text { text-align: center; font-size: 14px; line-height: 1.85; color: #334155; max-width: 680px; margin: 0 auto; }
        .body-text p { margin-bottom: 10px; }
        .seal-wrap { text-align: center; margin: 12px 0; }
        .seal { max-height: 64px; opacity: 0.9; }
        .date-line { margin-top: 14px; font-size: 13px; }
        .footer {
            margin-top: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 24px 32px;
        }
        .sign-block { text-align: center; min-width: 140px; max-width: 180px; }
        .sign-img { max-height: 40px; max-width: 120px; margin-bottom: 2px; }
        .sign-line { border-top: 1px solid #0f172a; width: 140px; margin: 0 auto 4px; min-height: 1px; }
        .sign-name { font-size: 11px; font-weight: 600; color: #0f172a; }
        .sign-label { font-size: 10px; color: #64748b; margin-top: 2px; }
        .uuid { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 16px; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
@if(!empty($isSample))
    <p class="no-print" style="text-align:center;font-family:system-ui,sans-serif;font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fcd34d;padding:10px 16px;margin:16px auto;max-width:842px;border-radius:8px;">
        <strong>Sample certificate</strong> — for client demo only. Configure layout under Certificate Templates.
    </p>
@endif
    @php
        $title = $template?->title ?? 'Certificate of Participation';
        $body = $template?->body ?? \App\Models\CertificateTemplate::defaultTrainingBody();
        foreach ($fieldValues as $key => $value) {
            $body = str_replace('{'.$key.'}', e($value), $body);
        }
        $paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/', $body)));
    @endphp

    <div class="page">
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>

        <div class="header">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="" class="logo">
            @endif
            <p class="org-name">{{ $fieldValues['sahodaya_name'] ?? $sahodaya->name }}</p>
            @if(!empty($fieldValues['program_title']))
                <p class="program-subtitle">{{ $fieldValues['program_title'] }}</p>
            @endif
        </div>

        <div class="cert-title">{{ $title }}</div>

        <div class="body-text">
            @foreach($paragraphs as $paragraph)
                <p>{!! nl2br($paragraph) !!}</p>
            @endforeach
            @if(!empty($fieldValues['days_attended']) && (int) $fieldValues['days_attended'] > 0)
                <p class="date-line"><strong>Days attended:</strong> {{ $fieldValues['days_attended'] }} of {{ $fieldValues['total_days'] ?? $fieldValues['days_attended'] }}</p>
            @endif
            <p class="date-line"><strong>Date:</strong> {{ $fieldValues['certificate_date'] ?? now()->format('j F Y') }}</p>
        </div>

        @if(!empty($sealUrl))
            <div class="seal-wrap">
                <img src="{{ $sealUrl }}" alt="" class="seal">
            </div>
        @endif

        <div class="footer">
            @foreach($signatories ?? [] as $signatory)
                <div class="sign-block">
                    @if(!empty($signatory['signature_url']))
                        <img src="{{ $signatory['signature_url'] }}" alt="" class="sign-img">
                    @else
                        <div class="sign-line"></div>
                    @endif
                    @if(!empty($signatory['name']))
                        <p class="sign-name">{{ $signatory['name'] }}</p>
                    @endif
                    <p class="sign-label">{{ $signatory['designation'] ?? '' }}</p>
                </div>
            @endforeach
        </div>

        <p class="uuid">Verification: {{ $certificate->verification_uuid }}</p>
    </div>

    <p class="no-print" style="text-align:center;margin-top:16px;font-family:sans-serif;font-size:13px;">
        <button type="button" onclick="window.print()" style="padding:8px 20px;background:#1e3a8a;color:#fff;border:none;border-radius:6px;cursor:pointer;">Print Certificate</button>
    </p>
</body>
</html>
