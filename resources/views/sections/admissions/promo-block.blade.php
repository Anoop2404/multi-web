@php
    use App\Support\TenantStorage;
    $image = TenantStorage::siteMediaUrl($tenant, $config['image'] ?? null);
@endphp
<section class="py-16 px-4" style="background-color: color-mix(in srgb, var(--color-primary) 4%, white)">
    <div class="max-w-7xl mx-auto rounded-2xl overflow-hidden shadow-sm grid md:grid-cols-2"
         style="background-color: color-mix(in srgb, var(--color-primary) 8%, white)">
        <div class="p-8 md:p-12 flex flex-col justify-center">
            <span class="inline-block bg-white text-xs font-bold uppercase tracking-widest px-3 py-1.5 rounded-full mb-4 w-fit"
                  style="color: var(--color-primary)">
                {{ $config['badge'] ?? 'Admissions Open' }}
            </span>
            <h2 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-2">
                {{ $config['heading'] ?? 'Join Our Community' }}
            </h2>
            @if(!empty($config['subtitle']))
            <h3 class="text-lg font-semibold mb-4" style="color: var(--color-primary)">{{ $config['subtitle'] }}</h3>
            @endif
            @if(!empty($config['content']))
            <p class="text-gray-600 leading-relaxed mb-6">{!! nl2br(e($config['content'])) !!}</p>
            @endif

            <a href="{{ $config['cta_url'] ?? '/admission-enquiry' }}"
               class="inline-block w-fit font-semibold px-6 py-3 rounded-full text-white hover:opacity-90 transition"
               style="background-color: var(--color-primary)">
                {{ $config['cta_label'] ?? 'Submit enquiry' }}
            </a>

            @if(!empty($config['notice_text']))
            <div class="mt-4 text-sm rounded-lg px-4 py-3 border" style="background-color: #fff8e1; border-color: #fde68a; color: #92400e">
                {{ $config['notice_text'] }}
            </div>
            @endif
        </div>

        @if($image)
        <div class="min-h-[320px] md:min-h-full">
            <img loading="lazy" src="{{ $image }}" alt="{{ $config['heading'] ?? 'Admissions' }}"
                 class="w-full h-full object-cover">
        </div>
        @endif
    </div>
</section>
