<section class="py-16 px-4" x-data="{ currentSlide: 0, slides: {{ json_encode($config['images'] ?? []) }} }">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="relative overflow-hidden rounded-2xl shadow-lg" x-init="setInterval(() => { currentSlide = (currentSlide + 1) % slides.length }, 4000)">
            <template x-for="(slide, index) in slides" :key="index">
                <div x-show="currentSlide === index" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <img :src="slide.url ?? slide" :alt="slide.caption ?? ''" class="w-full h-80 md:h-96 object-cover">
                    <div x-show="slide.caption" class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 p-6">
                        <p class="text-white text-sm" x-text="slide.caption"></p>
                    </div>
                </div>
            </template>
            {{-- Dots --}}
            <div class="absolute bottom-4 inset-x-0 flex justify-center gap-2">
                <template x-for="(slide, index) in slides" :key="'dot-'+index">
                    <button @click="currentSlide = index" class="w-2.5 h-2.5 rounded-full transition"
                            :class="currentSlide === index ? 'bg-white' : 'bg-white/40'"></button>
                </template>
            </div>
        </div>
        @if(empty($config['images']))
        <div class="text-center text-gray-400 py-12">No images added yet.</div>
        @endif
    </div>
</section>