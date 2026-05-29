<footer class="bg-gray-900 text-gray-300 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-2 gap-8">
        <div>
            <a href="/" class="flex items-center gap-3 mb-4">
                @if(!empty($logo = $tenant->getSetting('logo')))
                <img loading="lazy" src="{{ $logo }}" class="h-12 brightness-0 invert" alt="{{ $tenant->name }}">
                @else
                <span class="text-xl font-bold text-white font-heading">{{ $tenant->name ?? 'School' }}</span>
                @endif
            </a>
            <p class="text-sm text-gray-400 mb-4">{{ $content['tagline'] ?? '' }}</p>
            @if(!empty($content['social_links']))
            <div class="flex gap-3">
                @foreach($content['social_links'] as $platform => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener"
                   class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center hover:bg-primary transition"
                   onmouseover="this.style.backgroundColor='var(--color-primary)'"
                   onmouseout="this.style.backgroundColor=''">
                    @include("partials.widgets.social-icons.{$platform}")
                </a>
                @endforeach
            </div>
            @endif
        </div>
        <div class="grid sm:grid-cols-2 gap-8">
            <div>
                <h3 class="text-white font-semibold mb-3 text-sm uppercase tracking-wide">Quick Links</h3>
                <ul class="space-y-2 text-sm">
                    @foreach($content['quick_links'] ?? [] as $link)
                    <li><a href="{{ $link['url'] }}" class="hover:text-white transition">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h3 class="text-white font-semibold mb-3 text-sm uppercase tracking-wide">Contact</h3>
                <address class="text-sm not-italic space-y-2">
                    @if(!empty($content['address']))<p>{{ $content['address'] }}</p>@endif
                    @if(!empty($content['phone']))<p>📞 <a href="tel:{{ $content['phone'] }}" class="hover:text-white">{{ $content['phone'] }}</a></p>@endif
                    @if(!empty($content['email']))<p>✉️ <a href="mailto:{{ $content['email'] }}" class="hover:text-white">{{ $content['email'] }}</a></p>@endif
                </address>
            </div>
        </div>
    </div>
    <div class="border-t border-gray-800 px-4 py-4 text-center text-xs text-gray-500">
        {{ $content['copyright'] ?? '© ' . date('Y') . ' ' . ($tenant->name ?? 'School') . '. All rights reserved.' }}
        @if(!empty($content['sahodaya_link']))
        &nbsp;|&nbsp;<a href="{{ $content['sahodaya_link']['url'] }}" class="hover:text-gray-300">{{ $content['sahodaya_link']['label'] }}</a>
        @endif
    </div>
</footer>