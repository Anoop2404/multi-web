@php
    $theme = $tenantTheme ?? [];
    $extractFont = fn (?string $value) => trim(explode(',', $value ?? '')[0] ?? '');
    $fonts = array_values(array_unique(array_filter([
        $extractFont($theme['display_font'] ?? $theme['font_heading'] ?? 'Manrope'),
        $extractFont($theme['body_font'] ?? $theme['font_body'] ?? 'Inter'),
    ])));

    $families = collect($fonts)
        ->map(fn ($font) => str_replace(' ', '+', $font).':wght@400;500;600;700')
        ->implode('&family=');

    $fontsUrl = $families
        ? 'https://fonts.googleapis.com/css2?family='.$families.'&display=swap'
        : null;
@endphp
@if($fontsUrl)
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="{{ $fontsUrl }}">
<link rel="stylesheet" href="{{ $fontsUrl }}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{{ $fontsUrl }}"></noscript>
@endif
