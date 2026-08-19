@php $quotes = collect($config['quotes'] ?? [])->filter(fn ($q) => !empty($q['quote']) && !empty($q['name']))->values(); @endphp
@if($quotes->isNotEmpty())
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-slate-50 border-t border-slate-200">
    <div class="max-w-6xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'What People Say' }}
            </h2>
        </div>

        <div class="grid sm:grid-cols-2 gap-6">
            @foreach($quotes as $i => $quote)
            <article class="v2-card p-6 flex flex-col gap-4">
                <svg class="w-8 h-8 text-accent opacity-70" fill="currentColor" viewBox="0 0 32 32"><path d="M9.352 22.851c-1.53 0-2.796-.508-3.797-1.524C4.554 20.311 4 18.951 4 17.279c0-1.529.407-3.128 1.221-4.797.814-1.669 2.021-3.198 3.621-4.586L11.7 9.5c-1.221 1.06-2.157 2.132-2.807 3.213-.65 1.082-.976 1.994-.976 2.735 0 .353.09.618.27.795.181.176.436.264.766.264.688 0 1.324.147 1.906.44.583.294 1.05.71 1.4 1.25.35.54.525 1.17.525 1.89 0 .953-.318 1.735-.953 2.346-.635.612-1.463.918-2.484.918zm11.598 0c-1.53 0-2.796-.508-3.797-1.524-1.001-1.016-1.502-2.376-1.502-4.048 0-1.529.407-3.128 1.221-4.797.814-1.669 2.021-3.198 3.621-4.586l2.858 2.604c-1.221 1.06-2.157 2.132-2.807 3.213-.65 1.082-.976 1.994-.976 2.735 0 .353.09.618.27.795.181.176.436.264.766.264.688 0 1.324.147 1.906.44.583.294 1.05.71 1.4 1.25.35.54.525 1.17.525 1.89 0 .953-.318 1.735-.953 2.346-.635.612-1.463.918-2.484.918z"/></svg>

                <blockquote class="text-slate-700 leading-relaxed flex-1">
                    {{ $quote['quote'] }}
                </blockquote>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                    <div class="w-11 h-11 rounded-full v2-chip-{{ ['a','b','c','d'][$i % 4] }} flex items-center justify-center text-sm font-bold shrink-0 overflow-hidden">
                        @if(!empty($quote['photo']))
                        <img loading="lazy" src="{{ $quote['photo'] }}" alt="" class="w-full h-full object-cover">
                        @else
                        {{ strtoupper(substr($quote['name'], 0, 1)) }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-sm text-slate-900 truncate">{{ $quote['name'] }}</p>
                        <p class="text-xs text-slate-500 truncate">
                            {{ collect([$quote['designation'] ?? null, $quote['school'] ?? null])->filter()->implode(', ') }}
                        </p>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
