@php $items = collect($config['items'] ?? [])->filter(fn ($i) => !empty($i['question']) && !empty($i['answer']))->values(); @endphp
@if($items->isNotEmpty())
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-white border-t border-slate-200" x-data="{ open: 0 }">
    <div class="max-w-3xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'Frequently Asked Questions' }}
            </h2>
            @if(!empty($config['subheading']))
            <p class="text-sm sm:text-base text-slate-600 font-normal leading-relaxed">{{ $config['subheading'] }}</p>
            @endif
        </div>

        <div class="space-y-3">
            @foreach($items as $i => $item)
            <div class="v2-card overflow-hidden">
                <button type="button" @click="open = (open === {{ $i }} ? null : {{ $i }})"
                        class="w-full flex items-center justify-between gap-4 text-left px-5 py-4 cursor-pointer"
                        :aria-expanded="open === {{ $i }}" aria-controls="faq-answer-{{ $i }}">
                    <span class="font-heading font-bold text-slate-900 text-sm sm:text-base">{{ $item['question'] }}</span>
                    <svg class="w-5 h-5 text-primary shrink-0 transition-transform duration-200" :class="open === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div id="faq-answer-{{ $i }}" x-show="open === {{ $i }}" x-collapse role="region">
                    <p class="px-5 pb-4 text-sm text-slate-600 leading-relaxed">{{ $item['answer'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
