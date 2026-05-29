<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['testimonials']) && is_array($config['testimonials']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['testimonials'] as $testimonial)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition">
                <div class="flex items-center gap-3 mb-4">
                    @if(!empty($testimonial['photo']))
                    <img loading="lazy" src="{{ $testimonial['photo'] }}" alt="{{ $testimonial['name'] }}" class="w-12 h-12 rounded-full object-cover">
                    @endif
                    <div>
                        <p class="font-semibold text-gray-800 text-sm">{{ $testimonial['name'] }}</p>
                        <p class="text-xs text-gray-500">{{ $testimonial['designation'] ?? '' }}</p>
                    </div>
                </div>
                <blockquote class="text-gray-600 text-sm italic">"{{ $testimonial['quote'] ?? $testimonial['message'] ?? '' }}"</blockquote>
                @if(!empty($testimonial['rating']))
                <div class="mt-3 flex gap-0.5">
                    @for($i = 0; $i < $testimonial['rating']; $i++)
                    <svg class="w-4 h-4" style="color: var(--color-primary)" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    @endfor
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>