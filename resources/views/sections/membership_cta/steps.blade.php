@php
    $steps = collect($config['steps'] ?? [])->filter(fn ($s) => !empty($s['title']))->values();
    // Literal class strings only — Tailwind's static scanner can't see a dynamically interpolated column count.
    $stepGridClass = match (true) {
        $steps->count() <= 1 => 'grid-cols-1 max-w-sm mx-auto',
        $steps->count() === 2 => 'sm:grid-cols-2 max-w-2xl mx-auto',
        $steps->count() === 3 => 'sm:grid-cols-3',
        default => 'sm:grid-cols-2 lg:grid-cols-4',
    };
@endphp
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 text-white relative overflow-hidden"
         style="background: linear-gradient(150deg, var(--color-primary) 0%, #0a0a0f 85%);">
    <div class="absolute inset-0 opacity-[0.06] bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] pointer-events-none"></div>

    <div class="max-w-5xl mx-auto relative z-10">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-3">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest bg-white/10 border border-white/20 px-3.5 py-1.5 rounded-full text-white/90">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading tracking-tight">
                {{ $config['heading'] ?? 'Join the Network' }}
            </h2>
            <p class="text-sm sm:text-base text-slate-300 leading-relaxed">
                {{ $config['subheading'] ?? ('Bring your CBSE affiliated school into ' . ($tenant->name ?? 'the Sahodaya network') . ' and take part in shared training, events, and academic collaboration.') }}
            </p>
        </div>

        @if($steps->isNotEmpty())
        <div class="grid {{ $stepGridClass }} gap-4 mb-10">
            @foreach($steps as $i => $step)
            <div class="bg-white/[0.06] border border-white/15 rounded-2xl p-5">
                <div class="w-9 h-9 rounded-full bg-accent text-white flex items-center justify-center text-sm font-bold mb-3">{{ $i + 1 }}</div>
                <h3 class="font-heading font-bold text-sm mb-1">{{ $step['title'] }}</h3>
                @if(!empty($step['description']))
                <p class="text-xs text-slate-300 leading-relaxed">{{ $step['description'] }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <div class="text-center">
            <a href="{{ route('school-register.create') }}" class="v2-btn-accent inline-flex items-center gap-2 font-bold px-7 py-3.5 rounded-xl shadow-lg text-sm">
                <span>{{ $config['cta_label'] ?? 'Apply for Membership' }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>
    </div>
</section>
