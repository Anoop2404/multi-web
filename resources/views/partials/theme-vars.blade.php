{{-- Inject tenant theme as CSS custom properties --}}
@php
    $theme = $tenantTheme ?? [];
@endphp
<style>
    :root {
        --color-primary: {{ $theme['primary'] ?? '#1e40af' }};
        --color-secondary: {{ $theme['secondary'] ?? '#7c3aed' }};
        --color-accent: {{ $theme['accent_color'] ?? '#f59e0b' }};
        --font-heading: {{ $theme['font_heading'] ?? 'Inter' }}, sans-serif;
        --font-body: {{ $theme['font_body'] ?? 'Inter' }}, sans-serif;
        --border-radius: {{ $theme['border_radius'] ?? '0.5rem' }};
        --navbar-style: {{ $theme['navbar_style'] ?? 'light' }};
        --footer-style: {{ $theme['footer_style'] ?? 'dark' }};
    }
    .font-heading { font-family: var(--font-heading); }
    .font-body { font-family: var(--font-body); }
    .text-primary { color: var(--color-primary); }
    .text-accent { color: var(--color-accent); }
    .bg-primary { background-color: var(--color-primary); }
    .bg-accent { background-color: var(--color-accent); }
    .border-primary { border-color: var(--color-primary); }
</style>
