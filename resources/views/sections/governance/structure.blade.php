<section class="py-16 px-4">
    <div class="max-w-6xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid lg:grid-cols-3 gap-8">
            @if(!empty($config['org_chart']))
            <div class="lg:col-span-2">
                <h3 class="font-bold font-heading text-lg mb-4" style="color: var(--color-secondary)">Organizational Structure</h3>
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    {!! $config['org_chart'] !!}
                </div>
            </div>
            @endif
            <div class="space-y-6">
                @if(!empty($config['rules']) && is_array($config['rules']))
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <h3 class="font-bold font-heading text-lg mb-3" style="color: var(--color-secondary)">Rules & Bye-laws</h3>
                    <ul class="space-y-2">
                        @foreach($config['rules'] as $rule)
                        <li class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: var(--color-primary);"></span>
                            @if(!empty($rule['url']))
                            <a href="{{ $rule['url'] }}" target="_blank" class="text-sm text-gray-600 hover:text-primary transition">{{ $rule['label'] }}</a>
                            @else
                            <span class="text-sm text-gray-600">{{ $rule['label'] }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @if(!empty($config['downloads']) && is_array($config['downloads']))
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <h3 class="font-bold font-heading text-lg mb-3" style="color: var(--color-secondary)">Downloads</h3>
                    <ul class="space-y-2">
                        @foreach($config['downloads'] as $doc)
                        <li>
                            <a href="{{ $doc['url'] }}" target="_blank" class="flex items-center gap-2 text-sm text-gray-600 hover:text-primary transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ $doc['label'] }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>