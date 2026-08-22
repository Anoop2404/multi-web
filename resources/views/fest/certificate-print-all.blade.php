<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificates — {{ $event->title ?? 'Event' }}</title>
    {{-- Admin-authored template body text may reference decorative webfonts (e.g.
         Cinzel) by name in its own inline styles; loaded here so those actually render
         instead of silently falling back to the next font in the stack. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;800&display=swap" rel="stylesheet">
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
        .page.has-background.portrait {
            width: 794px;
            height: 1123px;
            min-height: 1123px;
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
        .logo-overlay {
            position: absolute; top: 16px; left: 16px; display: flex; align-items: center;
            gap: 6px; background: rgba(255,255,255,0.72); border-radius: 4px; padding: 4px 8px;
        }
        .logo-overlay img { height: 40px; width: auto; max-width: 40px; object-fit: contain; }
        .logo-overlay span { font-size: 9px; font-weight: 700; color: #1e293b; max-width: 120px; line-height: 1.2; }
        .overlay-photo {
            position: absolute; border-radius: 50%; object-fit: cover;
            border: 3px solid #fdfaf0; box-shadow: 0 2px 10px rgba(0,0,0,0.25); background: #fff;
        }

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
        .cert-sheet { padding-top: 24px; page-break-after: always; break-after: page; }
        .cert-sheet:last-child { page-break-after: auto; break-after: auto; }
        .toolbar {
            position: sticky; top: 0; z-index: 10; text-align: center; padding: 14px;
            background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        }
        .toolbar button {
            padding: .6rem 1.5rem; font-size: 1rem; cursor: pointer; border-radius: 6px;
            border: 1px solid #b45309; background: #b45309; color: #fff; font-weight: 600;
        }
        .toolbar p { margin: 8px 0 0; font-size: 12px; color: #64748b; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
            .cert-sheet { padding-top: 0; }
            .page.has-background, .page.has-background.portrait { width: 100%; height: 100vh; min-height: 100vh; }
        }
        @page { size: landscape; margin: 0; }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button onclick="window.print()">Print / Save all as PDF ({{ count($certificates) }} certificate{{ count($certificates) === 1 ? '' : 's' }})</button>
        <p>Each certificate prints on its own page — use "Save as PDF" in the print dialog to download them all as one file.</p>
    </div>
    @forelse($certificates as $payload)
        <div class="cert-sheet">
            @include('fest.partials.certificate-body', array_merge($payload, ['isSample' => true]))
        </div>
    @empty
        <p class="no-print" style="text-align:center;padding:2rem;color:#64748b;">No certificates to show.</p>
    @endforelse
</body>
</html>
