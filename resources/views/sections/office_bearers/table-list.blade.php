<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['bearers']) && is_array($config['bearers']))
        <div class="overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background-color: var(--color-primary); color: white;">
                        <th class="px-6 py-3 text-left font-semibold">Name</th>
                        <th class="px-6 py-3 text-left font-semibold">Role</th>
                        <th class="px-6 py-3 text-left font-semibold">Term</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($config['bearers'] as $bearer)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-medium text-gray-800">{{ $bearer['name'] }}</td>
                        <td class="px-6 py-4" style="color: var(--color-primary)">{{ $bearer['role'] ?? '' }}</td>
                        <td class="px-6 py-4 text-gray-500 text-xs">{{ $bearer['term_from'] ?? '' }} - {{ $bearer['term_to'] ?? 'Present' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>