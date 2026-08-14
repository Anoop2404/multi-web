@extends('layouts.public')

@section('content')
    @php use App\Support\SectionVariantResolver; @endphp
    @forelse($sections as $section)
        @php
            [$sectionType, $variant] = SectionVariantResolver::path($section->section_type, $section->variant);
            $anchor = str_replace('_', '-', $section->section_type);
        @endphp
        @php
            $layout = !empty($previewMode) ? ($section->layout_json ?? []) : $section->publicLayout();
            $isV2Section = ($experience['experience_version'] ?? 'v1') === 'v2' || !empty($layout);
            $frameClasses = $isV2Section
                ? collect([
                    'site-section-frame',
                    'site-section-width-'.($layout['width'] ?? 'standard'),
                    'site-section-spacing-'.($layout['spacing'] ?? 'standard'),
                    'site-section-surface-'.($layout['surface'] ?? 'canvas'),
                    'site-section-heading-'.($layout['heading_alignment'] ?? 'left'),
                    'site-section-media-'.($layout['media_treatment'] ?? 'natural'),
                ])->implode(' ')
                : 'legacy-site-section';
        @endphp
        <div id="{{ $anchor }}" class="{{ $frameClasses }} scroll-mt-24" data-section-type="{{ $section->section_type }}">
        @includeIf("sections.{$sectionType}.{$variant}", [
            'config'  => (!empty($previewMode) ? ($section->config ?? []) : $section->publicConfig()),
            'section' => $section,
            'tenant'  => $tenant,
            'logo'    => $logo ?? \App\Support\TenantBranding::logoUrl($tenant),
        ])
        </div>
    @empty
        <div class="min-h-screen flex items-center justify-center text-gray-400">
            <p>This site is being set up. Please check back soon.</p>
        </div>
    @endforelse
@endsection
