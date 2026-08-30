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

        <div>
            <div class="rounded-2xl overflow-hidden shadow-lg h-[400px]">
                @if($photo)
                <img loading="lazy" src="{{ $photo }}" alt="{{ $config['name'] ?? 'Principal' }}"
                     class="w-full h-full object-cover">
                @else
                <div class="w-full h-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary, var(--color-primary)) 100%)">
                    <svg class="w-24 h-24 text-white/60" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>
