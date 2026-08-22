<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate — {{ $student?->name ?? 'Participant' }}</title>
    {{-- Admin-authored template body text may reference decorative webfonts (e.g.
         Cinzel) by name in its own inline styles; loaded here so those actually render
         instead of silently falling back to the next font in the stack. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;800&display=swap" rel="stylesheet">
    @php
        // Computed ahead of the stylesheet because the static @page rule needs it too.
        $__layout = $overlayLayout ?? (!empty($template) ? $template->overlayLayout() : \App\Models\CertificateTemplate::defaultBackgroundLayout());
        $__orientation = ($__layout['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape';
    @endphp
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e2e8f0; color: #1e293b; font-family: "Times New Roman", Times, serif; }

        @page {
            size: A4 {{ $__orientation }};
            margin: 0;
        }

        .cert-viewport {
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .page {
            width: 842px;
            min-height: 595px;
            margin: 0 auto;
            position: relative;
            border: 10px double #b45309;
            padding: 40px 52px 56px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
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
            background-color: #ffffff;
        }

        .page.has-background.portrait {
            width: 794px;
            height: 1123px;
            min-height: 1123px;
        }

        body.hide-background .page.has-background,
        .page.hide-background {
            background-image: none !important;
            background-color: #ffffff !important;
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

        .actions { display: none; }
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print, .actions-bar { display: none !important; }
            .cert-viewport { padding: 0 !important; margin: 0 !important; display: block !important; }
            .page,
            .page.has-background,
            .page.has-background.portrait {
                transform: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .page.has-background {
                width: 297mm !important;
                height: 210mm !important;
                min-height: 210mm !important;
                max-width: 297mm !important;
                max-height: 210mm !important;
                page-break-after: always;
                break-after: page;
            }
            .page.has-background.portrait {
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-width: 210mm !important;
                max-height: 297mm !important;
                page-break-after: always;
                break-after: page;
            }
        }
    </style>
</head>
<body>
    <div class="no-print actions-bar" style="position: fixed; top: 16px; left: 50%; transform: translateX(-50%); z-index: 99999; display: flex; align-items: center; gap: 12px; background: rgba(15, 23, 42, 0.92); color: #fff; padding: 8px 18px; border-radius: 9999px; box-shadow: 0 10px 30px rgba(0,0,0,0.35); backdrop-filter: blur(8px); font-family: system-ui, -apple-system, sans-serif;">
        <button type="button" onclick="window.print()" style="background: #eab308; color: #0f172a; font-weight: 700; border: none; padding: 7px 18px; border-radius: 9999px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
            <span>🖨️</span> Print / Save PDF
        </button>
        <button type="button" id="toggleBgBtn" onclick="toggleBackground()" style="background: rgba(255,255,255,0.15); color: #fff; font-weight: 600; border: 1px solid rgba(255,255,255,0.3); padding: 7px 18px; border-radius: 9999px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
            <span>🖼️</span> <span id="bgBtnText">Hide Background Image (Print on Paper)</span>
        </button>
    </div>

    <script>
        function toggleBackground() {
            document.body.classList.toggle('hide-background');
            var isHidden = document.body.classList.contains('hide-background');
            var btnText = document.getElementById('bgBtnText');
            if (btnText) {
                btnText.innerText = isHidden ? 'Show Background Image' : 'Hide Background Image (Print on Paper)';
            }
        }
        if (new URLSearchParams(window.location.search).get('hide_bg') === '1' || new URLSearchParams(window.location.search).get('plain') === '1') {
            document.body.classList.add('hide-background');
        }
    </script>

    <div class="cert-viewport">
        @include('fest.partials.certificate-body')
    </div>

    <script>
        (function () {
            function fitToScreen() {
                var page = document.querySelector('.page');
                if (!page) return;
                page.style.transform = 'none';
                page.style.marginBottom = '0px';
                var rect = page.getBoundingClientRect();
                var scale = Math.min(
                    (window.innerWidth - 32) / rect.width,
                    (window.innerHeight - 32) / rect.height,
                    1
                );
                page.style.transformOrigin = 'top center';
                page.style.transform = 'scale(' + scale + ')';
                page.style.marginBottom = (-(rect.height * (1 - scale))) + 'px';
            }
            window.addEventListener('load', fitToScreen);
            window.addEventListener('resize', fitToScreen);
            window.addEventListener('beforeprint', function () {
                var page = document.querySelector('.page');
                if (page) {
                    page.style.transform = 'none';
                    page.style.marginBottom = '0px';
                }
            });
            window.addEventListener('afterprint', fitToScreen);
        })();
    </script>
</body>
</html>
