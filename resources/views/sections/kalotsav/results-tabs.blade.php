<section class="py-16 px-4" x-data="{ activeCat: '{{ $config['categories'][0]['key'] ?? 'all' }}' }">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['categories']) && is_array($config['categories']))
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            @foreach($config['categories'] as $cat)
            <button @click="activeCat = '{{ $cat['key'] }}'"
                    class="px-4 py-2 rounded-full text-sm transition"
                    :class="activeCat === '{{ $cat['key'] }}' ? 'text-white' : 'bg-gray-100 text-gray-600'"
                    x-bind:style="activeCat === '{{ $cat['key'] }}' ? 'background-color: var(--color-primary);' : ''">{{ $cat['label'] }}</button>
            @endforeach
        </div>
        @endif
        @if(!empty($config['results']) && is_array($config['results']))
        <div class="overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background-color: var(--color-primary); color: white;">
                        <th class="px-6 py-3 text-left">School</th>
                        <th class="px-6 py-3 text-left">Category</th>
                        <th class="px-6 py-3 text-left">Position</th>
                        <th class="px-6 py-3 text-left">Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($config['results'] as $result)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">{{ $result['school'] ?? '' }}</td>
                        <td class="px-6 py-4">{{ $result['category'] ?? '' }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded text-xs font-semibold text-white"
                                  style="background-color: {{ $result['position'] == 1 ? '#FFD700' : ($result['position'] == 2 ? '#C0C0C0' : ($result['position'] == 3 ? '#CD7F32' : 'var(--color-primary)')) }};">
                                {{ $result['position'] ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">{{ $result['score'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>