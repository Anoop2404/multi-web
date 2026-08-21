@php
    // $position (int, required) and $size (px, optional, default 56) are passed in via @include.
    $size = $size ?? 56;
    $src = asset('images/fest/medals/rank-' . min(max((int) $position, 1), 6) . '.webp');
@endphp
<img src="{{ $src }}" alt="Rank {{ $position }}" width="{{ $size }}" height="{{ $size }}"
     class="shrink-0 object-contain" style="width: {{ $size }}px; height: {{ $size }}px; filter: drop-shadow(0 2px 4px rgba(0,0,0,.45))">
