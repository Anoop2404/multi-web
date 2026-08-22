{{--
    Single certificate's visible markup — shared by certificate-print.blade.php (one
    certificate per page load) and certificate-print-all.blade.php (every certificate
    for an event concatenated into one page, each wrapped so it starts on its own
    printed page). Expects the same variables renderContext()/payloadFor() produce:
    $certificate, $event, $item, $student, $mark, $recordBreak, $template,
    $overlayLayout, $fieldValues, $sahodaya, $logoUrl, $sealUrl, $backgroundUrl,
    $photoUrl, $signatories, $qr_src, $isSample.
--}}
@php
    $__layout = $overlayLayout ?? (!empty($template) ? $template->overlayLayout() : \App\Models\CertificateTemplate::defaultBackgroundLayout());
    $__orientation = ($__layout['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape';
@endphp
@if(!empty($template))
    @php
        $title = $template?->title ?? 'Certificate';
        $layout = $overlayLayout ?? \App\Models\CertificateTemplate::defaultBackgroundLayout();
        $boldVariables = (bool) ($layout['bold_variables'] ?? true);
        $showRecipientName = (bool) ($layout['show_recipient_name'] ?? true);
        $showParticipationLabel = (bool) ($layout['show_participation_label'] ?? true);
        $showCertificateDate = (bool) ($layout['show_certificate_date'] ?? true);
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

    @if(empty($isSample))
        <div class="actions no-print">
            <button onclick="window.print()" style="padding:.5rem 1.25rem;font-size:1rem;cursor:pointer">Print / Save as PDF</button>
        </div>
    @endif

    @if($hasBackground)
        <div class="page has-background {{ $__orientation === 'portrait' ? 'portrait' : '' }}" style="background-image:url('{{ $backgroundUrl }}');">
            {{-- Sahodaya branding is otherwise entirely dependent on the uploaded
                 background image, which may not carry any logo/name of its own.
                 Keep a small, unobtrusive overlay so branding is always present —
                 unless the template's own background already has real branding baked
                 in (show_logo_overlay:false), where this would just duplicate it. --}}
            @if(($layout['show_logo_overlay'] ?? true) && (!empty($logoUrl) || !empty($sahodaya?->name)))
                <div class="logo-overlay">
                    @if(!empty($logoUrl))
                        <img src="{{ $logoUrl }}" alt="">
                    @endif
                    <span>{{ $fieldValues['sahodaya_name'] ?? ($sahodaya->name ?? '') }}</span>
                </div>
            @endif

            @if(($layout['show_photo'] ?? false) && !empty($photoUrl))
                @php $ph = $layout['photo'] ?? []; @endphp
                <img class="overlay-photo" src="{{ $photoUrl }}" alt=""
                     style="top:{{ $ph['top'] ?? 31 }}%;left:{{ $ph['left'] ?? 50 }}%;width:{{ $ph['size'] ?? 118 }}px;height:{{ $ph['size'] ?? 118 }}px;transform:translateX(-50%);">
            @endif

            @if(! $showParticipationLabel)
                @php $c = $layout['participation_label_cover'] ?? []; @endphp
                <div class="overlay-field" style="top:{{ $c['top'] ?? 28 }}%;left:{{ $c['left'] ?? 18 }}%;width:{{ $c['width'] ?? 64 }}%;height:{{ $c['height'] ?? 7 }}%;background:#f7f3e8;border-radius:2px;"></div>
            @endif

            @if($showRecipientName)
                @php $r = $layout['recipient_name'] ?? []; @endphp
                <div class="overlay-field recipient" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($r, ['top' => 38, 'left' => 10, 'width' => 80, 'font_size' => 24, 'font_family' => 'Montserrat', 'font_weight' => 'bold']) }}">
                    {{ $fieldValues['recipient_name'] ?? '' }}
                </div>
            @endif

            @php $b = $layout['body'] ?? []; @endphp
            <div class="overlay-field body" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($b, ['top' => 48, 'left' => 12, 'width' => 76, 'font_size' => 12.5, 'font_family' => 'Montserrat']) }}">
                @foreach($paragraphs as $paragraph)
                    <p style="margin-bottom:8px;">{!! nl2br($paragraph) !!}</p>
                @endforeach
            </div>

            @if($showCertificateDate)
                @php $d = $layout['certificate_date'] ?? []; $dateValue = $fieldValues['certificate_date'] ?? now()->format('j F Y'); @endphp
                <div class="overlay-field" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($d, ['top' => 72, 'left' => 8, 'width' => 42, 'font_size' => 12, 'font_family' => 'Montserrat', 'align' => 'left']) }}">
                    @if($boldVariables)<strong>Date :</strong> <strong>{{ $dateValue }}</strong>@else Date : {{ $dateValue }}@endif
                </div>
            @endif

            @php $u = $layout['uuid'] ?? []; @endphp
            <div class="overlay-field uuid" style="{{ \App\Models\CertificateTemplate::overlayFieldStyle($u, ['top' => 92, 'left' => 5, 'width' => 90, 'font_size' => 8, 'font_family' => 'Arial']) }}">
                Verification: {{ $certificate->verification_uuid ?? 'Not yet issued' }}
            </div>

            @if(($layout['show_qr'] ?? true) && !empty($qr_src))
                <div class="qr-box"><img src="{{ $qr_src }}" alt="Verify QR" width="70" height="70"></div>
            @endif
        </div>
    @else
        @php
            $b = $layout['body'] ?? [];
            $bAlign = $b['align'] ?? 'center';
            $bStyle = '';
            if (!empty($b['font_size'])) $bStyle .= 'font-size:' . $b['font_size'] . 'px;';
            if (!empty($b['font_family'])) $bStyle .= 'font-family:"' . $b['font_family'] . '", serif;';
            if (!empty($b['font_weight']) && $b['font_weight'] === 'bold') $bStyle .= 'font-weight:bold;';
            if (!empty($b['font_style']) && $b['font_style'] === 'italic') $bStyle .= 'font-style:italic;';
            if ($bAlign && $bAlign !== 'none') $bStyle .= 'text-align:' . $bAlign . ';';

            $d = $layout['certificate_date'] ?? [];
            $dAlign = $d['align'] ?? 'left';
            $dStyle = '';
            if (!empty($d['font_size'])) $dStyle .= 'font-size:' . $d['font_size'] . 'px;';
            if (!empty($d['font_family'])) $dStyle .= 'font-family:"' . $d['font_family'] . '", serif;';
            if (!empty($d['font_weight']) && $d['font_weight'] === 'bold') $dStyle .= 'font-weight:bold;';
            if (!empty($d['font_style']) && $d['font_style'] === 'italic') $dStyle .= 'font-style:italic;';
            if ($dAlign && $dAlign !== 'none') $dStyle .= 'text-align:' . $dAlign . ';';
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
                <p class="org-name">{{ $fieldValues['sahodaya_name'] ?? ($sahodaya->name ?? '') }}</p>
                @if(!empty($fieldValues['event_title']))
                    <p class="event-subtitle">{{ $fieldValues['event_title'] }}@if(!empty($fieldValues['item_title'])) — {{ $fieldValues['item_title'] }}@endif</p>
                @endif
            </div>

            <div class="cert-title">{{ $title }}</div>

            <div class="body-text" style="{{ $bStyle }}">
                @foreach($paragraphs as $paragraph)
                    <p>{!! nl2br($paragraph) !!}</p>
                @endforeach
                @if($showCertificateDate)
                    <p class="date-line" style="{{ $dStyle }}"><strong>Date:</strong> {{ $fieldValues['certificate_date'] ?? now()->format('j F Y') }}</p>
                @endif
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

            @if(($layout['show_qr'] ?? true) && !empty($qr_src))
                <div class="qr-box"><img src="{{ $qr_src }}" alt="Verify QR" width="70" height="70"></div>
            @endif

            <p class="uuid">Verification: {{ $certificate->verification_uuid ?? 'Not yet issued' }}</p>
        </div>
    @endif
@else
    {{-- No template configured for this event/item — fall back to the fixed design --}}
    @if(empty($isSample))
        <div class="actions no-print">
            <button onclick="window.print()" style="padding:.5rem 1.25rem;font-size:1rem;cursor:pointer">Print / Save as PDF</button>
        </div>
    @endif
    <div class="cert-legacy">
        <div class="inner">
            <p class="org">{{ ($recordBreak ?? null) ? 'Record Break Achievement' : 'Certificate of Achievement' }}</p>
            <h1>{{ $event?->title ?? 'Kalotsav' }}</h1>
            <p class="subtitle">{{ $fieldValues['item_title'] ?? $item?->title ?? '' }}</p>
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
