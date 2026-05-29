<section class="py-20 px-4" style="background-color: var(--color-primary);">
    <div class="max-w-4xl mx-auto text-center text-white">
        <p class="text-sm uppercase tracking-widest font-semibold opacity-80">{{ $config['label'] ?? 'Principal\'s Message' }}</p>
        @if(!empty($config['photo']))
        <img loading="lazy" src="{{ $config['photo'] }}" alt="Principal"
             class="w-24 h-24 rounded-full mx-auto my-4 object-cover border-4 border-white/30">
        @endif
        <blockquote class="text-xl md:text-2xl leading-relaxed italic font-light mt-4">
            "{{ $config['message_quote'] ?? $config['message'] ?? '' }}"
        </blockquote>
        @if(!empty($config['name']))
        <div class="mt-6">
            <p class="font-bold font-heading text-lg">{{ $config['name'] }}</p>
            <p class="text-sm opacity-80">{{ $config['designation'] ?? 'Principal' }}</p>
        </div>
        @endif
    </div>
</section>