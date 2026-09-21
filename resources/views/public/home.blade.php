@extends('layouts.public')

@section('content')
    @php
        use App\Support\SectionVariantResolver;
        use App\Support\SiteSectionMedia;
    @endphp
    @forelse($sections as $section)
        @php
            [$sectionType, $variant] = SectionVariantResolver::path($section->section_type, $section->variant);
            $rawConfig = !empty($previewMode) ? ($section->config ?? []) : $section->publicConfig();
            $resolvedConfig = SiteSectionMedia::resolveConfig($tenant, $sectionType, $variant, $rawConfig);
        @endphp
        <x-site-section-frame :section="$section" :experience="$experience ?? []" :preview-mode="!empty($previewMode)" default-width="standard">
        @includeIf("sections.{$sectionType}.{$variant}", [
            'config'  => $resolvedConfig,
            'section' => $section,
            'tenant'  => $tenant,
            'logo'    => $logo ?? \App\Support\TenantBranding::logoUrl($tenant),
        ])
        </x-site-section-frame>
    @empty
        <div class="min-h-screen flex items-center justify-center text-gray-400">
            <p>This site is being set up. Please check back soon.</p>
        </div>
    @endforelse
@endsection
