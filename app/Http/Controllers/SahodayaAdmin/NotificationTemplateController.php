<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

class NotificationTemplateController extends SahodayaAdminController
{
    public function index()
    {
        $templates = NotificationTemplate::orderBy('slug')->get();

        return $this->inertia('Sahodaya/NotificationTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function update(Request $request, string $tenantId, NotificationTemplate $template)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'body_template' => 'required|string|max:5000',
            'is_active'     => 'boolean',
            'channels_json' => 'nullable|array',
            'channels_json.*' => 'string|in:mail,push,in_app',
        ]);

        $template->update([
            'title'         => $data['title'],
            'body_template' => $data['body_template'],
            'is_active'     => $request->boolean('is_active'),
            'channels_json' => $data['channels_json'] ?? $template->channels_json,
        ]);

        return back()->with('success', 'Template updated.');
    }
}
