<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Malappuram logo palette — navy, royal blue, gold */
            --navy-950: #041525;
            --navy-900: #0a2744;
            --navy-800: #0f3d7a;
            --navy-700: #1a4f8c;
            --navy-600: #1e5aa8;
            --blue-500: #2563eb;
            --gold-400: #fbbf24;
            --gold-500: #eab308;
            --gold-600: #ca8a04;
            --green-600: #16a34a;
            --sky-100: #e0f2fe;
            --sky-50:  #f0f9ff;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100dvh;
            background: var(--navy-950);
            color: #fff;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Background ── */
        .portal-bg {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 65% 50% at 20% 5%, rgba(234,179,8,.14) 0%, transparent 55%),
                radial-gradient(ellipse 55% 45% at 80% 95%, rgba(37,99,235,.18) 0%, transparent 50%),
                radial-gradient(ellipse 45% 40% at 50% 50%, rgba(30,90,168,.12) 0%, transparent 60%),
                linear-gradient(165deg, var(--navy-950) 0%, var(--navy-900) 40%, var(--navy-800) 100%);
        }
        .portal-grid {
            position: fixed; inset: 0; z-index: 0; opacity: .03;
            background-image:
                linear-gradient(rgba(255,255,255,.9) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.9) 1px, transparent 1px);
            background-size: 56px 56px;
        }
        .portal-orb {
            position: fixed; border-radius: 50%; filter: blur(80px); z-index: 0; pointer-events: none;
        }
        .portal-orb-1 { width: 320px; height: 320px; top: -80px; right: -60px; background: rgba(234,179,8,.12); }
        .portal-orb-2 { width: 240px; height: 240px; bottom: -40px; left: -40px; background: rgba(37,99,235,.14); }

        /* ── Layout ── */
        .portal-wrap {
            position: relative; z-index: 1;
            min-height: 100dvh;
            display: flex; align-items: center; justify-content: center;
            padding: 1rem;
        }
        @media (max-width: 639px) {
            .portal-wrap { align-items: flex-start; padding-top: 1.25rem; padding-bottom: 1.25rem; }
        }
        .portal-shell {
            width: 100%;
            max-width: 54rem;
            display: grid;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(255,255,255,.08),
                0 32px 80px rgba(0,0,0,.5),
                0 8px 24px rgba(15,61,122,.25);
        }
        @media (min-width: 640px) {
            .portal-shell { grid-template-columns: 1fr 1.05fr; }
        }
        @media (max-width: 639px) {
            .portal-shell { max-width: 26rem; }
        }

        /* ── Brand panel ── */
        .portal-brand {
            background: linear-gradient(155deg, var(--navy-900) 0%, var(--navy-800) 45%, var(--navy-700) 100%);
            padding: 2.5rem 2rem;
            display: flex; flex-direction: column;
            justify-content: center; gap: .85rem;
            position: relative; overflow: hidden;
        }
        .portal-brand::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse 80% 60% at 30% 15%, rgba(234,179,8,.1) 0%, transparent 55%);
            pointer-events: none;
        }
        .portal-brand::after {
            content: '';
            position: absolute; bottom: -2rem; right: -2rem;
            width: 10rem; height: 10rem; border-radius: 50%;
            background: radial-gradient(circle, rgba(37,99,235,.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .portal-brand > * { position: relative; z-index: 1; }
        @media (max-width: 639px) {
            .portal-brand { padding: 1.5rem 1.25rem 1.25rem; align-items: center; text-align: center; gap: .6rem; }
            .portal-tagline { font-size: .8125rem; }
            .portal-contacts { display: none; }
            .portal-motto { display: none; }
        }

        .portal-logo-wrap {
            display: flex; align-items: center; justify-content: center;
            width: 7.5rem; height: 7.5rem;
            border-radius: 50%;
            background: transparent;
            border: 2px solid rgba(255,255,255,.35);
            box-shadow: 0 12px 32px rgba(0,0,0,.3);
            margin-bottom: .25rem;
            flex-shrink: 0;
            overflow: hidden;
            padding: 0;
        }
        @media (max-width: 639px) {
            .portal-logo-wrap { width: 6rem; height: 6rem; margin: 0 auto .25rem; }
        }

        .portal-logo {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
            transform: scale(1.18);
            transform-origin: center center;
        }
        .portal-avatar {
            width: 100%; height: 100%; border-radius: 50%;
            background: linear-gradient(135deg, var(--navy-800), var(--navy-600));
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 800; color: var(--gold-400);
        }
        @media (max-width: 639px) { .portal-avatar { font-size: 1.5rem; } }

        .portal-logo-sm-wrap {
            width: 3rem; height: 3rem; border-radius: 50%;
            background: transparent;
            border: 2px solid #e2e8f0;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; overflow: hidden; padding: 0;
        }
        .portal-logo-sm { width: 100%; height: 100%; object-fit: cover; display: block; transform: scale(1.18); transform-origin: center center; }

        .portal-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .625rem; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: var(--gold-400);
            background: rgba(234,179,8,.12);
            border: 1px solid rgba(234,179,8,.28);
            padding: .35rem .8rem; border-radius: 999px;
            align-self: flex-start;
        }
        @media (max-width: 639px) { .portal-badge { align-self: center; } }

        .portal-title {
            font-size: 1.625rem; font-weight: 800; color: #fff;
            line-height: 1.2; letter-spacing: -.02em;
        }
        .portal-tagline {
            font-size: .875rem; color: rgba(255,255,255,.6);
            line-height: 1.55; max-width: 22rem;
        }
        .portal-motto {
            font-size: .8125rem; font-style: italic; color: var(--gold-400);
            padding-left: .75rem; border-left: 2px solid rgba(251,191,36,.45);
            margin-top: .25rem;
        }
        .portal-contacts {
            display: flex; flex-direction: column; gap: .4rem;
            margin-top: .5rem; padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,.1);
        }
        .portal-contact {
            display: inline-flex; align-items: center; gap: .5rem;
            font-size: .75rem; color: rgba(255,255,255,.5);
            text-decoration: none; transition: color .15s;
        }
        a.portal-contact:hover { color: var(--gold-400); }
        .portal-contact svg { opacity: .65; flex-shrink: 0; }

        /* ── Actions panel ── */
        .portal-panel {
            background: #fff;
            padding: 2.5rem 2rem;
            display: flex; flex-direction: column; justify-content: center; gap: 1.75rem;
        }
        @media (max-width: 639px) { .portal-panel { padding: 1.75rem 1.5rem; gap: 1.5rem; } }

        .portal-panel-intro {
            display: flex; flex-direction: column; gap: .5rem;
        }
        .portal-panel-heading {
            font-size: .6875rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: var(--navy-600);
            margin: 0;
        }
        .portal-panel-title {
            font-size: 1.375rem; font-weight: 800; color: var(--navy-900);
            line-height: 1.3; margin: 0;
        }
        .portal-panel-sub {
            font-size: .875rem; color: #64748b; line-height: 1.6;
            margin: 0; max-width: 26rem;
        }

        .portal-actions { display: flex; flex-direction: column; gap: .875rem; }

        .portal-action {
            display: flex; align-items: center; gap: 1rem;
            padding: 1rem 1.15rem;
            border-radius: .875rem;
            text-decoration: none;
            transition: transform .12s, box-shadow .15s, border-color .15s, background .15s;
            border: 1.5px solid transparent;
        }
        .portal-action:hover { transform: translateY(-2px); }
        .portal-action-primary {
            background: linear-gradient(135deg, var(--sky-50) 0%, #dbeafe 100%);
            border-color: #93c5fd;
            color: var(--navy-900);
            box-shadow: 0 4px 16px rgba(15,61,122,.1);
        }
        .portal-action-primary:hover {
            box-shadow: 0 8px 24px rgba(15,61,122,.18);
            border-color: var(--navy-600);
        }
        .portal-action-secondary {
            background: #fff;
            border-color: #e2e8f0;
            color: var(--navy-900);
        }
        .portal-action-secondary:hover {
            border-color: var(--navy-600);
            background: var(--sky-50);
            box-shadow: 0 4px 16px rgba(0,0,0,.06);
        }
        .portal-action-icon {
            width: 2.75rem; height: 2.75rem; border-radius: .75rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .portal-action-primary .portal-action-icon {
            background: linear-gradient(135deg, var(--navy-800), var(--navy-600));
            color: var(--gold-400);
            box-shadow: 0 4px 12px rgba(15,61,122,.35);
        }
        .portal-action-secondary .portal-action-icon {
            background: #f1f5f9; color: var(--navy-700);
        }
        .portal-action-secondary:hover .portal-action-icon {
            background: var(--navy-800); color: var(--gold-400);
        }
        .portal-action-body { flex: 1; min-width: 0; }
        .portal-action-title { font-size: .9375rem; font-weight: 700; color: var(--navy-900); display: block; }
        .portal-action-desc  { font-size: .75rem; color: #64748b; margin-top: .1rem; display: block; }
        .portal-action-arrow {
            color: #94a3b8; font-size: 1.1rem; flex-shrink: 0;
            transition: transform .15s, color .15s;
        }
        .portal-action:hover .portal-action-arrow { transform: translateX(3px); color: var(--navy-600); }
        .portal-action-ghost {
            padding: .75rem 1rem;
            border-radius: .75rem;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            text-decoration: none;
            color: inherit;
            display: flex; align-items: center; gap: .75rem;
            transition: border-color .15s, background .15s;
        }
        .portal-action-ghost:hover {
            border-color: var(--navy-600);
            background: #fff;
        }
        .portal-action-ghost .portal-action-title { font-size: .8125rem; font-weight: 600; color: #475569; }

        /* ── Form card ── */
        .portal-form-wrap { width: 100%; max-width: 30rem; }
        .portal-card {
            width: 100%;
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 0 0 1px rgba(255,255,255,.06), 0 32px 80px rgba(0,0,0,.45);
            color: var(--navy-900);
        }
        .portal-card-header {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.75rem 2rem 0;
        }
        .portal-card-body { padding: 1.5rem 2rem 2rem; }
        @media (max-width: 639px) {
            .portal-card-header, .portal-card-body { padding-left: 1.5rem; padding-right: 1.5rem; }
        }
        .portal-card-title { font-size: 1.125rem; font-weight: 800; color: var(--navy-900); line-height: 1.3; }
        .portal-card-sub   { font-size: .8125rem; color: #64748b; margin-top: .15rem; }

        .portal-back {
            display: inline-flex; align-items: center; gap: .4rem;
            font-size: .8125rem; font-weight: 600; color: rgba(255,255,255,.55);
            text-decoration: none; margin-bottom: 1.25rem;
            transition: color .15s;
        }
        .portal-back:hover { color: var(--gold-400); }

        /* ── Form elements ── */
        .portal-input {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: .75rem;
            padding: .7rem .9rem;
            font-size: .875rem;
            font-family: inherit;
            color: var(--navy-900);
            background: #f8fafc;
            transition: border-color .15s, box-shadow .15s, background .15s;
            outline: none;
        }
        .portal-input:focus {
            border-color: var(--navy-600);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(30,90,168,.15);
        }
        .portal-input.is-error { border-color: #f87171; background: #fff; }
        .portal-label {
            display: block;
            font-size: .75rem; font-weight: 600; color: #334155;
            margin-bottom: .4rem; letter-spacing: .01em;
        }
        .portal-required { color: #dc2626; }
        .portal-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            width: 100%;
            padding: .85rem 1.25rem;
            border: none; border-radius: .75rem;
            font-size: .875rem; font-weight: 700; font-family: inherit;
            cursor: pointer; transition: transform .1s, box-shadow .15s;
        }
        .portal-btn:active { transform: scale(.98); }
        .portal-btn-primary {
            background: linear-gradient(135deg, var(--navy-800), var(--navy-600));
            color: #fff;
            box-shadow: 0 4px 14px rgba(15,61,122,.4);
        }
        .portal-btn-primary:hover { box-shadow: 0 6px 20px rgba(15,61,122,.5); }
        .portal-error { font-size: .75rem; color: #dc2626; margin-top: .35rem; }
        .portal-alert {
            padding: .75rem 1rem; border-radius: .75rem;
            font-size: .8125rem; font-weight: 500; margin-bottom: 1.25rem;
        }
        .portal-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .portal-alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .field-grid { display: grid; gap: 1rem; align-items: start; }
        @media (min-width: 480px) { .field-grid-2 { grid-template-columns: 1fr 1fr; } }
        .field-span-2 { grid-column: 1 / -1; }
        .field-hidden { display: none; }
        .portal-hint {
            font-size: .6875rem; color: #94a3b8; margin-top: .35rem; line-height: 1.4;
        }
        .portal-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2364748b' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .85rem center;
            padding-right: 2.25rem;
        }
        .portal-field-hint {
            font-size: .6875rem; color: #94a3b8; margin-top: .35rem; line-height: 1.4;
        }
        .portal-form-section-title {
            font-size: .6875rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: #64748b; margin-top: .25rem;
        }
        .portal-form-actions {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            margin-top: .5rem; flex-wrap: wrap;
        }
        .portal-form-link {
            font-size: .8125rem; color: var(--navy-600); text-decoration: none; font-weight: 600;
        }
        .portal-form-link:hover { text-decoration: underline; }
        .portal-form-actions .portal-btn { width: auto; min-width: 9rem; }

        .portal-form-steps {
            display: flex; align-items: center; gap: .5rem;
            margin-bottom: .25rem;
        }
        .portal-form-step {
            display: flex; align-items: center; gap: .5rem;
            font-size: .75rem; font-weight: 600; color: #94a3b8;
        }
        .portal-form-step.is-active { color: var(--navy-800); }
        .portal-form-step.is-done { color: var(--navy-600); }
        .portal-form-step-num {
            width: 1.5rem; height: 1.5rem; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .6875rem; font-weight: 700;
            background: #e2e8f0; color: #64748b;
        }
        .portal-form-step.is-active .portal-form-step-num {
            background: var(--navy-700); color: #fff;
        }
        .portal-form-step.is-done .portal-form-step-num {
            background: #dbeafe; color: var(--navy-700);
        }
        .portal-form-step-line {
            flex: 1; height: 2px; background: #e2e8f0; border-radius: 1px;
            max-width: 2.5rem;
        }
        .portal-form-step-line.is-done { background: #bfdbfe; }
        .portal-form-step-panel { display: none; flex-direction: column; gap: 1rem; }
        .portal-form-step-panel.is-active { display: flex; }
        .portal-btn-secondary {
            background: #f1f5f9; color: var(--navy-800);
            border: 1.5px solid #e2e8f0;
        }
        .portal-btn-secondary:hover {
            background: #e2e8f0;
            box-shadow: 0 4px 12px rgba(15,23,42,.08);
        }
        .portal-form-actions-end { margin-left: auto; display: flex; gap: .75rem; align-items: center; }

        .portal-footer-note {
            text-align: center; font-size: .6875rem; color: rgba(255,255,255,.35);
            margin-top: 1.25rem; padding-bottom: .5rem;
        }

        /* ── Full pages (login / register) ── */
        .portal-page { width: 100%; max-width: 58rem; }
        .portal-shell--form { max-width: 58rem; }
        @media (min-width: 640px) {
            .portal-shell--form { grid-template-columns: .95fr 1.05fr; }
        }
        .portal-panel--form {
            gap: 1.5rem;
            max-height: none;
        }
        .portal-form { display: flex; flex-direction: column; gap: 1rem; }
        .portal-form-note {
            font-size: .75rem; color: #94a3b8; line-height: 1.5;
            padding: .75rem 1rem; background: #f8fafc;
            border: 1px solid #e2e8f0; border-radius: .75rem;
        }
        .portal-steps {
            list-style: none; display: flex; flex-direction: column; gap: .5rem;
            margin-top: .5rem; padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,.1);
            counter-reset: portal-step;
        }
        .portal-steps li {
            counter-increment: portal-step;
            display: flex; align-items: flex-start; gap: .65rem;
            font-size: .8125rem; color: rgba(255,255,255,.65); line-height: 1.45;
        }
        .portal-steps li::before {
            content: counter(portal-step);
            flex-shrink: 0;
            width: 1.35rem; height: 1.35rem; border-radius: 50%;
            background: rgba(234,179,8,.15); border: 1px solid rgba(234,179,8,.35);
            color: var(--gold-400); font-size: .6875rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        @media (max-width: 639px) {
            .portal-steps { display: none; }
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="portal-bg"></div>
    <div class="portal-grid"></div>
    <div class="portal-orb portal-orb-1"></div>
    <div class="portal-orb portal-orb-2"></div>
    @yield('content')
</body>
</html>
