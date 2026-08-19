@php
    $extractYouTubeId = function (?string $url) {
        if (empty($url)) return null;
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) return $url;
        preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
        return $m[1] ?? null;
    };
    $videos = collect($config['videos'] ?? [])
        ->map(fn ($v) => ['title' => $v['title'] ?? '', 'id' => $extractYouTubeId($v['url'] ?? null)])
        ->filter(fn ($v) => !empty($v['id']))
        ->values();
@endphp
@if($videos->isNotEmpty())
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-white border-t border-slate-200">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'Video Highlights' }}
            </h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($videos as $video)
            <div class="v2-card overflow-hidden">
                <div class="aspect-video">
                    <iframe class="w-full h-full border-0" src="https://www.youtube-nocookie.com/embed/{{ $video['id'] }}" title="{{ $video['title'] ?: 'Video' }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </div>
                @if(!empty($video['title']))
                <div class="p-4">
                    <h3 class="font-semibold text-sm text-slate-800">{{ $video['title'] }}</h3>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
