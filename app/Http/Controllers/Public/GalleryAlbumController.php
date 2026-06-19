<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\GalleryAlbum;
use Illuminate\Support\Str;

class GalleryAlbumController extends Controller
{
    use RendersPublicPages;

    public function show(string $slug)
    {
        $tenant = $this->resolveTenant();

        $album = GalleryAlbum::with('items')
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->renderPublic('public.gallery.show', $tenant, [
            'album'   => $album,
            'pageSeo' => [
                'title'       => $album->title.' — Gallery — '.$tenant->name,
                'description' => Str::limit(strip_tags($album->description ?? ''), 160),
                'og_image'    => $album->cover_image,
                'og_type'     => 'website',
            ],
        ]);
    }
}
