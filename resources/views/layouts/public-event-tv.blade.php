<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale ?? 'en') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ($pageSeo['title'] ?? null) ?: ($tenant->name ?? 'Sahodaya') }}</title>
    {{-- Not a page a visitor searches for or shares — it's a URL typed once into a
         venue's TV/browser and left running. --}}
    <meta name="robots" content="noindex, nofollow">

    @vite(['resources/css/app.css', 'resources/js/public.js'])

    <style>
        /* Fixed canvas, not responsive. The content below is always laid out as if the
           screen were exactly 1920x1080 — #tv-canvas is that literal fixed size — and
           the script at the bottom scales the whole canvas as one rigid unit to fit
           whatever window/TV it's actually shown on. Nothing inside ever reflows for a
           narrower or shorter screen; it just renders smaller or larger. */
        html, body {
            margin: 0;
            height: 100%;
            overflow: hidden;
            background: #020617;
        }
        html { font-size: 21px; }
        #tv-canvas {
            width: 1920px;
            height: 1080px;
            position: absolute;
            top: 0;
            left: 0;
            transform-origin: top left;
            overflow: hidden;
        }
    </style>
</head>
<body class="font-body text-white" style="color:#fff">
    <div id="tv-canvas">
        @yield('content')
    </div>
    <script>
        (() => {
            const canvas = document.getElementById('tv-canvas');
            const fit = () => {
                // Explicit pixel left/top, not a %-based translate(-50%,-50%) — that
                // percentage resolves against the canvas's own unscaled 1920x1080 box
                // regardless of the scale() in the same transform, so it doesn't actually
                // land centered once scaled. Computing the offset in real pixels here
                // sidesteps that percentage/scale composition ambiguity entirely.
                const scale = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
                const left = (window.innerWidth - 1920 * scale) / 2;
                const top = (window.innerHeight - 1080 * scale) / 2;
                canvas.style.transform = `translate(${left}px, ${top}px) scale(${scale})`;
            };
            fit();
            window.addEventListener('resize', fit);
        })();
    </script>
</body>
</html>
