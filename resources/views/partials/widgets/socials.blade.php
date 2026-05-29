{{-- Social Media Strip --}}
@php
    $socialLinks = $widgets['social_links'] ?? [];
@endphp
@if(!empty($socialLinks))
<div class="flex justify-center gap-3 py-3">
    @foreach($socialLinks as $platform => $url)
        @if(!empty($url))
        <a href="{{ $url }}" target="_blank" rel="noopener"
           class="w-8 h-8 rounded-full flex items-center justify-center text-white hover:opacity-80 transition"
           style="background-color: var(--color-primary);">
            @include("partials.widgets.social-icons.{$platform}")
        </a>
        @endif
    @endforeach
</div>
@endif