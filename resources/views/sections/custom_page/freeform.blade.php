<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto prose max-w-none text-gray-700">
        @if(!empty($config['heading']))
        <h2 style="color: var(--color-primary)" class="font-heading">{{ $config['heading'] }}</h2>
        @endif
        {!! \App\Support\HtmlSanitizer::rich($config['content'] ?? '') !!}
    </div>
</section>