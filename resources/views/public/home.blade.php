@extends('layouts.public')

@section('content')
    @php use App\Support\SectionVariantResolver; @endphp
    @forelse($sections as $section)
        @php
            [$sectionType, $variant] = SectionVariantResolver::path($section->section_type, $section->variant);
            $anchor = str_replace('_', '-', $section->section_type);
        @endphp
        <div id="{{ $anchor }}" class="scroll-mt-24">
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
