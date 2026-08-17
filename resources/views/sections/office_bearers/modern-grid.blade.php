@php
    use App\Support\SahodayaPublicData;
    $bearers = SahodayaPublicData::officeBearers($tenant->id);
@endphp
<section id="office-bearers" class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-slate-50 border-t border-slate-200 relative overflow-hidden">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[36rem] h-64 opacity-[0.05] pointer-events-none" style="background: radial-gradient(ellipse, var(--color-primary), transparent 70%);"></div>
    <div class="max-w-7xl mx-auto relative">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
                Network Leadership
            </span>
            <h2 class="font-heading text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'Office Bearers' }}
            </h2>
            <p class="text-sm sm:text-base text-slate-600 font-normal">
                Dedicated educational leaders guiding the vision and programmes of {{ $tenant->name }}.
            </p>
        </div>

        @if($bearers->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($bearers as $bearer)
            <article class="v2-card p-6 text-center flex flex-col justify-between items-center group relative overflow-hidden">
                <div class="absolute top-0 inset-x-0 h-1 scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-center" style="background: linear-gradient(90deg, var(--color-primary), var(--color-accent));"></div>
                <div class="w-full space-y-3">
                    @if($bearer->photo_url)
                    <img src="{{ $bearer->photo_url }}"
                         alt="{{ $bearer->name }}" class="v2-media w-24 h-24 rounded-2xl mx-auto object-cover ring-4 ring-slate-100 group-hover:ring-primary/15 shadow-lg transition duration-200">
                    @else
                    <div class="w-24 h-24 rounded-2xl mx-auto flex items-center justify-center text-white text-3xl font-extrabold font-heading shadow-lg ring-4 ring-slate-100 group-hover:ring-primary/15 transition duration-200"
                         style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                        {{ strtoupper(substr($bearer->name, 0, 1)) }}
                    </div>
                    @endif

                    <div class="pt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider v2-badge-accent">
                            {{ $bearer->role ?? 'Office Bearer' }}
                        </span>
                        <h3 class="font-heading font-bold text-slate-900 text-lg mt-2 group-hover:text-primary transition-colors">
                            {{ $bearer->name }}
                        </h3>
                        @if($bearer->school_name)
                        <p class="text-xs text-slate-500 font-medium mt-1 leading-snug">
                            {{ $bearer->school_name }}
                        </p>
                        @endif
                    </div>
                </div>

                @if($bearer->phone || $bearer->email)
                <div class="w-full pt-4 mt-4 border-t border-slate-100 flex items-center justify-center gap-3 text-xs text-slate-600">
                    @if($bearer->phone)
                    <a href="tel:{{ $bearer->phone }}" class="inline-flex items-center gap-1.5 hover:text-primary font-semibold transition">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.826-1.47-5.114-3.758-6.585-6.586l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                        <span>Call</span>
                    </a>
                    @endif
                    @if($bearer->email)
                    <a href="mailto:{{ $bearer->email }}" class="inline-flex items-center gap-1.5 hover:text-primary font-semibold transition">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                        <span>Email</span>
                    </a>
                    @endif
                </div>
                @endif
            </article>
            @endforeach
        </div>
        @else
        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center bg-white max-w-2xl mx-auto">
            <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            <h3 class="text-base font-bold text-slate-800">Leadership Team Coming Soon</h3>
            <p class="text-sm text-slate-500 mt-1">Office bearers will be listed here once added.</p>
        </div>
        @endif
    </div>
</section>
