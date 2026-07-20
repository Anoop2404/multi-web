<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate — {{ $student?->name ?? 'Participant' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; background: #fff; color: #1e293b; margin: 0; }
        .page {
            width: 842px;
            min-height: 595px;
            margin: 0 auto;
            position: relative;
            border: 10px double #b45309;
            padding: 40px 52px 56px;
        }
        .page.has-background {
            border: none;
            padding: 0;
            width: 1123px;
            height: 794px;
            min-height: 794px;
            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;
            overflow: hidden;
        }
        .overlay-field { position: absolute; text-align: center; color: #1e293b; line-height: 1.45; word-wrap: break-word; }
        .overlay-field.recipient { color: #0f172a; }
        .overlay-field.body { line-height: 1.7; color: #334155; }
        .overlay-field.uuid { color: #94a3b8; letter-spacing: 0.02em; }
        .corner { position: absolute; width: 48px; height: 48px; border-color: #c7a84a; }
        .corner-tl { top: 10px; left: 10px; border-top: 3px solid; border-left: 3px solid; }
        .corner-tr { top: 10px; right: 10px; border-top: 3px solid; border-right: 3px solid; }
        .corner-bl { bottom: 10px; left: 10px; border-bottom: 3px solid; border-left: 3px solid; }
        .corner-br { bottom: 10px; right: 10px; border-bottom: 3px solid; border-right: 3px solid; }
        .header { text-align: center; margin-bottom: 8px; }
        .logo { max-height: 72px; max-width: 120px; margin: 0 auto 6px; display: block; }
        .org-name { font-size: 18px; font-weight: 700; color: #b45309; letter-spacing: 1px; text-transform: uppercase; }
        .event-subtitle { font-size: 13px; font-weight: 600; color: #334155; margin-top: 4px; }
        .cert-title {
            text-align: center; font-size: 30px; font-weight: 700; color: #b45309; letter-spacing: 2px;
            text-transform: uppercase; margin: 16px 0 14px; border-top: 2px solid #c7a84a; border-bottom: 2px solid #c7a84a; padding: 6px 0;
        }
        .body-text { text-align: center; font-size: 14px; line-height: 1.85; color: #334155; max-width: 680px; margin: 0 auto; }
        .body-text p { margin-bottom: 10px; }
        .seal-wrap { text-align: center; margin: 12px 0; }
        .seal { max-height: 64px; opacity: 0.9; }
        .date-line { margin-top: 14px; font-size: 13px; }
        .footer { margin-top: 28px; display: flex; flex-wrap: wrap; justify-content: center; gap: 24px 32px; }
        .sign-block { text-align: center; min-width: 140px; max-width: 180px; }
        .sign-img { max-height: 40px; max-width: 120px; margin-bottom: 2px; }
        .sign-line { border-top: 1px solid #0f172a; width: 140px; margin: 0 auto 4px; min-height: 1px; }
        .sign-name { font-size: 11px; font-weight: 600; color: #0f172a; }
        .sign-label { font-size: 10px; color: #64748b; margin-top: 2px; }
        .uuid { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 16px; }
        .qr-box { position: absolute; bottom: 18px; right: 18px; }

        /* Legacy fixed design (used when no template is configured) */
        .cert-legacy {
            width: 297mm; min-height: 210mm; margin: 0 auto; background: #fff;
            border: 12px double #b45309; box-sizing: border-box; padding: 2.5rem 3rem;
            position: relative; font-family: Georgia, 'Times New Roman', serif;
        }
        .cert-legacy .inner { border: 2px solid #d97706; padding: 2rem 2.5rem; text-align: center; min-height: 160mm; }
        .cert-legacy .org { font-size: .85rem; letter-spacing: .2em; text-transform: uppercase; color: #92400e; }
        .cert-legacy h1 { font-size: 2.4rem; margin: .75rem 0 .25rem; color: #0f172a; font-weight: normal; }
        .cert-legacy .subtitle { color: #64748b; font-size: 1rem; margin-bottom: 2rem; }
        .cert-legacy .name { font-size: 2rem; font-weight: bold; color: #1e3a5f; margin: 1rem 0; border-bottom: 1px solid #e2e8f0; display: inline-block; padding: 0 2rem .5rem; }
        .cert-legacy .detail { font-size: 1.1rem; color: #334155; line-height: 1.8; margin: 1.5rem 0; }
        .cert-legacy .position { font-size: 1.5rem; color: #b45309; font-weight: bold; }
        .cert-legacy .meta { position: absolute; bottom: 2rem; left: 3rem; right: 3rem; display: flex; justify-content: space-between; font-size: .75rem; color: #94a3b8; }

        .actions { text-align: center; padding: 1rem; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print, .actions { display: none; }
            .page.has-background { width: 100%; height: 100vh; min-height: 100vh; }
        }
        @page { size: landscape; margin: 0; }
    </style>
</head>
<body>
@if(!empty($template))
    @php
        $title = $template?->title ?? 'Certificate';
        $layout = $overlayLayout ?? \App\Models\CertificateTemplate::defaultBackgroundLayout();
        $boldVariables = (bool) ($layout['bold_variables'] ?? true);
        $showRecipientName = (bool) ($layout['show_recipient_name'] ?? true);
        $showParticipationLabel = (bool) ($layout['show_participation_label'] ?? true);
        $body = $template?->body ?? \App\Models\CertificateTemplate::defaultFestBody();
        foreach (($fieldValues ?? []) as $key => $value) {
            $safe = e((string) $value);
            if ($boldVariables && $safe !== '') {
                $safe = '<strong>'.$safe.'</strong>';
            }
            $body = str_replace('{'.$key.'}', $safe, $body);
        }
        $paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/', $body)));
        $hasBackground = ! empty($backgroundUrl);
    @endphp

    <div class="actions no-print">
        <button onclick="window.print()" style="padding:.5rem 1.25rem;font-size:1rem;cursor:pointer">Print / Save as PDF</button>
    </div>

    @if($hasBackground)
        <div class="page has-background" style="background-image:url('{{ $backgroundUrl }}');">
            @if(! $showParticipationLabel)
                @php $c = $layout['participation_label_cover'] ?? []; @endphp
                <div class="overlay-field" style="top:{{ $c['top'] ?? 28 }}%;left:{{ $c['left'] ?? 18 }}%;width:{{ $c['width'] ?? 64 }}%;height:{{ $c['height'] ?? 7 }}%;background:#f7f3e8;border-radius:2px;"></div>
            @endif

            @if($showRecipientName)
                @php $r = $layout['recipient_name'] ?? []; @endphp
                <div class="overlay-field recipient" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($r, ['top' => 38, 'left' => 10, 'width' => 80, 'font_size' => 28, 'font_family' => 'Georgia', 'font_weight' => 'bold']) }}">
                    {{ $fieldValues['recipient_name'] ?? '' }}
                </div>
            @endif

            @php $b = $layout['body'] ?? []; @endphp
            <div class="overlay-field body" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($b, ['top' => 48, 'left' => 12, 'width' => 76, 'font_size' => 13, 'font_family' => 'Times New Roman']) }}">
                @foreach($paragraphs as $paragraph)
                    <p style="margin-bottom:8px;">{!! nl2br($paragraph) !!}</p>
                @endforeach
            </div>

            @php $d = $layout['certificate_date'] ?? []; $dateValue = $fieldValues['certificate_date'] ?? now()->format('j F Y'); @endphp
            <div class="overlay-field" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($d, ['top' => 72, 'left' => 8, 'width' => 42, 'font_size' => 12, 'font_family' => 'Times New Roman', 'align' => 'left']) }}">
                @if($boldVariables)<strong>Date :</strong> <strong>{{ $dateValue }}</strong>@else Date : {{ $dateValue }}@endif
            </div>

            @php $u = $layout['uuid'] ?? []; @endphp
            <div class="overlay-field uuid" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($u, ['top' => 92, 'left' => 5, 'width' => 90, 'font_size' => 8, 'font_family' => 'Arial']) }}">
                Verification: {{ $certificate->verification_uuid ?? 'Not yet issued' }}
            </div>

            @if(!empty($qr_src))
                <div class="qr-box"><img src="{{ $qr_src }}" alt="Verify QR" width="70" height="70"></div>
            @endif
        </div>
    @else
        <div class="page">
            <div class="corner corner-tl"></div>
            <div class="corner corner-tr"></div>
            <div class="corner corner-bl"></div>
            <div class="corner corner-br"></div>

            <div class="header">
                @if(!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="" class="logo">
                @endif
                <p class="org-name">{{ $fieldValues['sahodaya_name'] ?? ($sahodaya->name ?? '') }}</p>
                @if(!empty($fieldValues['event_title']))
                    <p class="event-subtitle">{{ $fieldValues['event_title'] }}@if(!empty($fieldValues['item_title'])) — {{ $fieldValues['item_title'] }}@endif</p>
                @endif
            </div>

            <div class="cert-title">{{ $title }}</div>

            <div class="body-text">
                @foreach($paragraphs as $paragraph)
                    <p>{!! nl2br($paragraph) !!}</p>
                @endforeach
                <p class="date-line"><strong>Date:</strong> {{ $fieldValues['certificate_date'] ?? now()->format('j F Y') }}</p>
            </div>

            @if(!empty($sealUrl))
                <div class="seal-wrap"><img src="{{ $sealUrl }}" alt="" class="seal"></div>
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

            @if(!empty($qr_src))
                <div class="qr-box"><img src="{{ $qr_src }}" alt="Verify QR" width="70" height="70"></div>
            @endif

            <p class="uuid">Verification: {{ $certificate->verification_uuid ?? 'Not yet issued' }}</p>
        </div>
    @endif
@else
    {{-- No template configured for this event/item — fall back to the fixed design --}}
    <div class="actions no-print">
        <button onclick="window.print()" style="padding:.5rem 1.25rem;font-size:1rem;cursor:pointer">Print / Save as PDF</button>
    </div>
    <div class="cert-legacy">
        <div class="inner">
            <p class="org">{{ ($recordBreak ?? null) ? 'Record Break Achievement' : 'Certificate of Achievement' }}</p>
            <h1>{{ $event?->title ?? 'Kalotsav' }}</h1>
            <p class="subtitle">{{ $item?->title ?? '' }}</p>
            <p class="detail">This is to certify that</p>
            <p class="name">{{ $student?->name ?? 'Participant' }}</p>
            @if($recordBreak)
            <p class="position">{{ $recordBreak->prize_label }}</p>
            <p class="detail">
                Broke the standing record:
                <strong>{{ $recordBreak->previous_value }}</strong> →
                <strong>{{ $recordBreak->new_value }} {{ $recordBreak->record_unit }}</strong>
            </p>
            @elseif($mark?->position)
            <p class="position">Position #{{ $mark->position }}</p>
            @endif
            @if($mark?->grade)
            <p class="detail">Grade: <strong>{{ $mark->grade }}</strong></p>
            @endif
            @if($recordBreak)
            <p class="detail">has achieved this athletic record distinction.</p>
            @else
            <p class="detail">has participated and achieved this distinction.</p>
            @endif
        </div>
        @if(!empty($qr_src))<div style="position:absolute;bottom:2rem;right:3rem;"><img src="{{ $qr_src }}" alt="Verify QR" width="80" height="80"></div>@endif
        <div class="meta">
            <span>ID: {{ $certificate->verification_uuid }}</span>
            <span>{{ $certificate->generated_at?->format('d M Y') ?? now()->format('d M Y') }}</span>
        </div>
    </div>
@endif
</body>
</html>
