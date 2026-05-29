@php
    $type  = $config['staff_type'] ?? null; // teaching|non-teaching|admin or null for all
    $limit = $config['limit'] ?? 12;
    $query = \App\Models\StaffMember::where('tenant_id', $tenant->id)->where('is_active', true);
    if ($type) {
        $query->where('type', $type);
    }
    $staff = $query->orderBy('display_order')->limit($limit)->get();
@endphp
@if($staff->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Our Staff' }}</h2>
        </div>

        <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($staff as $member)
            <div class="text-center group">
                <div class="relative mx-auto w-32 h-32 rounded-full overflow-hidden mb-4 shadow-md ring-4 ring-transparent
                            group-hover:ring-primary/30 transition"
                     style="--tw-ring-color: color-mix(in srgb, var(--color-primary) 30%, transparent)">
                    @if($member->photo)
                    <img loading="lazy" src="{{ $member->photo }}" alt="{{ $member->name }}"
                         class="w-full h-full object-cover">
                    @else
                    <div class="w-full h-full flex items-center justify-center text-white text-3xl font-bold"
                         style="background-color: var(--color-primary)">
                        {{ strtoupper(substr($member->name, 0, 1)) }}
                    </div>
                    @endif
                </div>
                <h3 class="font-bold text-gray-900">{{ $member->name }}</h3>
                <p class="text-sm font-medium" style="color: var(--color-primary)">{{ $member->designation }}</p>
                @if($member->department)
                <p class="text-xs text-gray-400 mt-0.5">{{ $member->department }}</p>
                @endif
                @if($member->qualification)
                <p class="text-xs text-gray-400">{{ $member->qualification }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
