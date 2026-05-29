<section class="py-16 px-4">
    <div class="max-w-6xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="max-w-2xl mx-auto mb-8 text-center space-y-3">
            @if(!empty($config['address']))<p class="text-gray-600">📍 {{ $config['address'] }}</p>@endif
            @if(!empty($config['phone']))<p class="text-gray-600">📞 <a href="tel:{{ $config['phone'] }}" class="hover:text-primary">{{ $config['phone'] }}</a></p>@endif
            @if(!empty($config['email']))<p class="text-gray-600">✉️ <a href="mailto:{{ $config['email'] }}" class="hover:text-primary">{{ $config['email'] }}</a></p>@endif
        </div>
        @if(!empty($config['map_embed']))
        <div class="rounded-xl overflow-hidden shadow-lg">{!! $config['map_embed'] !!}</div>
        @endif
    </div>
</section>