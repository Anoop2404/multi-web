@php
    use App\Support\SahodayaPublicData;
    $bearers = SahodayaPublicData::officeBearers($tenant->id);
    if ($bearers->isEmpty() && !empty($config['bearers']) && is_array($config['bearers'])) {
        $bearers = collect($config['bearers'])->map(fn ($b) => (object) $b);
    }
@endphp
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if($bearers->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($bearers as $bearer)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center hover:shadow-md transition">
                @php
                    $photoUrl = $bearer->photo_url ?? $bearer['photo_url'] ?? null;
                    if (! $photoUrl) {
                        $rawPhoto = $bearer->photo ?? $bearer['photo'] ?? null;
                        if ($rawPhoto && (str_starts_with($rawPhoto, 'http') || str_starts_with($rawPhoto, '/'))) {
                            $photoUrl = $rawPhoto;
                        }
                    }
                @endphp
                <div class="w-24 h-24 rounded-full mx-auto mb-4 overflow-hidden flex items-center justify-center"
                     style="background-color: var(--color-primary-light)">
                    @if($photoUrl)
                    <img loading="lazy" src="{{ $photoUrl }}" alt="{{ $bearer->name ?? $bearer['name'] }}" class="w-full h-full object-cover">
                    @else
                    <span class="text-2xl font-bold font-heading" style="color: var(--color-primary)">{{ substr($bearer->name ?? $bearer['name'] ?? '?', 0, 1) }}</span>
                    @endif
                </div>
                <h3 class="font-bold font-heading text-gray-800">{{ $bearer->name ?? $bearer['name'] }}</h3>
                <p class="text-sm" style="color: var(--color-primary)">{{ $bearer->role ?? $bearer['role'] ?? '' }}</p>
            </div>
            @endforeach
        </div>
        <div class="text-center mt-10">
            <a href="/office-bearers" class="inline-flex items-center gap-1.5 font-bold text-sm hover:underline" style="color: var(--color-primary)">
                View all leadership
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
        @else
        <x-section-empty-state
            title="Leadership Team Coming Soon"
            subtitle="Office bearers will be listed here once added."
            icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
        />
        @endif
    </div>
</section>