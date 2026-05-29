<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryController extends SchoolAdminController
{
    public function index()
    {
        $albums = GalleryAlbum::where('tenant_id', $this->school->id)
            ->withCount('items')
            ->with(['items' => fn($q) => $q->orderBy('display_order')->limit(16)])
            ->orderBy('display_order')
            ->get();

        return $this->inertia('School/Gallery/Index', compact('albums'));
    }

    public function storeAlbum(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:4096',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['slug']      = Str::slug($data['title']) . '-' . Str::random(4);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('gallery/' . $this->school->id, 's3');
        }

        $album = GalleryAlbum::create($data);

        return back()->with('success', "Album \"{$album->title}\" created.");
    }

    public function uploadPhotos(Request $request, string $tenantId, GalleryAlbum $album)
    {
        abort_if($album->tenant_id !== $this->school->id, 403);

        $request->validate([
            'photos'   => 'required|array',
            'photos.*' => 'image|max:8192',
        ]);

        $order = $album->items()->max('display_order') + 1;

        foreach ($request->file('photos') as $photo) {
            $path = $photo->store('gallery/' . $this->school->id . '/' . $album->id, 's3');
            GalleryItem::create([
                'album_id'      => $album->id,
                'tenant_id'     => $this->school->id,
                'image_path'    => $path,
                'display_order' => $order++,
            ]);
        }

        return back()->with('success', count($request->file('photos')) . ' photos uploaded.');
    }

    public function destroyAlbum(string $tenantId, GalleryAlbum $album)
    {
        abort_if($album->tenant_id !== $this->school->id, 403);
        $album->delete();
        return back()->with('success', 'Album deleted.');
    }

    public function destroyPhoto(string $tenantId, GalleryItem $photo)
    {
        abort_if($photo->tenant_id !== $this->school->id, 403);
        $photo->delete();
        return back()->with('success', 'Photo removed.');
    }
}
