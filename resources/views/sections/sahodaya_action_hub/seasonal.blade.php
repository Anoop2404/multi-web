@php
    $actions = [
        [
            'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5H4.5V21"/></svg>',
            'bg' => 'v2-chip-a',
            'label' => 'Member School Directory',
            'detail' => 'Browse and connect with 80+ affiliated CBSE schools across the region.',
            'url' => '#member_schools',
            'status' => '80+ Schools'
        ],
        [
            'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
            'bg' => 'v2-chip-b',
            'label' => 'CBSE Circulars & Notices',
            'detail' => 'Instant access to official mandates, exam guidelines, and downloadable circulars.',
            'url' => '#news_circulars',
            'status' => 'Live Feed'
        ],
        [
            'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 003-3V8.25a3 3 0 00-3-3h-9a3 3 0 00-3 3v7.5a3 3 0 003 3m9 0v-1.5A2.25 2.25 0 0014.25 15h-4.5A2.25 2.25 0 007.5 17.25v1.5m9-6h.008v.008H16.5v-.008zM7.5 10.5h.008v.008H7.5v-.008z"/></svg>',
            'bg' => 'v2-chip-c',
            'label' => 'Kalotsav & Sports Portal',
            'detail' => 'Event schedules, online registrations, item rules, and live competition results.',
            'url' => '#events_programs',
            'status' => 'Events Hub'
        ],
        [
            'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-5.25 6.557c0 1.5.75 2.25 2.25 2.25h6c1.5 0 2.25-.75 2.25-2.25"/></svg>',
            'bg' => 'v2-chip-d',
            'label' => 'School & Teacher Login',
            'detail' => 'Administrative portal access for school heads, principals, and teachers.',
            'url' => '/portal',
            'status' => 'Admin Portal'
        ],
    ];
@endphp
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-slate-50 border-t border-slate-200" aria-labelledby="action-hub-heading">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div class="space-y-1">
                <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1 rounded-full">
                    Quick Portals
                </span>
                <h2 id="action-hub-heading" class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                    {{ $config['heading'] ?? 'Sahodaya Quick Action Hub' }}
                </h2>
            </div>
            <p class="text-xs sm:text-sm font-semibold text-slate-500">
                Direct access to member services & resources
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}" class="v2-card p-6 flex flex-col justify-between group space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="w-12 h-12 rounded-xl {{ $action['bg'] }} flex items-center justify-center shrink-0 shadow-sm">
                                {!! $action['icon'] !!}
                            </div>
                            <span class="inline-flex text-[10px] font-bold uppercase tracking-wider rounded-full bg-slate-100 text-slate-700 px-2.5 py-1 border border-slate-200">
                                {{ $action['status'] }}
                            </span>
                        </div>
                        <h3 class="text-lg font-bold font-heading text-slate-900 group-hover:text-primary transition-colors leading-snug">
                            {{ $action['label'] }}
                        </h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-normal">
                            {{ $action['detail'] }}
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-primary">
                        <span>Access Service</span>
                        <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
