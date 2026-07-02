<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\ScreenSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ScreenSettingController extends SahodayaAdminController
{
    public function index()
    {
        $screens = ScreenSetting::where('tenant_id', $this->sahodaya->id)
            ->orderBy('title')
            ->get();

        $events = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->whereIn('status', ['published', 'registration_open', 'ongoing', 'completed'])
            ->orderByDesc('event_start')
            ->get(['id', 'title', 'status']);

        return $this->inertia('Sahodaya/DisplayScreens/Index', [
            'screens' => $screens,
            'events'  => $events,
            'defaultEventId' => request()->filled('event_id') ? (int) request('event_id') : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'slug'     => [
                'nullable',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('screen_settings', 'slug')->where('tenant_id', $this->sahodaya->id),
            ],
            'event_id' => 'nullable|exists:fest_events,id',
        ]);

        $slug = $data['slug'] ?? Str::slug($data['title']);

        ScreenSetting::create([
            'tenant_id'   => $this->sahodaya->id,
            'slug'        => $slug,
            'title'       => $data['title'],
            'config_json' => filled($data['event_id'] ?? null) ? ['event_id' => (int) $data['event_id']] : [],
            'is_active'   => true,
        ]);

        return back()->with('success', 'Display screen created.');
    }

    public function update(Request $request, string $tenantId, ScreenSetting $screen)
    {
        abort_if($screen->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'event_id'   => 'nullable|exists:fest_events,id',
            'is_active'  => 'boolean',
        ]);

        $config = $screen->config_json ?? [];
        if (array_key_exists('event_id', $data)) {
            $config['event_id'] = $data['event_id'] ? (int) $data['event_id'] : null;
        }

        $screen->update([
            'title'       => $data['title'],
            'config_json' => $config,
            'is_active'   => $data['is_active'] ?? $screen->is_active,
        ]);

        return back()->with('success', 'Screen updated.');
    }

    public function destroy(string $tenantId, ScreenSetting $screen)
    {
        abort_if($screen->tenant_id !== $this->sahodaya->id, 403);
        $screen->delete();

        return back()->with('success', 'Screen removed.');
    }
}
