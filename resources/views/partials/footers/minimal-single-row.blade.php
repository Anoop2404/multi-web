<footer class="border-t border-gray-200 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
        <div class="flex items-center gap-2">
            @if(!empty($logo = \App\Support\TenantBranding::logoUrl($tenant)))
            <img loading="lazy" src="{{ $logo }}" class="h-6" alt="{{ $tenant->name }}">
            @endif
            <span>{{ $content['copyright'] ?? '© ' . date('Y') . ' ' . ($tenant->name ?? '') }}</span>
        </div>
        <div class="flex items-center gap-4">
            @foreach($content['quick_links'] ?? [] as $link)
            <a href="{{ $link['url'] }}" class="hover:text-gray-800 transition">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>
</footer>