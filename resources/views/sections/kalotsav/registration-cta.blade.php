<section class="py-16 px-4" style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
    <div class="max-w-4xl mx-auto text-center text-white">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading mb-4">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['description']))
        <p class="text-lg opacity-90 mb-8">{{ $config['description'] }}</p>
        @endif
        <div class="flex flex-wrap justify-center gap-4">
            @if(!empty($config['login_url']))
            <a href="{{ $config['login_url'] }}" class="bg-white text-gray-800 font-semibold px-8 py-3 rounded-full hover:bg-opacity-90 transition">School Login</a>
            @endif
            @if(!empty($config['register_url']))
            <a href="{{ $config['register_url'] }}" class="border border-white/60 text-white font-semibold px-8 py-3 rounded-full hover:bg-white/10 transition">Register Now</a>
            @endif
        </div>
    </div>
</section>