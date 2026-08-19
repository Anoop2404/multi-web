@php
    $type  = $config['staff_type'] ?? null;
    $limit = $config['limit'] ?? 12;
    $query = \App\Models\StaffMember::where('tenant_id', $tenant->id)->where('is_active', true);
    if ($type) {
        $query->where('type', $type);
    }
    $staff = $query->orderBy('display_order')->limit($limit)->get();
@endphp
@if($staff->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Our Faculty' }}</h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($staff as $member)
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100">
                <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden">
                    @if($member->photo)
                    <img loading="lazy" src="{{ $member->photo }}" alt="{{ $member->name }}" class="w-full h-full object-cover">
                    @else
                    <span class="text-gray-400 text-sm">No photo</span>
                    @endif
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-gray-900 text-lg mb-1">{{ $member->name }}</h3>
                    @if($member->designation)
                    <p class="text-sm font-medium mb-1" style="color: var(--color-primary)">{{ $member->designation }}</p>
                    @endif
                    @if($member->department)
                    <p class="text-xs text-gray-400 mb-2">{{ $member->department }}</p>
                    @endif
                    @if($member->qualification)
                    <p class="text-xs text-gray-500">Qualification: {{ $member->qualification }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
