@extends('layouts.public')

@section('content')
@php
    // Primary-site standalone pages have no $microsite — link back to the root site
    // instead of a /m/{slug} prefix.
    $micrositeHome = $microsite
        ? route('tenant.site.microsite', ['slug' => $microsite->slug], false)
        : '';
    $homeLabel = $microsite ? 'Home' : ($tenant->name ?? 'Home');
@endphp

{{-- Sub-Page Banner Header --}}
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 border-b border-white/10 text-white relative overflow-hidden"
         style="background: linear-gradient(135deg, var(--color-primary, #0F2942) 0%, var(--color-secondary, #163B60) 100%);">
    
    {{-- Ambient Light Halo --}}
    <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-amber-500/10 blur-3xl pointer-events-none"></div>
    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10 space-y-4">
        {{-- Breadcrumbs Navigation --}}
        <nav class="flex items-center gap-2 text-xs font-semibold text-slate-300">
            <a href="{{ $micrositeHome ?: '/' }}" class="hover:text-amber-300 transition-colors flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                <span>{{ $homeLabel }}</span>
            </a>
            <span>/</span>
            <span class="text-amber-300 truncate">{{ $pageConfig['title'] ?? 'Page' }}</span>
        </nav>

        {{-- Eyebrow & Page Heading --}}
        <div class="space-y-2">
            @if(!empty($pageConfig['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest bg-white/10 backdrop-blur-md px-3.5 py-1 rounded-full border border-white/20 text-amber-300 shadow-sm">
                {{ $pageConfig['eyebrow'] }}
            </span>
            @endif

            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold font-heading text-white tracking-tight">
                {{ $pageConfig['title'] ?? 'Sahodaya Module' }}
            </h1>

            @if(!empty($pageConfig['subheading']))
            <p class="text-base sm:text-lg text-slate-200 font-normal leading-relaxed max-w-3xl">
                {{ $pageConfig['subheading'] }}
            </p>
            @endif
        </div>
    </div>
</section>

{{-- Sub-Page Main Content Sections --}}
<div class="subpage-content bg-slate-50 min-h-[60vh]">
    @php use App\Support\SectionVariantResolver; @endphp
    @forelse($sections as $section)
        @php
            [$sectionType, $variant] = SectionVariantResolver::path($section->section_type, $section->variant);
        @endphp
        <x-site-section-frame :section="$section" :experience="$experience ?? []" :preview-mode="!empty($previewMode)" default-width="wide">
        @includeIf("sections.{$sectionType}.{$variant}", [
            'config'  => (!empty($previewMode) ? ($section->config ?? []) : $section->publicConfig()),
            'section' => $section,
            'tenant'  => $tenant,
            'logo'    => $logo ?? \App\Support\TenantBranding::logoUrl($tenant),
        ])
        </x-site-section-frame>
    @empty
        <div class="max-w-7xl mx-auto py-16 px-4 text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center mx-auto text-2xl font-bold">ℹ</div>
            <h2 class="text-2xl font-bold text-slate-900 font-heading">Content Coming Soon</h2>
            <p class="text-slate-600 max-w-md mx-auto">This page is being prepared by the secretariat desk.</p>
            <a href="{{ $micrositeHome ?: '/' }}" class="inline-block v2-btn-accent font-bold px-6 py-2.5 rounded-xl text-sm shadow-md">
                Back to Homepage
            </a>
        </div>
    @endforelse
</div>

{{-- Bottom Quick Navigation Bar --}}
<section class="py-8 bg-white border-t border-slate-200 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3 text-xs font-semibold text-slate-500">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>Official Portal for {{ $tenant->name }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-4 text-xs font-bold text-slate-700">
            <a href="{{ $micrositeHome }}/about" class="hover:text-primary transition-colors">About</a>
            <a href="{{ $micrositeHome }}/member-schools" class="hover:text-primary transition-colors">Directory</a>
            <a href="{{ $micrositeHome }}/office-bearers" class="hover:text-primary transition-colors">Leadership</a>
            <a href="{{ $micrositeHome }}/gallery" class="hover:text-primary transition-colors">Gallery</a>
            <a href="{{ $micrositeHome }}/announcements" class="hover:text-primary transition-colors">Circulars</a>
            <a href="{{ $micrositeHome }}/events" class="hover:text-primary transition-colors">Events</a>
            <a href="{{ $micrositeHome }}/downloads" class="hover:text-primary transition-colors">Downloads</a>
            <a href="{{ $micrositeHome }}/contact" class="hover:text-primary transition-colors">Contact</a>
        </div>
    </div>
</section>
@endsection
