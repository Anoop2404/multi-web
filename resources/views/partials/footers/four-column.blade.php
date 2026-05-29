<footer class="bg-gray-900 text-gray-300 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
        {{-- Branding --}}
        <div class="lg:col-span-1">
            @if(!empty($logo = $tenant->getSetting('logo')))
            <img loading="lazy" src="{{ $logo }}" class="h-12 mb-3 brightness-0 invert" alt="{{ $tenant->name }}">
            @else
            <h3 class="text-white font-bold text-lg font-heading mb-3">{{ $tenant->name ?? 'School' }}</h3>
            @endif
            <p class="text-sm text-gray-400 mb-4">{{ $content['tagline'] ?? '' }}</p>

            @if(!empty($content['social_links']))
            <div class="flex gap-3">
                @foreach($content['social_links'] as $platform => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener"
                   class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center hover:bg-primary transition"
                   style="--hover-bg: var(--color-primary)"
                   onmouseover="this.style.backgroundColor='var(--color-primary)'"
                   onmouseout="this.style.backgroundColor=''">
                    @include("partials.widgets.social-icons.{$platform}")
                </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Quick links --}}
        <div>
            <h3 class="text-white font-semibold mb-3 text-sm uppercase tracking-wide">Quick Links</h3>
            <ul class="space-y-2 text-sm">
                @foreach($content['quick_links'] ?? [] as $link)
                <li>
                    <a href="{{ $link['url'] }}" class="hover:text-white transition-colors">{{ $link['label'] }}</a>
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Important links --}}
        <div>
            <h3 class="text-white font-semibold mb-3 text-sm uppercase tracking-wide">Important Links</h3>
            <ul class="space-y-2 text-sm">
                @foreach($content['important_links'] ?? [] as $link)
                <li>
                    <a href="{{ $link['url'] }}" target="{{ $link['target'] ?? '_self' }}"
                       class="hover:text-white transition-colors">{{ $link['label'] }}</a>
                </li>
                @endforeach
            </ul>
        </div>

        {{-- Contact --}}
        <div>
            <h3 class="text-white font-semibold mb-3 text-sm uppercase tracking-wide">Contact</h3>
            <address class="text-sm not-italic space-y-2">
                @if(!empty($content['address']))
                <p class="flex items-start gap-2">
                    <span class="mt-0.5 shrink-0">📍</span>{{ $content['address'] }}
                </p>
                @endif
                @if(!empty($content['phone']))
                <p class="flex items-center gap-2">
                    <span>📞</span>
                    <a href="tel:{{ $content['phone'] }}" class="hover:text-white transition">{{ $content['phone'] }}</a>
                </p>
                @endif
                @if(!empty($content['email']))
                <p class="flex items-center gap-2">
                    <span>✉️</span>
                    <a href="mailto:{{ $content['email'] }}" class="hover:text-white transition">{{ $content['email'] }}</a>
                </p>
                @endif
            </address>
        </div>
    </div>

    <div class="border-t border-gray-800 px-4 py-4 text-center text-xs text-gray-500">
        {{ $content['copyright'] ?? '© ' . date('Y') . ' ' . ($tenant->name ?? 'School') . '. All rights reserved.' }}
        @if(!empty($content['sahodaya_link']))
        &nbsp;|&nbsp;
        <a href="{{ $content['sahodaya_link']['url'] }}" class="hover:text-gray-300">
            {{ $content['sahodaya_link']['label'] }}
        </a>
        @endif
    </div>
</footer>
