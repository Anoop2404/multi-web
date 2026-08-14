{{-- Inject tenant theme as CSS custom properties --}}
@php
    $theme = $tenantTheme ?? [];
@endphp
<style>
    :root {
        --color-primary: {{ $theme['primary'] ?? '#1e40af' }};
        --color-secondary: {{ $theme['secondary'] ?? '#7c3aed' }};
        --color-accent: {{ $theme['accent_color'] ?? '#f59e0b' }};
        --font-heading: {{ $theme['display_font'] ?? $theme['font_heading'] ?? 'Inter' }}, sans-serif;
        --font-body: {{ $theme['body_font'] ?? $theme['font_body'] ?? 'Inter' }}, sans-serif;
        --border-radius: {{ $theme['border_radius'] ?? '0.5rem' }};
        --navbar-style: {{ $theme['navbar_style'] ?? 'light' }};
        --footer-style: {{ $theme['footer_style'] ?? 'dark' }};
        --site-section-gap: {{ ($theme['density'] ?? 'comfortable') === 'compact' ? '3rem' : ((($theme['density'] ?? 'comfortable') === 'spacious') ? '6rem' : '4.5rem') }};
        --site-corner: {{ ($theme['corners'] ?? 'soft') === 'square' ? '0.15rem' : ((($theme['corners'] ?? 'soft') === 'rounded') ? '1.5rem' : '0.75rem') }};
    }
    .font-heading { font-family: var(--font-heading); }
    .font-body { font-family: var(--font-body); }
    .text-primary { color: var(--color-primary); }
    .text-accent { color: var(--color-accent); }
    .bg-primary { background-color: var(--color-primary); }
    .bg-accent { background-color: var(--color-accent); }
    .border-primary { border-color: var(--color-primary); }
    .site-section-frame { position: relative; background: #fff; }
    .site-section-frame > section { padding-top: var(--site-section-gap) !important; padding-bottom: var(--site-section-gap) !important; }
    .site-section-width-narrow > section > div { max-width: 48rem !important; }
    .site-section-width-standard > section > div { max-width: 64rem !important; }
    .site-section-width-wide > section > div { max-width: 80rem !important; }
    .site-section-width-full > section > div { max-width: none !important; }
    .site-section-spacing-compact > section { padding-top: 2.5rem !important; padding-bottom: 2.5rem !important; }
    .site-section-spacing-spacious > section { padding-top: 7rem !important; padding-bottom: 7rem !important; }
    .site-section-surface-muted, .site-section-surface-muted > section { background: color-mix(in srgb, var(--color-primary) 5%, white) !important; }
    .site-section-surface-primary, .site-section-surface-primary > section { background: var(--color-primary) !important; color: white; }
    .site-section-surface-dark, .site-section-surface-dark > section { background: #0f172a !important; color: white; }
    .site-section-heading-center h2 { text-align: center; }
    .site-section-media-framed img { border-radius: var(--site-corner); box-shadow: 0 20px 50px rgba(15,23,42,.14); }
    [data-experience="events-results-live"] .site-section-frame { border-bottom: 1px solid rgba(49,46,129,.09); }
    [data-experience="academic-resources"] .site-section-frame p { line-height: 1.8; }
    [data-experience="confederation-governance"] h1,
    [data-experience="confederation-governance"] h2 { letter-spacing: -.025em; }
    [data-experience="network-directory"] .site-section-frame [class*="rounded"] { border-radius: var(--site-corner); }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
</style>
