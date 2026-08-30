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

            @if(!empty($config['key_dates']) && is_array($config['key_dates']))
            <ul class="mb-6 space-y-2">
                @foreach($config['key_dates'] as $date)
                <li class="flex items-baseline gap-3 text-sm">
                    <span class="font-bold shrink-0" style="color: var(--color-primary)">{{ $date['date'] ?? '' }}</span>
                    <span class="text-gray-600">{{ $date['label'] ?? '' }}</span>
                </li>
                @endforeach
            </ul>
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

        <div class="min-h-[320px] md:min-h-full">
            @if($image)
            <img loading="lazy" src="{{ $image }}" alt="{{ $config['heading'] ?? 'Admissions' }}"
                 class="w-full h-full object-cover">
            @else
            <div class="w-full h-full flex items-center justify-center"
                 style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary, var(--color-primary)) 100%)">
                <svg class="w-20 h-20 text-white/60" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            </div>
            @endif
        </div>
    </div>
</section>
