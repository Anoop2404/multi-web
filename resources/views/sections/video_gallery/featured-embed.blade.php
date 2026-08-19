@php
    $extractYouTubeId = function (?string $url) {
        if (empty($url)) return null;
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) return $url;
        preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
        return $m[1] ?? null;
    };
    $featuredId = $extractYouTubeId($config['featured_url'] ?? null);
    $videos = collect($config['videos'] ?? [])
        ->map(fn ($v) => ['title' => $v['title'] ?? '', 'id' => $extractYouTubeId($v['url'] ?? null)])
        ->filter(fn ($v) => !empty($v['id']))
        ->values();
@endphp
@if($featuredId || $videos->isNotEmpty())
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-white border-t border-slate-200">
    <div class="max-w-6xl mx-auto">
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

        <div class="grid lg:grid-cols-2 gap-8 items-start">
            @if($featuredId)
            <div class="v2-card overflow-hidden">
                <div class="aspect-video">
                    <iframe class="w-full h-full border-0" src="https://www.youtube-nocookie.com/embed/{{ $featuredId }}" title="{{ $config['featured_title'] ?? 'Featured video' }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </div>
            </div>
            @endif

            @if($videos->isNotEmpty())
            <div class="space-y-3">
                @foreach($videos as $video)
                <a href="https://www.youtube.com/watch?v={{ $video['id'] }}" target="_blank" rel="noopener" class="v2-card flex gap-3 p-3 group">
                    <div class="relative w-24 h-16 rounded-lg overflow-hidden shrink-0 bg-slate-100">
                        <img loading="lazy" src="https://img.youtube.com/vi/{{ $video['id'] }}/mqdefault.jpg" alt="" class="v2-media w-full h-full object-cover">
                        <div class="absolute inset-0 flex items-center justify-center bg-slate-950/20 group-hover:bg-slate-950/10 transition-colors">
                            <span class="w-7 h-7 rounded-full bg-accent text-white flex items-center justify-center shadow">
                                <svg class="w-3 h-3 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </div>
                    </div>
                    <div class="min-w-0 flex items-center">
                        <p class="text-sm font-semibold text-slate-800 group-hover:text-primary transition-colors line-clamp-2">{{ $video['title'] ?: 'Watch video' }}</p>
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</section>
@endif
