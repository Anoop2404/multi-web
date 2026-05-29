@php
    $type  = $config['staff_type'] ?? null;
    $query = \App\Models\StaffMember::where('tenant_id', $tenant->id)->where('is_active', true);
    if ($type) $query->where('type', $type);
    $staff = $query->orderBy('display_order')->get();

    // Group by department if enabled
    $groupByDept = $config['group_by_department'] ?? false;
    if ($groupByDept) {
        $grouped = $staff->groupBy('department');
    }
@endphp
@if($staff->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-5xl mx-auto">
        <h2 class="text-3xl font-bold font-heading text-gray-900 mb-8">{{ $config['heading'] ?? 'Teaching Staff' }}</h2>

        @if($groupByDept)
            @foreach($grouped as $dept => $members)
            @if($dept)
            <h3 class="text-lg font-bold mb-3 mt-8 pb-2 border-b" style="color: var(--color-primary)">{{ $dept }}</h3>
            @endif
            <div class="overflow-x-auto rounded-xl border border-gray-100 mb-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Name</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Designation</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700">Qualification</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($members as $m)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $m->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $m->designation }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $m->qualification }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
        @else
        <div class="overflow-x-auto rounded-xl border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Name</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Designation</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Department</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700">Qualification</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($staff as $m)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $m->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $m->designation }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $m->department }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $m->qualification }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>
@endif
