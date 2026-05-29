<section class="py-16 px-4 bg-gray-50" x-data="{ activeTab: '{{ $config['tabs'][0]['key'] ?? 'science' }}' }">
    <div class="max-w-6xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['tabs']) && is_array($config['tabs']))
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            @foreach($config['tabs'] as $tab)
            <button @click="activeTab = '{{ $tab['key'] }}'"
                    class="px-6 py-2 rounded-full text-sm font-medium transition"
                    :class="activeTab === '{{ $tab['key'] }}' ? 'text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                    x-bind:style="activeTab === '{{ $tab['key'] }}' ? 'background-color: var(--color-primary); color: white;' : ''">
                {{ $tab['label'] }}
            </button>
            @endforeach
        </div>
        <div>
            @foreach($config['tabs'] as $tab)
            <div x-show="activeTab === '{{ $tab['key'] }}'" x-transition:enter="transition ease-out duration-300" x-cloak>
                <div class="bg-white rounded-xl p-8 shadow-sm">
                    <h3 class="text-2xl font-bold font-heading mb-4" style="color: var(--color-primary)">{{ $tab['label'] }}</h3>
                    <div class="prose max-w-none text-gray-600">{!! nl2br(e($tab['content'] ?? '')) !!}</div>
                    @if(!empty($tab['subjects']) && is_array($tab['subjects']))
                    <div class="mt-6">
                        <p class="font-semibold text-gray-800 mb-2">Subjects Offered:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($tab['subjects'] as $subject)
                            <span class="px-3 py-1 rounded-full text-xs font-medium"
                                  style="background-color: color-mix(in srgb, var(--color-primary) 10%, transparent); color: var(--color-primary);">
                                {{ $subject }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>