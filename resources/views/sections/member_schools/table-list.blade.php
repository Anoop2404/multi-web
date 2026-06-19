@php
    $schools = \App\Models\Tenant::where('parent_id', $tenant->id)
        ->where('is_active', true)->orderBy('name')->get();
@endphp
@if($schools->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        <h2 class="text-3xl font-bold font-heading text-gray-900 mb-8">{{ $config['heading'] ?? 'Member Schools' }}</h2>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">#</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">School Name</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Location</th>
                        <th class="text-left px-5 py-3 font-semibold text-gray-600">Website</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($schools as $i => $school)
                    @php
                        $location = $school->getSetting('address_city') ?? '';
                        $url = $school->domain ? 'https://' . $school->domain : null;
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $school->name }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $location }}</td>
                        <td class="px-5 py-3">
                            @if($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               class="font-semibold text-xs hover:underline" style="color: var(--color-primary)">
                                Visit &rarr;
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endif
