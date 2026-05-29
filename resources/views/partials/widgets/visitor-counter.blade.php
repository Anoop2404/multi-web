{{-- Visitor Counter Widget --}}
@php
    $visitorActive = $widgets['visitor_counter']['active'] ?? false;
@endphp
@if($visitorActive)
<div class="text-xs text-gray-400 text-center py-2">
    <span>Visitors: {{ $widgets['visitor_counter']['count'] ?? '—' }}</span>
</div>
@endif