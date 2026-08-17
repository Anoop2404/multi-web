<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-slate-50 border-y border-slate-200 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-[28rem] h-[28rem] rounded-full opacity-[0.05] -translate-y-1/3 translate-x-1/3 pointer-events-none" style="background: radial-gradient(circle, var(--color-primary), transparent 70%);"></div>
    <div class="max-w-7xl mx-auto relative">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-12 items-center">
            {{-- Left Column: Copy & Mission --}}
            <div class="space-y-5">
                @if(!empty($config['eyebrow']))
                <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-accent px-3.5 py-1.5 rounded-full">
                    {{ $config['eyebrow'] }}
                </span>
                @endif

                <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight leading-snug">
                    {{ $config['heading'] ?? 'Stronger Schools Through Collaboration' }}
                </h2>

                <div class="text-slate-600 text-base leading-relaxed space-y-4 font-normal">
                    @if(!empty($config['content']))
                    {!! nl2br(e($config['content'])) !!}
                    @else
                    <p>
                        {{ $tenant->name }} brings together CBSE affiliated institutions across {{ $tenant->region->name ?? 'the region' }} to foster educational excellence, teacher training, academic innovation, and collaborative growth.
                    </p>
                    <p>
                        By sharing knowledge, conducting regional sports & cultural meets, and promoting best pedagogical practices, we empower principals, teachers, and students to reach their full potential.
                    </p>
                    @endif
                </div>

                {{-- Pillar Badges --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-4">
                    <div class="flex items-center gap-3 p-3.5 rounded-xl bg-white border border-slate-200 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_6px_16px_rgba(15,23,42,0.05)] transition-transform duration-200 hover:-translate-y-0.5">
                        <div class="w-9 h-9 rounded-lg v2-chip-a flex items-center justify-center shrink-0">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-800">Academic Leadership</span>
                    </div>

                    <div class="flex items-center gap-3 p-3.5 rounded-xl bg-white border border-slate-200 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_6px_16px_rgba(15,23,42,0.05)] transition-transform duration-200 hover:-translate-y-0.5">
                        <div class="w-9 h-9 rounded-lg v2-chip-b flex items-center justify-center shrink-0">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a5.97 5.97 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-800">Teacher Professional Development</span>
                    </div>
                </div>
            </div>

            {{-- Right Column: Stats / Pillar Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if(!empty($config['stats']) && is_array($config['stats']))
                    @foreach($config['stats'] as $stat)
                    <div class="v2-card p-6 text-center">
                        <div class="text-3xl font-extrabold font-heading text-primary mb-1">{{ $stat['value'] ?? 0 }}</div>
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ $stat['label'] ?? '' }}</div>
                    </div>
                    @endforeach
                @else
                    <div class="v2-card p-6 space-y-3 relative">
                        <div class="w-11 h-11 rounded-xl v2-chip-a flex items-center justify-center font-bold text-base shadow-sm">01</div>
                        <h4 class="font-heading font-bold text-slate-900 text-sm">Empowerment</h4>
                        <p class="text-xs text-slate-500 leading-relaxed">Regular workshops, principal meets & teacher training sessions.</p>
                    </div>
                    <div class="v2-card p-6 space-y-3 relative">
                        <div class="w-11 h-11 rounded-xl v2-chip-b font-bold text-base flex items-center justify-center shadow-sm">02</div>
                        <h4 class="font-heading font-bold text-slate-900 text-sm">Kalotsav & Sports</h4>
                        <p class="text-xs text-slate-500 leading-relaxed">State & regional competitions nurturing talent.</p>
                    </div>
                    <div class="v2-card p-6 space-y-3 relative">
                        <div class="w-11 h-11 rounded-xl v2-chip-c font-bold text-base flex items-center justify-center shadow-sm">03</div>
                        <h4 class="font-heading font-bold text-slate-900 text-sm">Circulars & Sync</h4>
                        <p class="text-xs text-slate-500 leading-relaxed">Instant sharing of CBSE mandates, exam updates & guidelines.</p>
                    </div>
                    <div class="v2-card p-6 space-y-3 relative">
                        <div class="w-11 h-11 rounded-xl v2-chip-d font-bold text-base flex items-center justify-center shadow-sm">04</div>
                        <h4 class="font-heading font-bold text-slate-900 text-sm">Resource Hub</h4>
                        <p class="text-xs text-slate-500 leading-relaxed">Centralized repository for teaching materials & papers.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>