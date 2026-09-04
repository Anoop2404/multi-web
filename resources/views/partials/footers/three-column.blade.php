<footer class="text-gray-300 mt-16" style="background-color: var(--color-primary)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid md:grid-cols-3 gap-8">
        <div>
            @if(!empty($logo = \App\Support\TenantBranding::logoUrl($tenant)))
            <span class="inline-block bg-white rounded-xl p-2 mb-3">
                <img loading="lazy" src="{{ $logo }}" class="h-10 block" alt="{{ $tenant->name }}">
            </span>
            @else
            <h3 class="text-white font-semibold mb-3 font-heading">{{ $tenant->name ?? 'School' }}</h3>
            @endif
            <p class="text-sm">{{ $content['tagline'] ?? '' }}</p>
            @if(isset($content['sahodaya_link']))
                <a href="{{ $content['sahodaya_link']['url'] }}" class="text-sm text-white font-semibold hover:underline mt-2 block">
                    {{ $content['sahodaya_link']['label'] ?? 'Sahodaya Cluster' }}
                </a>
            @endif
        </div>

        @if(!empty($content['quick_links']))
        <div>
            <h3 class="text-white font-semibold mb-3">Quick Links</h3>
            <ul class="space-y-1 text-sm">
                @foreach($content['quick_links'] as $link)
                    <li><a href="{{ $link['url'] }}" class="hover:text-white transition-colors">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(!empty($content['address']) || !empty($content['phone']) || !empty($content['email']))
        <div>
            <h3 class="text-white font-semibold mb-3">Contact</h3>
            <address class="text-sm not-italic space-y-1">
                @if(!empty($content['address']))<p>{{ $content['address'] }}</p>@endif
                @if(!empty($content['phone']))<p>{{ $content['phone'] }}</p>@endif
                @if(!empty($content['email']))<p>{{ $content['email'] }}</p>@endif
            </address>
        </div>
        @endif
    </div>
    <div class="border-t border-white/10 px-4 py-4 text-center text-xs text-gray-400">
        {{ $content['copyright'] ?? '© ' . date('Y') . ' ' . ($tenant->name ?? 'School') . '. All rights reserved.' }}
    </div>
</footer>
