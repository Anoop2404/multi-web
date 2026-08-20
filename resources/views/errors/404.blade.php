@php
    $errorTenant = null;
    $errorLogo = null;

    try {
        $errorTenant = \App\Support\TenantBranding::resolveTenant(request());
        $errorLogo = $errorTenant ? \App\Support\TenantBranding::logoUrl($errorTenant) : null;
    } catch (\Throwable) {
        // Error pages must remain renderable when tenancy or storage is unavailable.
    }

    $isEventRequest = request()->is('fest') || request()->is('fest/*');
    $siteName = $errorTenant?->name ?? config('app.name', 'Sahodaya');
    $pageTitle = $isEventRequest ? 'Event page not found' : 'Page not found';
    $pageMessage = $isEventRequest
        ? 'This event may be unavailable, unpublished, or moved to a different event page.'
        : 'The page you requested may have been moved, removed, or is temporarily unavailable.';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#071a2d">
    <title>404 — {{ $pageTitle }}</title>
    <style>
        :root {
            --navy-950: #04111f;
            --navy-900: #071a2d;
            --navy-800: #0f2942;
            --navy-700: #163b60;
            --gold-500: #d97706;
            --gold-400: #f59e0b;
            --slate-700: #334155;
            --slate-500: #64748b;
            --slate-200: #e2e8f0;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { color-scheme: dark; }

        body {
            min-height: 100dvh;
            margin: 0;
            overflow-x: hidden;
            display: grid;
            place-items: center;
            padding: 1.25rem;
            color: #fff;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 12% 15%, rgba(245, 158, 11, .16), transparent 31rem),
                radial-gradient(circle at 88% 84%, rgba(59, 130, 246, .17), transparent 32rem),
                linear-gradient(145deg, var(--navy-950), var(--navy-900) 46%, var(--navy-800));
        }

        .page-glow {
            position: fixed;
            width: 18rem;
            height: 18rem;
            border: 1px solid rgba(255, 255, 255, .06);
            border-radius: 999px;
            pointer-events: none;
        }

        .page-glow--top { top: -9rem; right: -5rem; }
        .page-glow--bottom { bottom: -11rem; left: -4rem; width: 25rem; height: 25rem; }

        .error-shell {
            position: relative;
            width: min(100%, 60rem);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 1.75rem;
            background: rgba(7, 26, 45, .76);
            box-shadow: 0 2rem 6rem rgba(0, 0, 0, .38);
            backdrop-filter: blur(18px);
        }

        .error-header {
            display: flex;
            align-items: center;
            gap: .8rem;
            min-height: 4.75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, .09);
        }

        .brand-mark {
            width: 2.75rem;
            height: 2.75rem;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            overflow: hidden;
            border: 1px solid rgba(245, 158, 11, .3);
            border-radius: .9rem;
            color: #fffbeb;
            background: rgba(245, 158, 11, .13);
        }

        .brand-mark img { width: 100%; height: 100%; object-fit: contain; background: #fff; }
        .brand-mark svg { width: 1.35rem; height: 1.35rem; }

        .brand-copy { min-width: 0; }
        .brand-name { margin: 0; overflow: hidden; color: #fff; font-size: .95rem; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
        .brand-label { margin: .15rem 0 0; color: #94a3b8; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }

        .error-content {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(15rem, .72fr);
            align-items: center;
            gap: 2rem;
            padding: clamp(2rem, 7vw, 5rem);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: 1.25rem;
            padding: .42rem .72rem;
            border: 1px solid rgba(245, 158, 11, .3);
            border-radius: 999px;
            color: #fcd34d;
            background: rgba(245, 158, 11, .1);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 35rem;
            margin: 0;
            color: #fff;
            font-size: clamp(2rem, 5vw, 3.75rem);
            line-height: 1.05;
            letter-spacing: -.045em;
        }

        .message {
            max-width: 37rem;
            margin: 1.15rem 0 0;
            color: #cbd5e1;
            font-size: clamp(.95rem, 2vw, 1.08rem);
            line-height: 1.7;
        }

        .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 2rem; }

        .button {
            min-height: 2.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            padding: .75rem 1.1rem;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: .8rem;
            color: #e2e8f0;
            background: rgba(255, 255, 255, .06);
            font-size: .88rem;
            font-weight: 800;
            text-decoration: none;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }

        .button:hover { transform: translateY(-2px); border-color: rgba(255, 255, 255, .3); background: rgba(255, 255, 255, .1); }
        .button:focus-visible { outline: 3px solid rgba(245, 158, 11, .55); outline-offset: 3px; }
        .button--primary { border-color: var(--gold-400); color: #231500; background: linear-gradient(135deg, #fbbf24, var(--gold-400)); }
        .button--primary:hover { border-color: #fcd34d; background: linear-gradient(135deg, #fcd34d, #f59e0b); }
        .button svg { width: 1rem; height: 1rem; }

        .error-visual { position: relative; display: grid; min-height: 16rem; place-items: center; }

        .error-number {
            position: relative;
            z-index: 1;
            color: transparent;
            font-size: clamp(7rem, 17vw, 12rem);
            font-weight: 900;
            line-height: .8;
            letter-spacing: -.1em;
            -webkit-text-stroke: 1px rgba(255, 255, 255, .25);
            text-shadow: 0 1.25rem 4rem rgba(0, 0, 0, .28);
            user-select: none;
        }

        .visual-icon {
            position: absolute;
            z-index: 2;
            width: 4.5rem;
            height: 4.5rem;
            display: grid;
            place-items: center;
            border: 1px solid rgba(245, 158, 11, .38);
            border-radius: 1.35rem;
            color: #fcd34d;
            background: rgba(7, 26, 45, .92);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, .35);
            transform: rotate(-7deg);
        }

        .visual-icon svg { width: 2rem; height: 2rem; }

        @media (max-width: 720px) {
            .error-content { grid-template-columns: 1fr; padding: 2rem 1.35rem 2.35rem; }
            .error-visual { grid-row: 1; min-height: 8rem; }
            .error-number { font-size: 7.5rem; }
            .visual-icon { width: 3.5rem; height: 3.5rem; border-radius: 1rem; }
            .visual-icon svg { width: 1.55rem; height: 1.55rem; }
            .actions { display: grid; }
            .button { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .button { transition: none; }
            .button:hover { transform: none; }
        }
    </style>
</head>
<body>
    <span class="page-glow page-glow--top" aria-hidden="true"></span>
    <span class="page-glow page-glow--bottom" aria-hidden="true"></span>

    <main class="error-shell">
        <header class="error-header">
            <span class="brand-mark" aria-hidden="true">
                @if($errorLogo)
                    <img src="{{ $errorLogo }}" alt="">
                @else
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6M9 10h.01M15 10h.01"/>
                    </svg>
                @endif
            </span>
            <div class="brand-copy">
                <p class="brand-name">{{ $siteName }}</p>
                <p class="brand-label">Public information portal</p>
            </div>
        </header>

        <div class="error-content">
            <section aria-labelledby="error-title">
                <span class="eyebrow">Error 404</span>
                <h1 id="error-title">{{ $pageTitle }}</h1>
                <p class="message">{{ $pageMessage }}</p>

                <nav class="actions" aria-label="Recovery options">
                    @if($isEventRequest && $errorTenant)
                        <a href="{{ url('/fest') }}" class="button button--primary">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12m-12 5.25h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75Zm0 5.25h.008v.008H3.75V12Zm0 5.25h.008v.008H3.75v-.008Z"/>
                            </svg>
                            View all events
                        </a>
                    @endif
                    <a href="{{ url('/') }}" class="button {{ !($isEventRequest && $errorTenant) ? 'button--primary' : '' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
                        </svg>
                        Back to home
                    </a>
                </nav>
            </section>

            <div class="error-visual" aria-hidden="true">
                <span class="error-number">404</span>
                <span class="visual-icon">
                    @if($isEventRequest)
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 2.75v2.5m7.5-2.5v2.5M3.75 9h16.5M5.5 4.5h13A1.75 1.75 0 0 1 20.25 6.25v12A1.75 1.75 0 0 1 18.5 20h-13a1.75 1.75 0 0 1-1.75-1.75v-12A1.75 1.75 0 0 1 5.5 4.5Z"/>
                            <path stroke-linecap="round" d="M9 13h6m-6 3h3"/>
                        </svg>
                    @else
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 15.75 5.25 5.25m-3-10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/>
                        </svg>
                    @endif
                </span>
            </div>
        </div>
    </main>
</body>
</html>
