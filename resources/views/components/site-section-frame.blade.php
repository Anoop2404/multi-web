@props([
    'section',
    'experience' => [],
    'previewMode' => false,
    'defaultWidth' => 'standard',
])
@php
    $layout = $previewMode ? ($section->layout_json ?? []) : $section->publicLayout();
    $isV2Section = ($experience['experience_version'] ?? 'v1') === 'v2' || !empty($layout);
    $anchor = str_replace('_', '-', $section->section_type);
    $aliases = [
        'about_sahodaya' => 'about',
        'events_programs' => 'programmes',
        'news_circulars' => 'news',
        'testimonials_sahodaya' => 'testimonials',
    ];
    $alias = $aliases[$section->section_type] ?? null;
    $frameClasses = $isV2Section
        ? collect([
            'site-section-frame',
            'site-section-width-'.($layout['width'] ?? $defaultWidth),
            'site-section-spacing-'.($layout['spacing'] ?? 'standard'),
            'site-section-surface-'.($layout['surface'] ?? 'canvas'),
            'site-section-heading-'.($layout['heading_alignment'] ?? 'left'),
            'site-section-media-'.($layout['media_treatment'] ?? 'natural'),
        ])->implode(' ')
        : 'legacy-site-section';
@endphp
<div id="{{ $anchor }}" class="{{ $frameClasses }} scroll-mt-24" data-section-type="{{ $section->section_type }}">
    @if($alias)
        <span id="{{ $alias }}" class="scroll-mt-24 block -mt-24 pointer-events-none"></span>
    @endif
    {{ $slot }}
</div>
