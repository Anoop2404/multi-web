<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto text-center">
        @if(!empty($config['motto']))
        <div class="mb-10 p-8 rounded-2xl" style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
            <p class="text-white/80 text-sm uppercase tracking-widest font-semibold mb-2">{{ $config['motto_label'] ?? 'Our Motto' }}</p>
            <h2 class="text-3xl md:text-5xl font-bold font-heading text-white italic">"{{ $config['motto'] }}"</h2>
        </div>
        @endif

        <div class="grid md:grid-cols-3 gap-8 mb-10">
            @if(!empty($config['history']))
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-bold font-heading mb-3" style="color: var(--color-primary)">History</h3>
                <p class="text-gray-600 text-sm">{!! nl2br(e($config['history'])) !!}</p>
            </div>
            @endif
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-bold font-heading mb-3" style="color: var(--color-primary)">Vision</h3>
                <p class="text-gray-600 text-sm">{{ $config['vision'] ?? 'To provide holistic education that nurtures excellence.' }}</p>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-bold font-heading mb-3" style="color: var(--color-primary)">Mission</h3>
                <p class="text-gray-600 text-sm">{{ $config['mission'] ?? 'Empowering students with knowledge, skills, and values.' }}</p>
            </div>
        </div>

        @if(!empty($config['anthem']))
        <div class="max-w-md mx-auto p-4 rounded-lg border" style="border-color: var(--color-primary);">
            <p class="text-sm font-semibold mb-2" style="color: var(--color-primary)">🎵 School Anthem</p>
            <audio controls class="w-full">
                <source src="{{ $config['anthem'] }}" type="audio/mpeg">
            </audio>
        </div>
        @endif
    </div>
</section>