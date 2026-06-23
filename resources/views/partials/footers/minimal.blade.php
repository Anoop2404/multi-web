<footer class="mt-16 border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                @if(!empty($logo = \App\Support\TenantBranding::logoUrl($tenant)))
                <img loading="lazy" src="{{ $logo }}" class="h-8" alt="{{ $tenant->name }}">
                @else
                <span class="font-bold font-heading" style="color: var(--color-primary)">{{ $tenant->name ?? 'School' }}</span>
                @endif
            </div>

            <div class="flex items-center gap-6 text-sm text-gray-500">
                @foreach($content['quick_links'] ?? [] as $link)
                <a href="{{ $link['url'] }}" class="hover:text-gray-800 transition">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>

        <div class="text-center text-xs text-gray-400 mt-6">
            {{ $content['copyright'] ?? '© ' . date('Y') . ' ' . ($tenant->name ?? 'School') . '. All rights reserved.' }}
        </div>
    </div>
</footer>
