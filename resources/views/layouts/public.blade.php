<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @php
        $seo          = $seo ?? [];
        $pageTitle    = $seo['title'] ?? ($tenant->name ?? 'School');
        $pageDesc     = $seo['description'] ?? ('Welcome to ' . ($tenant->name ?? 'our school') . '. ' . ($seo['tagline'] ?? ''));
        $ogImage      = $seo['og_image'] ?? ($tenant->logo ?? '');
        $canonicalUrl = request()->url();
    @endphp

    <title>@yield('title', $pageTitle)</title>
    <meta name="description" content="@yield('description', $pageDesc)">
    @if(!empty($seo['keywords']))
    <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ $canonicalUrl }}">
    <meta property="og:title"       content="@yield('title', $pageTitle)">
    <meta property="og:description" content="@yield('description', $pageDesc)">
    @if($ogImage)
    <meta property="og:image"       content="{{ $ogImage }}">
    @endif
    <meta property="og:site_name"   content="{{ $tenant->name ?? '' }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="@yield('title', $pageTitle)">
    <meta name="twitter:description" content="@yield('description', $pageDesc)">
    @if($ogImage)
    <meta name="twitter:image"       content="{{ $ogImage }}">
    @endif

    {{-- Canonical --}}
    <link rel="canonical" href="{{ $canonicalUrl }}">

    @yield('meta')

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

    {{-- Floating widgets --}}
    @include('partials.widgets.whatsapp')
    @include('partials.widgets.cbse-badge')

    {{-- Visitor counter --}}
    @include('partials.widgets.visitor-counter')

</body>
</html>
