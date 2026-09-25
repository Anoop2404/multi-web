{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}{{-- Echoed, not written literally: with short_open_tag=On (common on hosted PHP builds) a literal
     "<?xml" at the top of a template is parsed as PHP and the compiled view dies with
     "syntax error, unexpected identifier 'version'" (production, 2026-09-25). --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        @if(!empty($url['lastmod']))<lastmod>{{ $url['lastmod'] }}</lastmod>@endif
        @if(!empty($url['changefreq']))<changefreq>{{ $url['changefreq'] }}</changefreq>@endif
        @if(!empty($url['priority']))<priority>{{ $url['priority'] }}</priority>@endif
    </url>
@endforeach
</urlset>
