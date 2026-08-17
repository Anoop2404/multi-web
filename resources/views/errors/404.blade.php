<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page not found</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Sahodaya platform palette — navy, royal blue, gold (matches portal layout) */
            --navy-950: #041525;
            --navy-900: #0a2744;
            --navy-800: #0f3d7a;
            --navy-700: #1a4f8c;
            --navy-600: #1e5aa8;
            --gold-400: #fbbf24;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100dvh;
            background:
                radial-gradient(ellipse 65% 50% at 20% 5%, rgba(234,179,8,.14) 0%, transparent 55%),
                radial-gradient(ellipse 55% 45% at 80% 95%, rgba(37,99,235,.18) 0%, transparent 50%),
                linear-gradient(165deg, var(--navy-950) 0%, var(--navy-900) 40%, var(--navy-800) 100%);
            color: #fff;
            -webkit-font-smoothing: antialiased;
            display: flex; align-items: center; justify-content: center;
            padding: 1.5rem;
        }
        .error-card {
            width: 100%; max-width: 26rem;
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 0 0 1px rgba(255,255,255,.06), 0 32px 80px rgba(0,0,0,.45);
            padding: 2.5rem 2rem;
            text-align: center;
            color: var(--navy-900);
        }
        .error-code {
            display: inline-block;
            font-size: .75rem; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: var(--gold-600, #ca8a04);
            background: rgba(234,179,8,.12);
            border: 1px solid rgba(234,179,8,.28);
            padding: .35rem .9rem; border-radius: 999px;
            margin-bottom: 1.25rem;
        }
        .error-title {
            font-size: 1.5rem; font-weight: 800; color: var(--navy-900);
            line-height: 1.3; margin-bottom: .6rem;
        }
        .error-message {
            font-size: .9375rem; color: #64748b; line-height: 1.6;
            margin-bottom: 2rem;
        }
        .error-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .85rem 1.5rem;
            border: none; border-radius: .75rem;
            font-size: .875rem; font-weight: 700; font-family: inherit;
            text-decoration: none;
            background: linear-gradient(135deg, var(--navy-800), var(--navy-600));
            color: #fff;
            box-shadow: 0 4px 14px rgba(15,61,122,.4);
            transition: box-shadow .15s, transform .1s;
        }
        .error-btn:hover { box-shadow: 0 6px 20px rgba(15,61,122,.5); }
        .error-btn:active { transform: scale(.98); }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="error-code">Error 404</span>
        <h1 class="error-title">Page not found</h1>
        <p class="error-message">The page you're looking for doesn't exist or may have been moved.</p>
        <a href="{{ url('/') }}" class="error-btn">Back to home</a>
    </div>
</body>
</html>
