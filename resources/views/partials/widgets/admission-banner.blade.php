@php
    $banner = $widgets['admission_banner'] ?? [];
    $show   = $banner['show'] ?? false;
@endphp
@if($show && !empty($banner['message']))
<div x-data="{ open: true }" x-show="open" x-transition.duration.300ms
     class="bg-amber-500 text-white text-sm py-2 px-4 relative">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div class="flex items-center gap-2 flex-1">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
            <span class="font-medium">{{ $banner['message'] }}</span>
            @if(!empty($banner['link_url']) && !empty($banner['link_text']))
            <a href="{{ $banner['link_url'] }}"
               class="ml-2 underline font-semibold hover:text-amber-100 transition-colors whitespace-nowrap">
                {{ $banner['link_text'] }}
            </a>
            @endif
        </div>
        <button @click="open = false" aria-label="Dismiss"
                class="shrink-0 hover:text-amber-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
@endif
