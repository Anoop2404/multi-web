<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\Event;
use Illuminate\Support\Str;

class EventController extends Controller
{
    use RendersPublicPages;

    public function index()
    {
        $tenant = $this->resolveTenant();

        $events = Event::where('tenant_id', $tenant->id)
            ->orderByDesc('start_date')
            ->paginate(12);

        return $this->renderPublic('public.events.index', $tenant, [
            'events'  => $events,
            'pageSeo' => [
                'title'       => 'Events — '.$tenant->name,
                'description' => 'Upcoming and past events at '.$tenant->name,
                'og_type'     => 'website',
            ],
        ]);
    }

    public function show(string $slug)
    {
        $tenant = $this->resolveTenant();

        $event = Event::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->renderPublic('public.events.show', $tenant, [
            'event' => $event,
            'pageSeo' => [
                'title'       => $event->title.' — '.$tenant->name,
                'description' => Str::limit(strip_tags($event->description ?? ''), 160),
                'og_image'    => $event->image,
                'og_type'     => 'event',
            ],
        ]);
    }
}
