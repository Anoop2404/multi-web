@php $awards = collect($config['awards'] ?? [])->filter(fn ($a) => !empty($a['title']))->values(); @endphp
@if($awards->isNotEmpty())
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-white border-t border-slate-200">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-accent px-3.5 py-1.5 rounded-full">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'Awards & Recognition' }}
            </h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($awards as $i => $award)
            <article class="v2-card overflow-hidden flex flex-col">
                @if(!empty($award['image']))
                <div class="aspect-[16/10] overflow-hidden">
                    <img loading="lazy" src="{{ $award['image'] }}" alt="{{ $award['title'] }}" class="v2-media w-full h-full object-cover">
                </div>
                @else
                <div class="aspect-[16/10] flex items-center justify-center v2-chip-{{ ['a','b','c','d'][$i % 4] }}">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 003-3V8.25a3 3 0 00-3-3h-9a3 3 0 00-3 3v7.5a3 3 0 003 3m9 0v-1.5A2.25 2.25 0 0014.25 15h-4.5A2.25 2.25 0 007.5 17.25v1.5m9-6h.008v.008H16.5v-.008zM7.5 10.5h.008v.008H7.5v-.008z"/></svg>
                </div>
                @endif
                <div class="p-5 space-y-2 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-heading font-bold text-slate-900 text-base leading-snug">{{ $award['title'] }}</h3>
                        @if(!empty($award['year']))
                        <span class="text-xs font-bold text-accent shrink-0">{{ $award['year'] }}</span>
                        @endif
                    </div>
                    @if(!empty($award['description']))
                    <p class="text-sm text-slate-500 leading-relaxed">{{ $award['description'] }}</p>
                    @endif
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
