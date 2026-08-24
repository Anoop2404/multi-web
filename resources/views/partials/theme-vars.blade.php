{{-- Inject tenant theme as CSS custom properties --}}
@php
    $theme = $tenantTheme ?? [];
    $typeScale = $theme['type_scale'] ?? 'balanced';
    $surface = $theme['surface'] ?? null;
    $buttons = $theme['buttons'] ?? 'solid';
    $motion = $theme['motion'] ?? 'expressive';

    // V1 legacy fallback (no surface token set at all): byte-identical to the pre-token .v2-card rule, so untouched V1/legacy sites render unchanged.
    $legacyCard = ['bg' => '#ffffff', 'border' => '#e2e8f0', 'shadow' => 'var(--shadow-card)', 'shadowHover' => 'var(--shadow-card-hover)', 'lift' => '-4px'];
    $cardStyles = $surface === null ? $legacyCard : ([
        'flat'     => ['bg' => '#ffffff', 'border' => 'transparent', 'shadow' => 'none', 'shadowHover' => '0 1px 2px rgba(15,23,42,0.04)', 'lift' => '0'],
        'bordered' => ['bg' => '#ffffff', 'border' => '#e2e8f0', 'shadow' => 'none', 'shadowHover' => 'var(--shadow-card)', 'lift' => '-2px'],
        'soft'     => ['bg' => '#ffffff', 'border' => 'transparent', 'shadow' => 'var(--shadow-card)', 'shadowHover' => 'var(--shadow-card-hover)', 'lift' => '-4px'],
        'elevated' => ['bg' => '#ffffff', 'border' => 'transparent', 'shadow' => 'var(--shadow-card-hover)', 'shadowHover' => '0 25px 50px -8px rgba(15,23,42,0.18)', 'lift' => '-6px'],
    ][$surface] ?? $legacyCard);
