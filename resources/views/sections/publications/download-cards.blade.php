<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['issues']) && is_array($config['issues']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['issues'] as $issue)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition">
                @if(!empty($issue['cover']))
                <img loading="lazy" src="{{ $issue['cover'] }}" alt="{{ $issue['title'] }}" class="w-full h-48 object-cover rounded-lg mb-4">
                @endif
                <h3 class="font-bold font-heading text-gray-800">{{ $issue['title'] }}</h3>
                @if(!empty($issue['date']))
                <p class="text-xs text-gray-500 mt-1">{{ $issue['date'] }}</p>
                @endif
                @if(!empty($issue['url']))
                <a href="{{ $issue['url'] }}" target="_blank" class="inline-flex items-center gap-2 mt-4 text-sm font-semibold transition hover:underline"
                   style="color: var(--color-primary);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download PDF
                </a>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>