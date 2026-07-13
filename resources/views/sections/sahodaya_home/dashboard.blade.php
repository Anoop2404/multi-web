@php
    use App\Support\SahodayaPublicData;
    $announcements = SahodayaPublicData::announcements($tenant->id, $config);
    $bearers = SahodayaPublicData::officeBearers($tenant->id);
    $schools = SahodayaPublicData::memberSchools($tenant->id);
    $years = SahodayaPublicData::academicYears($config);
    $useful = SahodayaPublicData::usefulLinks($config);
    $programmes = SahodayaPublicData::programmes($config);
    $upcoming = SahodayaPublicData::upcomingEvents($tenant->id);
    $motto = SahodayaPublicData::motto($config);
    $aboutText = SahodayaPublicData::aboutText($config, $tenant);
    $phone = SahodayaPublicData::contactPhone($tenant, $config);
    $email = $config['email'] ?? $tenant->sahodayaProfile?->contact_email ?? $tenant->getSetting('contact_email');
@endphp

<section class="relative overflow-hidden">
    {{-- Hero band --}}
    <div class="relative px-4 pt-12 pb-16 sm:pt-16 sm:pb-20 text-white"
         style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 55%, color-mix(in srgb, var(--color-secondary) 70%, #0f172a) 100%);">
        <div class="absolute inset-0 opacity-20 pointer-events-none"
             style="background-image: radial-gradient(circle at 20% 20%, white 0%, transparent 40%), radial-gradient(circle at 80% 0%, white 0%, transparent 35%);"></div>
        <div class="max-w-7xl mx-auto relative">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/70 mb-3">{{ $config['eyebrow'] ?? 'CBSE Sahodaya' }}</p>
                <h1 class="font-heading text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight">{{ $config['heading'] ?? $tenant->name }}</h1>
                @if(!empty($config['tagline']))
                <p class="mt-4 text-lg sm:text-xl text-white/85 max-w-2xl">{{ $config['tagline'] }}</p>
                @endif
            </div>
            <div class="mt-8 flex flex-wrap gap-3">
                @if($schools->count())
                <span class="inline-flex items-center gap-2 bg-white/15 backdrop-blur px-4 py-2 rounded-full text-sm font-medium">
                    <span class="text-2xl font-bold">{{ $schools->count() }}</span> Member Schools
                </span>
                @endif
                @if($bearers->count())
                <span class="inline-flex items-center gap-2 bg-white/15 backdrop-blur px-4 py-2 rounded-full text-sm font-medium">
                    <span class="text-2xl font-bold">{{ $bearers->count() }}</span> Office Bearers
                </span>
                @endif
                <a href="#academic" class="inline-flex items-center gap-2 bg-white text-gray-900 px-5 py-2.5 rounded-full text-sm font-bold hover:bg-white/90 transition shadow-lg">
                    Academic Resources →
                </a>
            </div>
        </div>
    </div>

    {{-- Latest news strip --}}
    @if($announcements->isNotEmpty())
    <div class="bg-gray-900 text-white py-3 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 flex items-center gap-4">
            <span class="shrink-0 text-xs font-bold uppercase tracking-wider bg-amber-500 text-gray-900 px-3 py-1 rounded-full">Latest</span>
            <div class="flex-1 overflow-hidden">
                <div class="flex gap-8 animate-marquee whitespace-nowrap text-sm">
                    @foreach($announcements->concat($announcements) as $item)
                    <a href="{{ $item->url }}" class="hover:text-amber-300 transition inline-flex items-center gap-2">
                        <span class="text-white/50">{{ $item->badge }}</span> {{ $item->title }}
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- About & motto — Travancore / Malabar style --}}
    <div id="about" class="scroll-mt-24 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16">
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-2">{{ $config['about_eyebrow'] ?? 'About Sahodaya' }}</p>
                    <h2 class="font-heading text-2xl sm:text-3xl font-bold text-gray-900 mb-4">{{ $config['about_heading'] ?? 'Caring and Sharing' }}</h2>
                    <p class="text-gray-600 leading-relaxed">{{ $aboutText }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="/school-register" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold text-white shadow-md hover:opacity-90 transition"
                           style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                            Apply for Membership →
                        </a>
                        <a href="/login" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                            School Login
                        </a>
                    </div>
                </div>
                <div class="relative rounded-3xl p-8 sm:p-10 text-center overflow-hidden"
                     style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 12%, white), color-mix(in srgb, var(--color-secondary) 8%, white));">
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-purple-600 mb-3">{{ $config['motto_label'] ?? 'Our Motto' }}</p>
                    <blockquote class="font-heading text-xl sm:text-2xl font-bold text-gray-900 italic leading-snug">
                        "{{ $motto }}"
                    </blockquote>
                    <div class="mt-6 flex justify-center gap-6 text-sm text-gray-500">
                        @if($schools->count())
                        <span><strong class="text-gray-900">{{ $schools->count() }}</strong> schools</span>
                        @endif
                        @if($bearers->count())
                        <span><strong class="text-gray-900">{{ $bearers->count() }}</strong> bearers</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Programmes & services — Central Kerala / Confederation style --}}
    <div id="programmes" class="scroll-mt-24 bg-gray-50 py-12 lg:py-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-10">
                <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">{{ $config['programmes_eyebrow'] ?? 'What We Do' }}</p>
                <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['programmes_heading'] ?? 'Programmes & Services' }}</h2>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($programmes as $prog)
                <a href="{{ $prog['url'] ?? '#' }}"
                   class="group bg-white rounded-2xl border border-gray-100 p-6 hover:shadow-lg hover:border-purple-100 transition-all duration-300"
                   @if(str_starts_with($prog['url'] ?? '', 'http')) target="_blank" rel="noopener" @endif>
                    <span class="text-3xl mb-4 block group-hover:scale-110 transition">{{ $prog['icon'] ?? '📌' }}</span>
                    <h3 class="font-heading font-bold text-gray-900 group-hover:text-purple-700 transition">{{ $prog['label'] }}</h3>
                    @if(!empty($prog['description']))
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed">{{ $prog['description'] }}</p>
                    @endif
                </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Main two-column: bearers + academic --}}
    <div class="max-w-7xl mx-auto px-4 py-14 lg:py-20">
        <div class="grid lg:grid-cols-12 gap-8 lg:gap-10">
            {{-- Office bearers --}}
            <div id="office-bearers" class="lg:col-span-7 scroll-mt-24">
                <div class="flex items-end justify-between mb-8">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">Leadership</p>
                        <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['bearers_heading'] ?? 'Office Bearers' }}</h2>
                    </div>
                </div>
                @if($bearers->isNotEmpty())
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($bearers as $bearer)
                    <article class="group relative bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-lg hover:border-purple-100 transition-all duration-300">
                        <div class="flex gap-4">
                            @if($bearer->photo_url)
                            <img src="{{ $bearer->photo_url }}"
                                 alt="{{ $bearer->name }}" class="w-16 h-16 rounded-xl object-cover shrink-0 ring-2 ring-purple-50">
                            @else
                            <div class="w-16 h-16 rounded-xl shrink-0 flex items-center justify-center text-white font-bold text-xl"
                                 style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                                {{ strtoupper(substr($bearer->name, 0, 1)) }}
                            </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-purple-600">{{ $bearer->role }}</p>
                                <h3 class="font-heading font-bold text-gray-900 mt-0.5 truncate">{{ $bearer->name }}</h3>
                                @if($bearer->school_name)
                                <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $bearer->school_name }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    @if($bearer->phone)
                                    <a href="tel:{{ $bearer->phone }}" class="text-gray-600 hover:text-purple-600">{{ $bearer->phone }}</a>
                                    @endif
                                    @if($bearer->email)
                                    <a href="mailto:{{ $bearer->email }}" class="text-purple-600 hover:underline truncate">{{ $bearer->email }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                    @endforeach
                </div>
                @else
                <p class="text-gray-400 text-sm bg-gray-50 rounded-2xl p-8 text-center">Office bearers will appear here once added in admin.</p>
                @endif
            </div>

            {{-- Academic quick links --}}
            <div id="academic" class="lg:col-span-5 scroll-mt-24" x-data="{ tab: '{{ $years[0]['year'] ?? '2025-26' }}' }">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-lg overflow-hidden sticky top-28">
                    <div class="px-5 py-4 border-b border-gray-100" style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 8%, white), color-mix(in srgb, var(--color-secondary) 6%, white));">
                        <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">Academic</p>
                        <h2 class="font-heading text-xl font-bold text-gray-900">{{ $config['academic_heading'] ?? 'Programs & Results' }}</h2>
                    </div>
                    <div class="flex gap-1 p-2 bg-gray-50 border-b border-gray-100 overflow-x-auto">
                        @foreach($years as $yearBlock)
                        <button type="button" @click="tab = '{{ $yearBlock['year'] }}'"
                                :class="tab === '{{ $yearBlock['year'] }}' ? 'bg-white shadow text-purple-700 font-bold' : 'text-gray-500 hover:text-gray-800'"
                                class="shrink-0 px-4 py-2 rounded-lg text-sm transition">
                            {{ $yearBlock['year'] }}
                        </button>
                        @endforeach
                    </div>
                    <div class="p-3 max-h-[28rem] overflow-y-auto">
                        @foreach($years as $yearBlock)
                        <ul x-show="tab === '{{ $yearBlock['year'] }}'" x-cloak class="space-y-1">
                            @foreach($yearBlock['links'] ?? [] as $link)
                            <li>
                                <a href="{{ $link['url'] ?? '#' }}"
                                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-800 transition group"
                                   @if(str_starts_with($link['url'] ?? '', 'http')) target="_blank" rel="noopener" @endif>
                                    <span class="text-lg w-8 text-center group-hover:scale-110 transition">{{ $link['icon'] ?? '🔗' }}</span>
                                    <span class="font-medium">{{ $link['label'] }}</span>
                                    <svg class="w-4 h-4 ml-auto opacity-0 group-hover:opacity-100 text-purple-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Member schools --}}
    @if($schools->isNotEmpty())
    <div id="member-schools" class="bg-gray-50 py-14 lg:py-20 scroll-mt-24">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-10">
                <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">Our Network</p>
                <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['schools_heading'] ?? 'Member Schools' }}</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                @foreach($schools as $school)
                @php $sLogo = $school->getSetting('logo'); $sUrl = $school->domain ? 'https://'.$school->domain : null; @endphp
                <a @if($sUrl) href="{{ $sUrl }}" target="_blank" rel="noopener" @endif
                   class="group bg-white rounded-2xl border border-gray-100 p-4 flex flex-col items-center text-center hover:shadow-md hover:border-purple-200 transition">
                    @if($sLogo)
                    <img src="{{ $sLogo }}" alt="{{ $school->name }}" class="h-12 w-auto object-contain mb-3 group-hover:scale-105 transition">
                    @else
                    <div class="h-12 w-12 rounded-xl mb-3 flex items-center justify-center text-white font-bold"
                         style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                        {{ strtoupper(substr($school->name, 0, 1)) }}
                    </div>
                    @endif
                    <p class="text-xs font-semibold text-gray-700 leading-snug line-clamp-3">{{ $school->name }}</p>
                </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Upcoming events — Travancore / Malappuram style --}}
    @if($upcoming->isNotEmpty())
    <div id="events" class="scroll-mt-24 max-w-7xl mx-auto px-4 py-14 lg:py-16">
        <div class="flex items-end justify-between mb-8 gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">{{ $config['events_eyebrow'] ?? 'Calendar' }}</p>
                <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['events_heading'] ?? 'Upcoming Events' }}</h2>
            </div>
            <a href="#academic" class="hidden sm:inline-flex text-sm font-semibold text-purple-600 hover:text-purple-800 transition">All programmes →</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($upcoming as $event)
            <article class="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-md hover:border-purple-100 transition group">
                <div class="px-5 py-4 border-b border-gray-50" style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 6%, white), white);">
                    @if($event->event_date)
                    <time class="text-xs font-bold text-purple-600">{{ $event->event_date->format('M j, Y') }}</time>
                    @else
                    <span class="text-xs font-bold text-purple-600">TBA</span>
                    @endif
                    @if($event->type)
                    <span class="ml-2 text-[10px] uppercase tracking-wider text-gray-400">{{ $event->type }}</span>
                    @endif
                </div>
                <div class="p-5">
                    <h3 class="font-heading font-bold text-gray-900 group-hover:text-purple-700 transition">{{ $event->name }}</h3>
                    @if($event->academic_year)
                    <p class="text-xs text-gray-500 mt-1">{{ $event->academic_year }}</p>
                    @endif
                    @if($event->venue)
                    <p class="text-xs text-gray-500 mt-2">📍 {{ $event->venue }}</p>
                    @endif
                </div>
            </article>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Useful links --}}
    <div id="useful-links" class="max-w-7xl mx-auto px-4 py-14 scroll-mt-24">
        <div class="text-center mb-8">
            <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">Resources</p>
            <h2 class="font-heading text-2xl font-bold text-gray-900">{{ $config['links_heading'] ?? 'Useful Links' }}</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($useful as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
               class="flex items-center gap-4 bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md hover:border-purple-100 transition group">
                <span class="text-2xl">{{ $link['icon'] ?? '🔗' }}</span>
                <span class="font-semibold text-gray-800 group-hover:text-purple-700 transition">{{ $link['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>

    {{-- School portal strip --}}
    <div class="max-w-7xl mx-auto px-4 pb-6">
        <div class="rounded-2xl border border-purple-100 bg-purple-50/50 px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="font-heading font-bold text-gray-900">{{ $config['portal_heading'] ?? 'Member School Portal' }}</p>
                <p class="text-sm text-gray-600 mt-0.5">{{ $config['portal_text'] ?? 'Schools can log in to submit annual registration, upload student & teacher data, and track membership status.' }}</p>
            </div>
            <div class="flex flex-wrap gap-3 shrink-0">
                <a href="/login" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-bold text-white shadow transition hover:opacity-90"
                   style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                    School Login →
                </a>
                <a href="/school-register" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                    New School Application
                </a>
            </div>
        </div>
    </div>

    {{-- Contact strip --}}
    <div id="contact" class="scroll-mt-24 mx-4 mb-14">
        <div class="max-w-7xl mx-auto rounded-3xl px-6 py-10 sm:px-10 text-white text-center"
             style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
            <h2 class="font-heading text-2xl sm:text-3xl font-bold mb-2">{{ $config['contact_heading'] ?? 'Contact Us' }}</h2>
            <p class="text-white/80 text-sm mb-6 max-w-xl mx-auto">{{ $config['contact_text'] ?? 'Reach the Sahodaya office for membership, events, and academic coordination.' }}</p>
            <div class="flex flex-wrap justify-center gap-4 text-sm font-semibold">
                @if($phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="inline-flex items-center gap-2 bg-white/15 backdrop-blur px-5 py-2.5 rounded-full hover:bg-white/25 transition">
                    📞 {{ $phone }}
                </a>
                @endif
                @if($email)
                <a href="mailto:{{ $email }}" class="inline-flex items-center gap-2 bg-white/15 backdrop-blur px-5 py-2.5 rounded-full hover:bg-white/25 transition">
                    ✉️ {{ $email }}
                </a>
                @endif
                <a href="/school-register" class="inline-flex items-center gap-2 bg-white text-gray-900 px-5 py-2.5 rounded-full hover:bg-white/90 transition shadow-lg font-bold">
                    School Membership →
                </a>
            </div>
        </div>
    </div>
</section>
