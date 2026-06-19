<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale ?? 'en') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @php
        $pageSeo      = $pageSeo ?? [];
        $seo          = $seo ?? [];
        $pageTitle    = $pageSeo['title'] ?? ($seo['title'] ?? ($tenant->name ?? 'School'));
        $pageDesc     = $pageSeo['description'] ?? ($seo['description'] ?? ('Welcome to ' . ($tenant->name ?? 'our school') . '. ' . ($seo['tagline'] ?? '')));
        $ogImage      = $pageSeo['og_image'] ?? ($seo['og_image'] ?? ($tenant->logo ?? ''));
        $ogType       = $pageSeo['og_type'] ?? 'website';
        $canonicalUrl = request()->url();
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDesc }}">
    @if(!empty($seo['keywords']))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type"        content="{{ $ogType }}">
    <meta property="og:url"         content="{{ $canonicalUrl }}">
    <meta property="og:title"       content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDesc }}">
    @if($ogImage)
    <meta property="og:image"       content="{{ $ogImage }}">
    @endif
    <meta property="og:site_name"   content="{{ $tenant->name ?? '' }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDesc }}">
    @if($ogImage)
    <meta name="twitter:image"       content="{{ $ogImage }}">
    @endif

    {{-- Canonical --}}
    <link rel="canonical" href="{{ $canonicalUrl }}">

    @include('partials.font-preload', ['tenantTheme' => $tenantTheme ?? []])

    {{-- Theme CSS variables injected server-side --}}
    @include('partials.theme-vars')

    @vite(['resources/css/app.css', 'resources/js/public.js'])
</head>
<body class="font-body text-gray-800 bg-white">

    {{-- Global widgets: topbar (phone/email/socials) --}}
    @include('partials.widgets.topbar')

    {{-- Sticky navigation --}}
    @include('partials.navbar')

    {{-- Admission open / announcement banner (dismissible) --}}
    @include('partials.widgets.admission-banner')

    {{-- News ticker below navbar --}}
    @include('partials.widgets.ticker')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    @if($widgets['social_strip']['show'] ?? false)
    @include('partials.widgets.socials')
    @endif

    {{-- Floating widgets --}}
    @include('partials.widgets.whatsapp')
    @include('partials.widgets.cbse-badge')

    {{-- Visitor counter --}}
    @include('partials.widgets.visitor-counter')

    {{-- Touch-friendly image lightbox --}}
    @include('partials.widgets.lightbox')

</body>
</html>
