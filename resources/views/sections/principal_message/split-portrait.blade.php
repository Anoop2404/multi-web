@php
    use App\Support\TenantStorage;
    $photo = TenantStorage::siteMediaUrl($tenant, $config['photo'] ?? null);
@endphp
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-12 items-center">
        <div class="md:text-right">
            @if(!empty($config['lead_in']))
            <p class="font-semibold text-gray-800 text-lg mb-3">{{ $config['lead_in'] }}</p>
            @endif
            @if(!empty($config['message']))
            <div class="text-gray-600 leading-relaxed space-y-4">
                {!! nl2br(e($config['message'])) !!}
            </div>
            @endif
            <div class="mt-6">
                @if(!empty($config['name']))
                <strong class="d-block text-lg text-gray-900">{{ $config['name'] }}</strong>
                @endif
                @if(!empty($config['designation']))
                <span class="font-semibold" style="color: var(--color-primary)">{{ $config['designation'] }}</span>
                @endif
            </div>
        </div>

        @if($photo)
        <div>
            <div class="rounded-2xl overflow-hidden shadow-lg h-[400px]">
                <img loading="lazy" src="{{ $photo }}" alt="{{ $config['name'] ?? 'Principal' }}"
                     class="w-full h-full object-cover">
            </div>
        </div>
        @endif
    </div>
</section>
