<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsController extends SchoolAdminController
{
    public function index()
    {
        $articles = NewsArticle::where('tenant_id', $this->school->id)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->inertia('School/News/Index', compact('articles'));
    }

    public function create()
    {
        return $this->inertia('School/News/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'category'     => 'nullable|string|max:100',
            'is_featured'  => 'boolean',
            'published_at' => 'nullable|date',
            'image'        => 'nullable|image|max:4096',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['slug']      = Str::slug($data['title']) . '-' . Str::random(5);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('news/' . $this->school->id, 's3');
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
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'category'     => 'nullable|string|max:100',
            'is_featured'  => 'boolean',
            'published_at' => 'nullable|date',
            'image'        => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('news/' . $this->school->id, 's3');
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
}
