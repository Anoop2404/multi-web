<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['subheading']))
        <p class="text-center text-gray-500 mb-8">{{ $config['subheading'] }}</p>
        @endif
        @if(!empty($config['fee_table']) && is_array($config['fee_table']))
        <div class="overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background-color: var(--color-primary); color: white;">
                        <th class="px-6 py-3 text-left font-semibold">Class</th>
                        <th class="px-6 py-3 text-left font-semibold">Tuition Fee</th>
                        <th class="px-6 py-3 text-left font-semibold">Admission Fee</th>
                        <th class="px-6 py-3 text-left font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($config['fee_table'] as $row)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-medium text-gray-800">{{ $row['class'] }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $row['tuition'] ?? $row['tuition_fee'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $row['admission'] ?? $row['admission_fee'] ?? '-' }}</td>
                        <td class="px-6 py-4 font-semibold" style="color: var(--color-primary)">{{ $row['total'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-gray-400 py-8">Fee structure not available yet.</div>
        @endif
    </div>
</section>