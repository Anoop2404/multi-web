@php use App\Support\SahodayaPublicData; $events = SahodayaPublicData::upcomingEvents($tenant->id); @endphp
<section id="events" class="py-16 px-4 scroll-mt-24">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">{{ $config['eyebrow'] ?? 'Calendar' }}</p>
            <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['heading'] ?? 'Upcoming Events' }}</h2>
        </div>
        @if($events->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($events as $event)
            <article class="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-md transition">
                <div class="px-5 py-3 border-b border-gray-50" style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 6%, white), white);">
                    <time class="text-xs font-bold text-purple-600">
                        {{ $event->event_date ? $event->event_date->format('M j, Y') : 'TBA' }}
                    </time>
                    @if($event->type)<span class="ml-2 text-[10px] uppercase text-gray-400">{{ $event->type }}</span>@endif
                </div>
                <div class="p-5">
                    <h3 class="font-heading font-bold text-gray-900">{{ $event->name }}</h3>
                    @if($event->venue)<p class="text-xs text-gray-500 mt-2">📍 {{ $event->venue }}</p>@endif
                </div>
            </article>
            @endforeach
        </div>
        @else
        <p class="text-center text-gray-400 bg-gray-50 rounded-2xl py-12">Events will appear here once scheduled in admin.</p>
        @endif
    </div>
</section>
