{{-- sports_meet/schedule-cards.blade.php — Events/schedule grid for sports meet --}}
<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-xs font-bold uppercase tracking-[0.2em] mb-2" style="color: var(--color-secondary)">
                {{ $config['eyebrow'] }}
            </p>
            @endif
            <h2 class="font-heading text-3xl sm:text-4xl font-bold text-gray-900">
                {{ $config['heading'] ?? 'Sports Meet Schedule' }}
            </h2>
            @if(!empty($config['date_range']))
            <p class="mt-2 text-gray-500 text-sm">📅 {{ $config['date_range'] }}</p>
            @endif
            @if(!empty($config['venue']))
            <p class="mt-1 text-gray-500 text-sm">📍 {{ $config['venue'] }}</p>
            @endif
        </div>

        @php $events = $config['events'] ?? []; @endphp
        @if(!empty($events))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($events as $event)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition group">
                <div class="flex items-start justify-between mb-3">
                    <span class="text-2xl">{{ $event['icon'] ?? '🏃' }}</span>
                    @if(!empty($event['category']))
                    <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full"
                          style="background: color-mix(in srgb, var(--color-primary) 12%, white); color: var(--color-primary)">
                        {{ $event['category'] }}
                    </span>
                    @endif
                </div>
                <h4 class="font-bold text-gray-900 group-hover:text-purple-700 transition">{{ $event['name'] ?? '' }}</h4>
                @if(!empty($event['time']))
                <p class="text-xs text-gray-500 mt-1">⏰ {{ $event['time'] }}</p>
                @endif
                @if(!empty($event['venue']))
                <p class="text-xs text-gray-500">📍 {{ $event['venue'] }}</p>
                @endif
                @if(!empty($event['age_group']))
                <p class="text-xs text-gray-500">👤 {{ $event['age_group'] }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center text-gray-400 py-8">Add events in the section editor to display the schedule.</div>
        @endif

        @if(!empty($config['registration_url']))
        <div class="text-center mt-10">
            <a href="{{ $config['registration_url'] }}"
               class="inline-flex items-center gap-2 text-white font-bold px-7 py-3.5 rounded-full text-sm shadow-lg hover:opacity-90 transition"
               style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary))">
                {{ $config['registration_label'] ?? 'Register Now' }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
        @endif
    </div>
</section>
