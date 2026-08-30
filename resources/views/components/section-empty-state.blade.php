@props(['title', 'subtitle', 'icon' => null, 'dark' => false])
@php
    $borderClass = $dark ? 'border-white/15' : 'border-slate-200';
    $bgClass = $dark ? 'bg-white/5' : 'bg-white';
    $iconClass = $dark ? 'text-slate-500' : 'text-slate-400';
    $titleClass = $dark ? 'text-white' : 'text-slate-800';
    $subtitleClass = $dark ? 'text-slate-400' : 'text-slate-500';
    $defaultIcon = 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
@endphp
<div {{ $attributes->merge(['class' => "rounded-2xl border-2 border-dashed {$borderClass} p-12 text-center {$bgClass}"]) }}>
    <svg class="w-12 h-12 {{ $iconClass }} mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon ?? $defaultIcon }}" />
    </svg>
    <h3 class="text-base font-bold {{ $titleClass }}">{{ $title }}</h3>
    <p class="text-sm {{ $subtitleClass }} mt-1">{{ $subtitle }}</p>
</div>
