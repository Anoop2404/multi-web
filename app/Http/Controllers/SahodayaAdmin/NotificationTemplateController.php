<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTemplateVariables;
use Illuminate\Http\Request;

class NotificationTemplateController extends SahodayaAdminController
{
    public function index()
    {
        $templates = NotificationTemplate::orderBy('slug')->get()->map(function (NotificationTemplate $t) {
            $row = $t->toArray();
            $row['available_variables'] = NotificationTemplateVariables::forSlug($t->slug);

            return $row;
        });

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

    /**
     * Send this template (with sample placeholder values, or the values
     * currently unsaved in the form) to the logged-in admin's own email, so
     * they can see exactly what recipients will get before rolling it out.
     */
    public function sendTest(Request $request, string $tenantId, NotificationTemplate $template)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'body_template' => 'required|string|max:5000',
        ]);

        $to = $request->user()->email;
        if (! $to) {
            return back()->withErrors(['test' => 'Your own account has no email address to send a test to.']);
        }

        $variables = NotificationTemplateVariables::forSlug($template->slug);
        $title = $data['title'];
        $body = $data['body_template'];

        foreach ($variables as $variable) {
            $sample = NotificationTemplateVariables::sampleValue($variable);
            $title = str_replace('{{'.$variable.'}}', $sample, $title);
            $body = str_replace('{{'.$variable.'}}', $sample, $body);
        }

        app(NotificationService::class)->notifyEmailToAddress(
            $to,
            $this->sahodaya->id,
            '[Test] '.$title,
            "This is a test send of the \"{$template->slug}\" template with sample data — recipients won't see this banner.\n\n".$body,
            'template.test.'.$template->slug,
        );

        return back()->with('success', "Test email sent to {$to}.");
    }
}
