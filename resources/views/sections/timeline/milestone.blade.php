<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['milestones']) && is_array($config['milestones']))
        <div class="space-y-8 relative before:absolute before:left-1/2 before:-translate-x-px before:top-0 before:bottom-0 before:w-0.5 before:bg-gray-300">
            @foreach($config['milestones'] as $i => $milestone)
            <div class="relative {{ $i % 2 === 0 ? 'md:text-right md:pr-[50%] md:pr-12' : 'md:pl-[50%] md:pl-12' }} pl-10 md:pl-0">
                <div class="absolute left-4 md:left-1/2 md:-translate-x-1/2 w-4 h-4 rounded-full border-2 border-white shadow"
                     style="background-color: var(--color-primary);"></div>
                <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                    <p class="text-xs font-bold" style="color: var(--color-primary);">{{ $milestone['year'] ?? '' }}</p>
                    <h3 class="font-bold font-heading text-gray-800 mt-1">{{ $milestone['title'] }}</h3>
                    <p class="text-sm text-gray-600 mt-1">{{ $milestone['description'] ?? '' }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>