@php
    use App\Support\SahodayaPublicData;
    $motto = SahodayaPublicData::motto($config);
    $aboutText = SahodayaPublicData::aboutText($config, $tenant);
    $schoolCount = SahodayaPublicData::memberSchools($tenant->id)->count();
    $bearerCount = SahodayaPublicData::officeBearers($tenant->id)->count();
@endphp
<section id="about" class="py-16 px-4 scroll-mt-24">
    <div class="max-w-7xl mx-auto">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-2">{{ $config['eyebrow'] ?? 'About Sahodaya' }}</p>
                <h2 class="font-heading text-3xl font-bold text-gray-900 mb-4">{{ $config['heading'] ?? 'Caring and Sharing' }}</h2>
                <p class="text-gray-600 leading-relaxed">{{ $aboutText }}</p>
            </div>
            <div class="rounded-3xl p-8 sm:p-10 text-center"
                 style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 12%, white), color-mix(in srgb, var(--color-secondary) 8%, white));">
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-purple-600 mb-3">{{ $config['motto_label'] ?? 'Our Motto' }}</p>
                <blockquote class="font-heading text-xl sm:text-2xl font-bold text-gray-900 italic">"{{ $motto }}"</blockquote>
                <div class="mt-6 flex justify-center gap-6 text-sm text-gray-500">
                    @if($schoolCount)<span><strong class="text-gray-900">{{ $schoolCount }}</strong> schools</span>@endif
                    @if($bearerCount)<span><strong class="text-gray-900">{{ $bearerCount }}</strong> bearers</span>@endif
                </div>
            </div>
        </div>
    </div>
</section>
