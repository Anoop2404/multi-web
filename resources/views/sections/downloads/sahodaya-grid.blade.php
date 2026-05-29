<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['categories']) && is_array($config['categories']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['categories'] as $cat)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-bold font-heading text-gray-800 mb-3">{{ $cat['label'] }}</h3>
                @if(!empty($cat['files']) && is_array($cat['files']))
                <ul class="space-y-2">
                    @foreach($cat['files'] as $file)
                    <li>
                        <a href="{{ $file['url'] }}" target="_blank" class="flex items-center gap-2 text-sm text-gray-600 hover:text-primary transition">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            {{ $file['label'] }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>