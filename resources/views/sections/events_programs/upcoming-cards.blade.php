@php
    use App\Support\SahodayaPublicData;
    $events = SahodayaPublicData::upcomingEvents($tenant->id);
@endphp
<section id="events" class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-white border-t border-slate-200">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
            <div>
                @if(!empty($config['eyebrow']))
                <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3 py-1 rounded-md mb-2">
                    {{ $config['eyebrow'] }}
                </span>
                @endif
                <h2 class="font-heading text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                    {{ $config['heading'] ?? 'Upcoming Programmes & Events' }}
                </h2>
            </div>
            
            <a href="/m/v2/events" class="v2-btn-primary inline-flex items-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition">
                <span>View Full Calendar</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </a>
        </div>

        @if($events->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($events as $event)
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="v2-card flex flex-col justify-between overflow-hidden group">
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold v2-badge-primary">
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                            <time>
                                {{ $event->event_date ? $event->event_date->format('M j, Y') : 'TBA' }}
                            </time>
                        </div>
                        @if($event->type)
                        <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">
                            {{ $event->type }}
                        </span>
                        @endif
                    </div>

                    <div>
                        <h3 class="font-heading font-bold text-slate-900 text-base group-hover:text-primary transition-colors leading-snug">
                            {{ $event->name }}
                        </h3>
                        @if($event->venue)
                        <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-2">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            <span>{{ $event->venue }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-primary">
                    <span>Programme Details</span>
                    <svg class="w-4 h-4 text-primary group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </div>
            </a>
            @endforeach
        </div>
        @else
        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center bg-slate-50">
            <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
            <h3 class="text-base font-bold text-slate-800">No Upcoming Programmes Yet</h3>
            <p class="text-sm text-slate-500 mt-1">Events and programmes will be listed here as they're scheduled.</p>
        </div>
        @endif
    </div>
</section>
