@php
    $items = collect($config['items'] ?? [])->filter(fn ($i) => !empty($i['title']));
    $icons = [
        'education' => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
        'shield'    => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.75h-.152c-3.196 0-6.1-1.248-8.25-3.286z',
        'bulb'      => 'M9 18h6m-5 3h4M12 3a6 6 0 00-4 10.472V16a1 1 0 001 1h6a1 1 0 001-1v-2.528A6 6 0 0012 3z',
        'heart'     => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
        'smile'     => 'M9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm5.25 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zM9 15c.75 1 1.9 1.5 3 1.5s2.25-.5 3-1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    $defaultIcon = 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z';
@endphp
@if($items->isNotEmpty())
<section class="py-16 px-4" style="background-color: color-mix(in srgb, var(--color-primary) 4%, white)">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
            @if(!empty($config['eyebrow']))
            <p class="inline-block text-xs font-semibold uppercase tracking-widest px-3 py-1.5 rounded mb-4"
               style="background-color: color-mix(in srgb, var(--color-primary) 12%, transparent); color: var(--color-primary)">
                {{ $config['eyebrow'] }}
            </p>
            @endif
            <h2 class="text-3xl md:text-4xl font-bold font-heading text-gray-900">
                {{ $config['heading_line1'] ?? 'Why Choose Us' }}
                @if(!empty($config['heading_line2']))<br>{{ $config['heading_line2'] }}@endif
            </h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($items as $item)
            @php
                $bg = $item['bg_color'] ?? null;
                $stroke = $item['stroke_color'] ?? null;
                $path = $icons[$item['icon'] ?? ''] ?? $defaultIcon;
            @endphp
            <div class="text-center p-7 rounded-2xl border-[1.5px] hover:-translate-y-1.5 transition"
                 style="border-color: color-mix(in srgb, var(--color-primary) 10%, #eef2f7)">
                <div class="w-[58px] h-[58px] mx-auto rounded-2xl flex items-center justify-center mb-4"
                     style="background-color: {{ $bg ?: 'color-mix(in srgb, var(--color-primary) 12%, transparent)' }}">
                    <svg class="w-7 h-7" style="color: {{ $stroke ?: 'var(--color-primary)' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $path }}"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-900 mb-2">{{ $item['title'] }}</h3>
                @if(!empty($item['description']))
                <p class="text-sm text-gray-500 leading-relaxed">{{ $item['description'] }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
