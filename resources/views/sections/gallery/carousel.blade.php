@php
    $slides = collect($config['images'] ?? [])->map(function ($slide) {
        if (is_string($slide)) {
            return ['url' => $slide, 'caption' => ''];
        }
        return [
            'url'     => $slide['url'] ?? '',
            'caption' => $slide['caption'] ?? '',
        ];
    })->filter(fn ($slide) => ! empty($slide['url']))->values();

    $images   = $slides->pluck('url')->all();
    $captions = $slides->pluck('caption')->all();
@endphp
<section class="py-16 px-4" x-data="{ currentSlide: 0, total: {{ $slides->count() }} }">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if($slides->isNotEmpty())
        <div class="relative overflow-hidden rounded-2xl shadow-lg cursor-pointer"
             @if($slides->isNotEmpty())
             @click="$dispatch('lightbox-open', { images: {{ json_encode($images) }}, captions: {{ json_encode($captions) }}, index: currentSlide })"
             @endif
             x-init="if (total > 1) setInterval(() => { currentSlide = (currentSlide + 1) % total }, 4000)"
             role="button"
             tabindex="0"
             aria-label="Open gallery lightbox"
             @keydown.enter="$dispatch('lightbox-open', { images: {{ json_encode($images) }}, captions: {{ json_encode($captions) }}, index: currentSlide })">
            @foreach($slides as $index => $slide)
            <div x-show="currentSlide === {{ $index }}"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                <img src="{{ $slide['url'] }}"
                     alt="{{ $slide['caption'] ?: ($config['heading'] ?? 'Gallery image') }}"
                     loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                     class="w-full h-80 md:h-96 object-cover">
                @if(!empty($slide['caption']))
                <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 p-6 pointer-events-none">
                    <p class="text-white text-sm">{{ $slide['caption'] }}</p>
                </div>
                @endif
            </div>
            @endforeach
            @if($slides->count() > 1)
            <div class="absolute bottom-4 inset-x-0 flex justify-center gap-2">
                @foreach($slides as $index => $slide)
                <button type="button" @click.stop="currentSlide = {{ $index }}"
                        class="w-3 h-3 min-w-[12px] min-h-[12px] rounded-full transition"
                        style="background-color: {{ $index === 0 ? 'white' : 'rgba(255,255,255,0.4)' }}"
                        :style="currentSlide === {{ $index }} ? 'background-color: white' : 'background-color: rgba(255,255,255,0.4)'"
                        aria-label="Go to slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
            @endif
        </div>
        @else
        <div class="text-center text-gray-400 py-12">No images added yet.</div>
        @endif
    </div>
</section>
