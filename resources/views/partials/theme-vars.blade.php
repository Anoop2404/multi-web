{{-- Inject tenant theme as CSS custom properties --}}
@php
    $theme = $tenantTheme ?? [];
@endphp
<style>
    :root {
        --color-primary: {{ $theme['primary'] ?? '#1e40af' }};
        --color-secondary: {{ $theme['secondary'] ?? '#7c3aed' }};
        --font-heading: {{ $theme['font_heading'] ?? 'Inter' }}, sans-serif;
        --font-body: {{ $theme['font_body'] ?? 'Inter' }}, sans-serif;
        --border-radius: {{ $theme['border_radius'] ?? '0.5rem' }};
    }
    .font-heading { font-family: var(--font-heading); }
    .font-body { font-family: var(--font-body); }
    .text-primary { color: var(--color-primary); }
    .bg-primary { background-color: var(--color-primary); }
    .border-primary { border-color: var(--color-primary); }
</style>
