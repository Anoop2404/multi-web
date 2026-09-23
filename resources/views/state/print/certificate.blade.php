{{--
    A State Kalotsav certificate. Landscape A4, fixed layout, rendered by Chromium.

    Both the Sahodaya and the School are named, and that is deliberate: at State level the Sahodaya is
    the competing unit, but the person holding the certificate belongs to a school, and a certificate
    that names only one of the two is wrong to somebody. The QR resolves to the public verification
    page so a certificate can be checked without asking the State office.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $certificate->certificate_number }} — {{ $certificate->recipient_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        @page { size: 297mm 210mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: Lato, "Helvetica Neue", Arial, sans-serif; color: #1f2937; }

        .page {
            width: 297mm; height: 210mm; position: relative;
            padding: 14mm; background: #fff;
        }
        .frame {
            height: 100%; border: 2px solid #b08d57; padding: 8mm 12mm;
            position: relative; text-align: center;
            background:
                radial-gradient(circle at 12% 12%, rgba(176,141,87,0.07) 0, transparent 38%),
                radial-gradient(circle at 88% 88%, rgba(30,58,95,0.06) 0, transparent 38%);
        }
        .frame::after {
            content: ''; position: absolute; inset: 3mm; border: 0.6px solid #d8c9a8; pointer-events: none;
        }

        .crest { font-family: Cinzel, Georgia, serif; font-size: 11pt; letter-spacing: 3px; color: #1e3a5f; margin: 0; }
        .event { font-family: Cinzel, Georgia, serif; font-size: 20pt; font-weight: 700; color: #1e3a5f; margin: 2mm 0 0; }
        .season { font-size: 9pt; color: #6b7280; margin: 1mm 0 0; letter-spacing: 1px; }
        .kind { font-family: Cinzel, Georgia, serif; font-size: 13pt; color: #b08d57; letter-spacing: 4px; text-transform: uppercase; margin: 7mm 0 4mm; }

        .lead { font-size: 10.5pt; color: #4b5563; margin: 0; }
        .name { font-family: Cinzel, Georgia, serif; font-size: 26pt; font-weight: 700; color: #111827; margin: 2mm 0 1mm; }
        .origin { font-size: 10.5pt; color: #374151; margin: 0; }
        .origin strong { color: #1e3a5f; }
        .citation { font-size: 11pt; color: #374151; margin: 5mm auto 0; max-width: 190mm; line-height: 1.6; }
        .award { font-family: Cinzel, Georgia, serif; font-size: 15pt; color: #b08d57; font-weight: 700; }

        .foot { position: absolute; left: 12mm; right: 12mm; bottom: 8mm; }
        .sig { display: inline-block; width: 46%; text-align: center; vertical-align: bottom; }
        .sig .rule { border-top: 0.8px solid #9ca3af; margin: 0 6mm 1.5mm; }
        .sig .role { font-size: 8.5pt; color: #6b7280; }

        .verify {
            position: absolute; right: 10mm; bottom: 8mm; text-align: center; width: 30mm;
        }
        .verify img { width: 22mm; height: 22mm; display: block; margin: 0 auto 1mm; }
        .verify .code { font-family: "Courier New", monospace; font-size: 7pt; color: #4b5563; word-break: break-all; }
        .serial { position: absolute; left: 10mm; bottom: 8mm; font-size: 7.5pt; color: #9ca3af; font-family: "Courier New", monospace; }
    </style>
</head>
<body>
<div class="page">
    <div class="frame">
        <p class="crest">Sahodaya Central School Complex</p>
        <h1 class="event">{{ $event->name }}</h1>
        <p class="season">
            @if ($event->starts_on){{ $event->starts_on->format('d M Y') }}@endif
            @if ($event->ends_on && $event->starts_on && ! $event->starts_on->isSameDay($event->ends_on))
                – {{ $event->ends_on->format('d M Y') }}
            @endif
        </p>

        <p class="kind">{{ $typeLabel }}</p>

        <p class="lead">This is to certify that</p>
        <p class="name">{{ $certificate->recipient_name }}</p>
        <p class="origin">
            @if ($certificate->school_name){{ $certificate->school_name }}@endif
            @if ($certificate->school_name && $certificate->sahodaya_name) · @endif
            @if ($certificate->sahodaya_name)<strong>{{ $certificate->sahodaya_name }}</strong>@endif
        </p>

        <p class="citation">
            @if ($certificate->type === 'championship')
                is adjudged <span class="award">{{ $certificate->position ? \App\Support\Ordinal::of($certificate->position) : '' }} Champion</span>
                of the {{ $event->name }}.
            @elseif ($certificate->type === 'participation')
                participated in <strong>{{ $certificate->item_name ?: $certificate->item_code }}</strong>
                at the {{ $event->name }}.
            @elseif (in_array($certificate->type, ['judge', 'official', 'volunteer'], true))
                served as {{ $typeLabel }} at the {{ $event->name }}, and the organisers record
                their gratitude for that service.
            @elseif ($certificate->position)
                secured <span class="award">{{ \App\Support\Ordinal::of($certificate->position) }} place</span>
                @if ($certificate->grade) with <span class="award">Grade {{ $certificate->grade }}</span> @endif
                in <strong>{{ $certificate->item_name ?: $certificate->item_code }}</strong>.
            @else
                is awarded <span class="award">Grade {{ $certificate->grade ?: '—' }}</span>
                in <strong>{{ $certificate->item_name ?: $certificate->item_code }}</strong>.
            @endif
        </p>

        <div class="foot">
            <div class="sig">
                <div class="rule"></div>
                <p class="role">General Convener</p>
            </div>
            <div class="sig">
                <div class="rule"></div>
                <p class="role">State President</p>
            </div>
        </div>

        <p class="serial">{{ $certificate->certificate_number }}</p>

        <div class="verify">
            @php
                // Inlined as a data URI: the renderer fetches nothing from this host, so a
                // certificate renders identically from a queue worker with no session.
                $__qr = base64_encode(app(\App\Services\Training\TrainingQrService::class)->png($verifyUrl, 300));
            @endphp
            <img src="data:image/png;base64,{{ $__qr }}" alt="Verification QR">
            <p class="code">{{ $certificate->verification_code }}</p>
        </div>
    </div>
</div>
</body>
</html>
