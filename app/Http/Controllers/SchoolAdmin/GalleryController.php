<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryController extends SchoolAdminController
{
    public function index()
    {
        $albums = GalleryAlbum::where('tenant_id', $this->school->id)
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->orderBy('display_order')])
            ->orderBy('display_order')
            ->get();

        return $this->inertia('School/Gallery/Index', compact('albums'));
    }

    public function storeAlbum(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['slug'] = Str::slug($data['title']).'-'.Str::random(4);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = TenantStorage::storeSiteMedia($request->file('cover_image'), $this->school->id);
        }

        $album = GalleryAlbum::create($data);

        return back()->with('success', "Album \"{$album->title}\" created.");
    }

    public function updateAlbum(Request $request, string $tenantId, GalleryAlbum $album)
    {
        abort_if($album->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'display_order' => 'nullable|integer|min:0|max:10000',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'remove_cover' => 'nullable|boolean',
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = TenantStorage::storeSiteMedia($request->file('cover_image'), $this->school->id);
        } elseif ($request->boolean('remove_cover')) {
            $data['cover_image'] = null;
        }

        unset($data['remove_cover']);

        $album->update($data);

        return back()->with('success', "Album \"{$album->title}\" updated.");
    }

    public function uploadPhotos(Request $request, string $tenantId, GalleryAlbum $album)
    {
        abort_if($album->tenant_id !== $this->school->id, 403);

        $request->validate([
            'photos' => 'required|array|min:1|max:30',
            'photos.*' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:8192',
        ]);

        $order = $album->items()->max('display_order') + 1;
        $firstPath = null;

        foreach ($request->file('photos') as $photo) {
            $path = TenantStorage::storeSiteMedia($photo, $this->school->id);
            $firstPath ??= $path;
            GalleryItem::create([
                'album_id' => $album->id,
                'tenant_id' => $this->school->id,
                'image_path' => $path,
                'display_order' => $order++,
            ]);
        }

        if (! $album->cover_image && $firstPath) {
            $album->update(['cover_image' => $firstPath]);
        }

        return back()->with('success', count($request->file('photos')).' photos uploaded.');
    }

    public function setCover(string $tenantId, GalleryAlbum $album, GalleryItem $photo)
    {
        abort_if($album->tenant_id !== $this->school->id, 403);
        abort_if($photo->tenant_id !== $this->school->id || $photo->album_id !== $album->id, 403);

        $album->update(['cover_image' => $photo->image_path]);

        return back()->with('success', 'Album cover updated.');
    }

    public function updatePhoto(Request $request, string $tenantId, GalleryItem $photo)
    {
        abort_if($photo->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'caption' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0|max:10000',
        ]);

        $photo->update($data);

        return back()->with('success', 'Photo details updated.');
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
        $album = $photo->album;
        $wasCover = $album?->cover_image === $photo->image_path;
        $photo->delete();

        if ($wasCover && $album) {
            $album->update(['cover_image' => $album->items()->value('image_path')]);
        }

        return back()->with('success', 'Photo removed.');
    }
}
