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
        $ogImage      = $pageSeo['og_image'] ?? ($seo['og_image'] ?? ($logo ?? ''));
        $ogType       = $pageSeo['og_type'] ?? 'website';
        $canonicalUrl = request()->url();
        // Built here (not inline in the template) so the '@' + word keys survive
        // Blade's directive-detection pass untouched — see layouts/public.blade.php
        // for the equivalent block that trips over this when written inline.
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => ($tenant->type ?? null) === 'sahodaya' ? 'Organization' : 'EducationalOrganization',
            'name' => $tenant->name ?? '',
            'url' => $canonicalUrl,
            'description' => $pageDesc,
            'image' => $ogImage ?: null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

    <script type="application/ld+json">{!! $jsonLd !!}</script>

    {{-- Twitter Card --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDesc }}">
    @if($ogImage)
    <meta name="twitter:image"       content="{{ $ogImage }}">
    @endif

    <link rel="canonical" href="{{ $canonicalUrl }}">

    @include('partials.font-preload', ['tenantTheme' => $tenantTheme ?? []])
    @include('partials.theme-vars')

    @vite(['resources/css/app.css', 'resources/js/public.js'])

    <style>
        /* Focused event-page chrome — deliberately not the full site header/footer.
           A live scoreboard is a destination in itself; visitors here came for the
           event, not the school website, so keep the frame minimal and get out
           of the way of the content. */
        .event-chrome-header {
            background: linear-gradient(180deg, rgba(0,0,0,.55), rgba(0,0,0,0) 100%), #020617;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .event-chrome-logo { filter: drop-shadow(0 1px 2px rgba(0,0,0,.4)); }
    </style>
</head>
<body class="font-body bg-slate-950 text-white min-h-screen experience-{{ $experience['key'] ?? 'classic' }}" data-experience="{{ $experience['key'] ?? 'classic' }}" data-motion="{{ ($tenantTheme ?? [])['motion'] ?? 'expressive' }}" style="color:#fff">

    @php
        // A visitor several taps into an event (live → results → item-results …) only had
        // "All Events" to escape back to the top level — nothing offered a way back to
        // *this* event without hunting for a link at the bottom of a possibly long page.
        // Show a persistent one-level-up breadcrumb whenever we're inside an event and not
        // already on its own home page.
        $insideEventSubpage = isset($event) && ! request()->routeIs('tenant.fest.show');
    @endphp
    {{-- Sticky in its own right — every page's own sticky elements (tab nav, filter
         bars) have their `top-*` offsets calibrated against this header's height
         alone. The breadcrumb below is a SIBLING, not a child: position:sticky can
         only stay pinned while scrolled within its own containing block, so nesting
         a short-lived (non-sticky) breadcrumb inside the same wrapper would cap how
         far this header could stick to that wrapper's total height, and it would
         detach and scroll away with it instead of staying pinned. --}}
    <header class="event-chrome-header sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
            <a href="{{ route('tenant.fest.index') }}" class="flex items-center gap-2.5 min-w-0 group">
                @if($logo ?? null)
                <img src="{{ $logo }}" alt="" class="event-chrome-logo w-8 h-8 rounded-full object-contain bg-white/10 p-1 shrink-0">
                @else
                <span class="event-chrome-logo w-8 h-8 rounded-full bg-accent flex items-center justify-center text-sm shrink-0">🏆</span>
                @endif
                <span class="min-w-0">
                    <span class="block text-sm font-bold text-white truncate group-hover:text-accent transition-colors">{{ $tenant->name ?? 'Sahodaya' }}</span>
                    <span class="block text-[10px] uppercase tracking-widest text-white/40">Public Event Portal</span>
                </span>
            </a>
            <a href="{{ route('tenant.fest.index') }}" class="shrink-0 text-xs font-bold text-white/70 hover:text-white border border-white/15 hover:border-white/30 rounded-full px-3.5 py-2 transition-colors">
                All Events
            </a>
        </div>
    </header>
    @if($insideEventSubpage)
    <div class="border-b border-white/10 bg-black/20">
        <div class="max-w-6xl mx-auto px-4 py-2">
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-400 hover:text-amber-300 transition-colors max-w-full">
                <span aria-hidden="true">←</span>
                <span class="truncate">{{ $event->title }}</span>
            </a>
        </div>
    </div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-white/10 bg-slate-950 mt-16">
        <div class="max-w-6xl mx-auto px-4 py-6 flex flex-wrap items-center justify-between gap-3 text-xs text-white/40">
            <span>© {{ date('Y') }} {{ $tenant->name ?? 'Sahodaya' }}</span>
            <a href="{{ url('/') }}" class="hover:text-white/70 transition-colors">Visit main site →</a>
        </div>
    </footer>

</body>
</html>
