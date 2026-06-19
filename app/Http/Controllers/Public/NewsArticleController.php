<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\NewsArticle;

class NewsArticleController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        $articles = NewsArticle::where('tenant_id', $tenant->id)
            ->published()
            ->orderByDesc('published_at')
            ->paginate(12);

        return $this->renderPublic('public.news.index', $tenant, [
            'articles' => $articles,
            'pageSeo'  => [
                'title'       => 'News & Announcements — '.$tenant->name,
                'description' => 'Latest news and announcements from '.$tenant->name,
                'og_type'     => 'website',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $tenant = $this->resolveTenant();

        $article = NewsArticle::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return $this->renderPublic('public.news.show', $tenant, [
            'article' => $article,
            'pageSeo' => [
                'title'       => $article->title.' — '.$tenant->name,
                'description' => \Illuminate\Support\Str::limit(strip_tags($article->body ?? ''), 160),
                'og_image'    => $article->image,
                'og_type'     => 'article',
            ],
        ]);
    }
}
