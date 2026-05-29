@php
    $limit    = $config['limit'] ?? 6;
    $upcoming = $config['show_upcoming'] ?? true;
    $query    = \App\Models\Event::where('tenant_id', $tenant->id);
    if ($upcoming) {
        $query->where('start_date', '>=', now()->startOfDay());
    }
    $events = $query->orderBy('start_date')->limit($limit)->get();
@endphp
@if($events->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-10">
            <div>
                @if(!empty($config['eyebrow']))
                <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
                @endif
                <h2 class="text-3xl font-bold font-heading text-gray-900">
                    {{ $config['heading'] ?? 'Upcoming Events' }}
                </h2>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($events as $event)
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition group">
                @if($event->image)
                <div class="aspect-video overflow-hidden relative">
                    <img loading="lazy" src="{{ $event->image }}" alt="{{ $event->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    {{-- Date badge --}}
                    <div class="absolute top-3 left-3 bg-white rounded-xl px-3 py-2 text-center shadow">
                        <div class="text-xl font-bold leading-none" style="color: var(--color-primary)">
                            {{ $event->start_date->format('d') }}
                        </div>
                        <div class="text-xs uppercase text-gray-500">{{ $event->start_date->format('M') }}</div>
                    </div>
                </div>
                @else
                <div class="bg-gradient-to-br from-primary/10 to-secondary/10 p-8 flex items-center justify-center"
                     style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 10%, white), color-mix(in srgb, var(--color-secondary, #6366f1) 10%, white))">
                    <div class="text-center">
                        <div class="text-5xl font-bold" style="color: var(--color-primary)">{{ $event->start_date->format('d') }}</div>
                        <div class="text-lg uppercase text-gray-500">{{ $event->start_date->format('M Y') }}</div>
                    </div>
                </div>
                @endif

                <div class="p-5">
                    <h3 class="font-bold text-gray-900 text-lg mb-2 line-clamp-2">{{ $event->title }}</h3>
                    @if($event->venue)
                    <p class="text-sm text-gray-500 flex items-center gap-1 mb-2">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $event->venue }}
                    </p>
                    @endif
                    @if($event->end_date && $event->end_date->ne($event->start_date))
                    <p class="text-xs text-gray-400">
                        {{ $event->start_date->format('d M') }} – {{ $event->end_date->format('d M Y') }}
                    </p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
