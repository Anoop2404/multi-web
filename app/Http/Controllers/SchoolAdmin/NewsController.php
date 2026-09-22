<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\NewsArticle;
use App\Support\HtmlSanitizer;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();
        $sort = in_array($request->string('sort')->toString(), ['title', 'category', 'published_at', 'created_at'], true)
            ? $request->string('sort')->toString()
            : 'published_at';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $articles = NewsArticle::where('tenant_id', $this->school->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($status === 'published', fn ($query) => $query->whereNotNull('published_at'))
            ->when($status === 'draft', fn ($query) => $query->whereNull('published_at'))
            ->orderBy($sort, $dir)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $categories = NewsArticle::where('tenant_id', $this->school->id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return $this->inertia('School/News/Index', [
            'articles' => $articles,
            'categories' => $categories,
            'filters' => compact('search', 'status', 'category', 'sort', 'dir'),
        ]);
    }

    public function create()
    {
        return $this->inertia('School/News/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'nullable|string|max:100',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:4096',
        ]);

        $data['body'] = $this->sanitizeBody($data['body']);

        $data['tenant_id'] = $this->school->id;
        $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);

        if ($request->hasFile('image')) {
            $data['image'] = TenantStorage::storeSiteMedia($request->file('image'), $this->school->id);
        }

        NewsArticle::create($data);

        return redirect("/school-admin/{$this->school->id}/news")
            ->with('success', 'Article published.');
    }

    public function edit(string $tenantId, NewsArticle $news)
    {
        abort_if($news->tenant_id !== $this->school->id, 403);

        return $this->inertia('School/News/Edit', compact('news'));
    }

    public function update(Request $request, string $tenantId, NewsArticle $news)
    {
        abort_if($news->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'nullable|string|max:100',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:4096',
        ]);

        $data['body'] = $this->sanitizeBody($data['body']);

        if ($request->hasFile('image')) {
            $data['image'] = TenantStorage::storeSiteMedia($request->file('image'), $this->school->id);
        }

        $news->update($data);

        return redirect("/school-admin/{$this->school->id}/news")
            ->with('success', 'Article updated.');
    }

    public function destroy(string $tenantId, NewsArticle $news)
    {
        abort_if($news->tenant_id !== $this->school->id, 403);
        $news->delete();

        return back()->with('success', 'Article deleted.');
    }

    private function sanitizeBody(string $body): string
    {
        $body = HtmlSanitizer::rich($body);
        $plainText = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/[\s\x{00A0}]+/u', '', $plainText) ?? '';

        if ($plainText === '') {
            throw ValidationException::withMessages([
                'body' => 'Please add some article content.',
            ]);
        }

        return $body;
    }
}
