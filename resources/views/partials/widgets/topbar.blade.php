@php
    $topbar  = $widgets['topbar'] ?? [];
    $socials = $widgets['social_links'] ?? [];
    $show    = $topbar['show'] ?? true;
@endphp
@if($show)
<div class="bg-gray-900 text-gray-300 text-xs py-1.5 px-4">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            @if(!empty($topbar['phone']))
            <a href="tel:{{ $topbar['phone'] }}" class="flex items-center gap-1 hover:text-white transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                {{ $topbar['phone'] }}
            </a>
            @endif
            @if(!empty($topbar['email']))
            <a href="mailto:{{ $topbar['email'] }}" class="flex items-center gap-1 hover:text-white transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                {{ $topbar['email'] }}
            </a>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @foreach(['facebook','youtube','instagram','twitter','linkedin'] as $platform)
                @if(!empty($socials[$platform]))
                <a href="{{ $socials[$platform] }}" target="_blank" rel="noopener"
                   class="hover:text-white transition-colors" aria-label="{{ ucfirst($platform) }}">
                    @include("partials.widgets.social-icons.{$platform}")
                </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endif
