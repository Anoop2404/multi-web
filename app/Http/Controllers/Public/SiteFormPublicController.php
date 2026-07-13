<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\SiteForm;
use App\Models\SiteFormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteFormPublicController extends Controller
{
    use RendersPublicPages;

    public function show(Request $request, string $slug)
    {
        $tenant = $this->resolveTenant();
        $form = SiteForm::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->renderPublic('public.site-form', $tenant, [
            'form' => $form,
        ]);
    }

    public function submit(Request $request, string $slug)
    {
        $tenant = $this->resolveTenant();
        $form = SiteForm::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $isSpam = false;
        if ($form->honeypot_enabled && filled($request->input('website_url'))) {
            $isSpam = true;
        }

        $payload = [];
        foreach ($form->fields_json ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }
            $value = $request->input($key);
            if (! empty($field['required']) && blank($value) && ! $isSpam) {
                return back()->withErrors([$key => 'Required.'])->withInput();
            }
            $payload[$key] = is_string($value) ? strip_tags($value) : $value;
        }

        SiteFormSubmission::create([
            'site_form_id' => $form->id,
            'payload_json' => $payload,
            'ip' => $request->ip(),
            'user_agent' => Str::limit($request->userAgent() ?? '', 500),
            'is_spam' => $isSpam,
        ]);

        return back()->with('success', $form->success_message ?: 'Thank you — we received your message.');
    }
}
