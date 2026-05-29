@php
    $limit  = $config['limit'] ?? 8;
    $events = \App\Models\Event::where('tenant_id', $tenant->id)
        ->orderBy('start_date')->limit($limit)->get();
@endphp
@if($events->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-3xl font-bold font-heading text-gray-900 mb-12 text-center">
            {{ $config['heading'] ?? 'Events Calendar' }}
        </h2>

        <div class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-14 top-0 bottom-0 w-px bg-gray-200"></div>

            <div class="space-y-6">
                @foreach($events as $event)
                <div class="flex gap-6 group">
                    {{-- Date column --}}
                    <div class="w-28 shrink-0 text-right pr-6 relative">
                        <div class="absolute right-[-5px] top-3 w-2.5 h-2.5 rounded-full border-2 border-white ring-2"
                             style="background-color: var(--color-primary); ring-color: var(--color-primary)"></div>
                        <div class="text-2xl font-bold leading-none" style="color: var(--color-primary)">
                            {{ $event->start_date->format('d') }}
                        </div>
                        <div class="text-xs text-gray-400 uppercase">{{ $event->start_date->format('M Y') }}</div>
                    </div>

                    {{-- Event card --}}
                    <div class="flex-1 bg-gray-50 rounded-xl p-4 hover:shadow-sm transition">
                        <h3 class="font-bold text-gray-900 mb-1">{{ $event->title }}</h3>
                        @if($event->venue)
                        <p class="text-sm text-gray-500">{{ $event->venue }}</p>
                        @endif
                        @if($event->description)
                        <p class="text-sm text-gray-400 mt-1 line-clamp-2">{{ $event->description }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
