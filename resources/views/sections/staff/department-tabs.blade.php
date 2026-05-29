<section class="py-16 px-4" x-data="{ activeDept: '{{ $config['departments'][0]['key'] ?? 'all' }}' }">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['departments']) && is_array($config['departments']))
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            @foreach($config['departments'] as $dept)
            <button @click="activeDept = '{{ $dept['key'] }}'"
                    class="px-4 py-2 rounded-full text-sm font-medium transition"
                    :class="activeDept === '{{ $dept['key'] }}' ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    x-bind:style="activeDept === '{{ $dept['key'] }}' ? 'background-color: var(--color-primary);' : ''">
                {{ $dept['label'] }}
            </button>
            @endforeach
        </div>
        <div>
            @foreach($config['departments'] as $dept)
            <div x-show="activeDept === '{{ $dept['key'] }}'" x-transition:enter="transition ease-out duration-300" x-cloak>
                @if(!empty($dept['members']) && is_array($dept['members']))
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($dept['members'] as $member)
                    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                        @if(!empty($member['photo']))
                        <img loading="lazy" src="{{ $member['photo'] }}" alt="{{ $member['name'] }}" class="w-16 h-16 rounded-full mx-auto object-cover mb-3">
                        @endif
                        <h4 class="font-semibold text-gray-800 text-sm">{{ $member['name'] }}</h4>
                        <p class="text-xs" style="color: var(--color-primary)">{{ $member['designation'] ?? '' }}</p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>