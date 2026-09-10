<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\NewsArticle;
use App\Support\SchoolPublicPageContent;
use App\Support\TenantStorage;
use Illuminate\Support\Str;

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
            'pageSeo' => SchoolPublicPageContent::seo($tenant, 'news', [
                'title' => 'News & Announcements — '.$tenant->name,
                'description' => 'Latest news and announcements from '.$tenant->name,
                'og_type' => 'website',
            ]),
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
                'title' => $article->title.' — '.$tenant->name,
                'description' => Str::limit(strip_tags($article->body ?? ''), 160),
                'og_image' => $article->image_url,
                'og_type' => 'article',
            ],
        ]);
    }

    public function image(NewsArticle $article)
    {
        $tenant = $this->resolveTenant();

        abort_unless($article->tenant_id === $tenant->id && $article->image, 404);

        return TenantStorage::downloadResponse($tenant, $article->image);
    }
}
