<footer class="bg-gray-900 text-gray-300 mt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid md:grid-cols-3 gap-8">
        <div>
            <h3 class="text-white font-semibold mb-3 font-heading">{{ $tenant->name ?? 'School' }}</h3>
            <p class="text-sm">{{ $content['tagline'] ?? '' }}</p>
            @if(isset($content['sahodaya_link']))
                <a href="{{ $content['sahodaya_link']['url'] }}" class="text-sm text-primary hover:underline mt-2 block">
                    {{ $content['sahodaya_link']['label'] ?? 'Sahodaya Cluster' }}
                </a>
            @endif
        </div>

        <div>
            <h3 class="text-white font-semibold mb-3">Quick Links</h3>
            <ul class="space-y-1 text-sm">
                @foreach($content['quick_links'] ?? [] as $link)
                    <li><a href="{{ $link['url'] }}" class="hover:text-white transition-colors">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h3 class="text-white font-semibold mb-3">Contact</h3>
            <address class="text-sm not-italic space-y-1">
                @if(isset($content['address']))<p>{{ $content['address'] }}</p>@endif
                @if(isset($content['phone']))<p>{{ $content['phone'] }}</p>@endif
                @if(isset($content['email']))<p>{{ $content['email'] }}</p>@endif
            </address>
        </div>
    </div>
    <div class="border-t border-gray-800 px-4 py-4 text-center text-xs text-gray-500">
        {{ $content['copyright'] ?? '© ' . date('Y') . ' ' . ($tenant->name ?? 'School') . '. All rights reserved.' }}
    </div>
</footer>