@endphp
<style>
    :root {
        --color-primary: {{ $theme['primary'] ?? $theme['primary_color'] ?? '#0F2942' }};
        --color-secondary: {{ $theme['secondary'] ?? $theme['secondary_color'] ?? '#163B60' }};
        --color-accent: {{ $theme['accent_color'] ?? $theme['accent'] ?? '#D97706' }};
        --color-primary-light: color-mix(in srgb, var(--color-primary) 10%, #ffffff);
        --color-primary-border: color-mix(in srgb, var(--color-primary) 25%, #cbd5e1);
        --color-primary-text: color-mix(in srgb, var(--color-primary) 85%, #000000);
        --color-accent-light: color-mix(in srgb, var(--color-accent) 15%, #ffffff);
        --color-accent-border: color-mix(in srgb, var(--color-accent) 35%, #e2e8f0);
        --color-accent-text: color-mix(in srgb, var(--color-accent) 95%, #000000);
        --color-chip-a: var(--color-primary);
        --color-chip-b: var(--color-secondary);
        --color-chip-c: var(--color-accent);
        --color-chip-d: color-mix(in srgb, var(--color-secondary) 55%, var(--color-accent));
        --font-heading: '{{ $theme['display_font'] ?? $theme['font_heading'] ?? 'Manrope' }}', sans-serif;
        --font-body: '{{ $theme['body_font'] ?? $theme['font_body'] ?? 'Inter' }}', sans-serif;
        --border-radius: {{ $theme['border_radius'] ?? '0.875rem' }};
        --navbar-style: {{ $theme['navbar_style'] ?? 'light' }};
        --footer-style: {{ $theme['footer_style'] ?? 'dark' }};
        --site-section-gap: {{ ($theme['density'] ?? 'comfortable') === 'compact' ? '3rem' : ((($theme['density'] ?? 'comfortable') === 'spacious') ? '6rem' : '4.5rem') }};
        --site-corner: {{ ($theme['corners'] ?? 'soft') === 'square' ? '0.25rem' : ((($theme['corners'] ?? 'soft') === 'rounded') ? '1.5rem' : '0.875rem') }};
        --shadow-card: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
        --shadow-card-hover: 0 20px 35px -5px rgba(15, 23, 42, 0.1), 0 8px 15px -4px rgba(15, 23, 42, 0.06);
        --motion-fast: {{ $motion === 'none' ? '0.01ms' : ($motion === 'restrained' ? '0.12s' : '0.2s') }};
        --motion-base: {{ $motion === 'none' ? '0.01ms' : ($motion === 'restrained' ? '0.18s' : '0.25s') }};
        --motion-lift: {{ $motion === 'restrained' ? '-2px' : $cardStyles['lift'] }};
    }
    .font-heading { font-family: var(--font-heading); font-weight: 700; letter-spacing: -0.02em; }
    .font-body { font-family: var(--font-body); }
    .text-primary { color: var(--color-primary); }
    .text-secondary { color: var(--color-secondary); }
    .text-accent { color: var(--color-accent); }
    .bg-primary { background-color: var(--color-primary); }
    .bg-secondary { background-color: var(--color-secondary); }
    .bg-accent { background-color: var(--color-accent); }
    .border-primary { border-color: var(--color-primary); }
    .border-accent { border-color: var(--color-accent); }

    {{-- Type scale: compact tightens the whole rem-based scale, editorial opens up reading rhythm --}}
    @if($typeScale === 'compact')
    html { font-size: 93.75%; }
    .font-heading { letter-spacing: -0.01em; }
    @elseif($typeScale === 'editorial')
    .site-section-frame p, .site-section-frame li { line-height: 1.75; }
    .font-heading { letter-spacing: -0.01em; }
    @endif

    {{-- Controlled multi-color chip palette: replaces ad hoc rainbow Tailwind colors with on-brand tints --}}
    .v2-chip-a { background: color-mix(in srgb, var(--color-chip-a) 12%, #fff); color: color-mix(in srgb, var(--color-chip-a) 88%, #000); }
    .v2-chip-b { background: color-mix(in srgb, var(--color-chip-b) 12%, #fff); color: color-mix(in srgb, var(--color-chip-b) 88%, #000); }
    .v2-chip-c { background: color-mix(in srgb, var(--color-chip-c) 15%, #fff); color: color-mix(in srgb, var(--color-chip-c) 90%, #000); }
    .v2-chip-d { background: color-mix(in srgb, var(--color-chip-d) 14%, #fff); color: color-mix(in srgb, var(--color-chip-d) 90%, #000); }

    {{-- Horizontal nav-item rows: scrolls instead of wrapping when a tenant configures many menu items --}}
    .nav-scroll { overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
    .nav-scroll::-webkit-scrollbar { display: none; }
    .nav-scroll > * { flex-shrink: 0; }

    {{-- Image character: applied via .v2-media on photographic <img> tags --}}
    .v2-media { filter: none; }
    @if(($theme['images'] ?? 'documentary') === 'vibrant')
    .v2-media { filter: saturate(1.18) contrast(1.03); }
    @elseif(($theme['images'] ?? 'documentary') === 'formal')
    .v2-media { filter: saturate(0.82) contrast(1.05); }
    @elseif(($theme['images'] ?? 'documentary') === 'monochrome')
    .v2-media { filter: grayscale(1) contrast(1.05); }
    @endif

    .v2-badge-primary {
        background-color: var(--color-primary-light);
        color: var(--color-primary-text);
        border: 1px solid var(--color-primary-border);
    }
    .v2-badge-accent {
        background-color: var(--color-accent-light);
        color: var(--color-accent-text);
        border: 1px solid var(--color-accent-border);
    }

    {{-- Button character: solid (filled), bordered (outline), understated (ghost/link) --}}
    @if($buttons === 'bordered')
    .v2-btn-primary { background-color: transparent; color: var(--color-primary); border: 2px solid var(--color-primary); transition: all var(--motion-fast) ease; }
    .v2-btn-primary:hover { background-color: var(--color-primary-light); }
    .v2-btn-accent { background-color: transparent; color: var(--color-accent-text); border: 2px solid var(--color-accent); font-weight: 700; transition: all var(--motion-fast) ease; }
    .v2-btn-accent:hover { background-color: var(--color-accent-light); }
    @elseif($buttons === 'understated')
    .v2-btn-primary { background-color: transparent; color: var(--color-primary); padding-left: 0.25rem; padding-right: 0.25rem; text-decoration: underline; text-underline-offset: 3px; box-shadow: none !important; transition: color var(--motion-fast) ease; }
    .v2-btn-primary:hover { color: var(--color-secondary); filter: none; box-shadow: none; }
    .v2-btn-accent { background-color: transparent; color: var(--color-accent-text); font-weight: 700; text-decoration: underline; text-underline-offset: 3px; box-shadow: none !important; transition: color var(--motion-fast) ease; }
    .v2-btn-accent:hover { filter: none; box-shadow: none; }
    @else
    .v2-btn-primary {
        background-color: var(--color-primary);
        color: #ffffff;
        transition: all var(--motion-fast) ease;
    }
    .v2-btn-primary:hover {
        filter: brightness(1.12);
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
    }
    .v2-btn-accent {
        background-color: var(--color-accent);
        color: #ffffff;
        font-weight: 700;
        transition: all var(--motion-fast) ease;
    }
    .v2-btn-accent:hover {
        filter: brightness(1.1);
        box-shadow: 0 10px 25px -5px rgba(217, 119, 6, 0.3);
    }
    @endif

    .site-section-frame { position: relative; background: #ffffff; transition: background-color 0.2s ease; }
    .site-section-frame > section { padding-top: var(--site-section-gap) !important; padding-bottom: var(--site-section-gap) !important; }
    .site-section-width-narrow > section > div { max-width: 48rem !important; }
    .site-section-width-standard > section > div { max-width: 64rem !important; }
    .site-section-width-wide > section > div { max-width: 80rem !important; }
    .site-section-width-full > section > div { max-width: none !important; }
    .site-section-spacing-compact > section { padding-top: 2.5rem !important; padding-bottom: 2.5rem !important; }
    .site-section-spacing-spacious > section { padding-top: 6.5rem !important; padding-bottom: 6.5rem !important; }
    .site-section-surface-canvas { background: #ffffff; }
    .site-section-surface-muted { background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
    {{-- Was `background: linear-gradient(...)` — rendered as fully transparent in practice
         (verified live), leaving this variant's white text invisible against the page's own
         white background. background-color as an explicit fallback plus the gradient as a
         separate background-image (rather than the shorthand) guarantees a solid, visible
         color even in whatever circumstance was dropping the gradient. See Documents/Path_breaks.md. --}}
    .site-section-surface-primary { background-color: var(--color-primary); background-image: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%); color: white; }
    .site-section-surface-dark { background: var(--color-primary); color: white; }
    .site-section-heading-center h2 { text-align: center; }
    .site-section-heading-center p { margin-left: auto; margin-right: auto; text-align: center; }
    .site-section-media-framed img { border-radius: var(--site-corner); box-shadow: var(--shadow-card-hover); }

    {{-- Surface character: flat / bordered / soft / elevated, resolved server-side from $theme['surface'] --}}
    .v2-card {
        background-color: {{ $cardStyles['bg'] }};
        border: 1px solid {{ $cardStyles['border'] }};
        border-radius: var(--site-corner);
        box-shadow: {{ $cardStyles['shadow'] }};
        transition: transform var(--motion-base) cubic-bezier(0.16, 1, 0.3, 1), box-shadow var(--motion-base) ease, border-color var(--motion-base) ease;
    }
    .v2-card:hover {
        transform: translateY(var(--motion-lift));
        box-shadow: {{ $cardStyles['shadowHover'] }};
        border-color: var(--color-primary-border);
    }

    [data-experience="network-directory"] {
        --font-heading: 'Manrope', sans-serif;
        --font-body: 'Inter', sans-serif;
    }
    [data-experience="network-directory"] .font-heading {
        letter-spacing: -0.025em;
    }
    [data-experience="events-results-live"] .site-section-frame { border-bottom: 1px solid rgba(49,46,129,.09); }
    [data-experience="academic-resources"] .site-section-frame p { line-height: 1.8; }
    [data-experience="confederation-governance"] h1,
    [data-experience="confederation-governance"] h2 { letter-spacing: -.025em; }
    [data-experience="network-directory"] .site-section-frame [class*="rounded"] { border-radius: var(--site-corner); }

    {{-- Motion character: none/restrained turn off decorative looping animation and reduce hover travel --}}
    @if($motion === 'none')
    [data-motion="none"] *, [data-motion="none"] *::before, [data-motion="none"] *::after {
        scroll-behavior: auto !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important;
    }
    @elseif($motion === 'restrained')
    [data-motion="restrained"] .animate-pulse,
    [data-motion="restrained"] .animate-marquee,
    [data-motion="restrained"] .animate-bounce {
        animation: none !important;
    }
    @endif
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
</style>
