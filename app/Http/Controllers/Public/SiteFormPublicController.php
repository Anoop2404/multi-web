<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\RendersPublicPages;
use App\Models\SiteForm;
use App\Models\SiteFormSubmission;
use App\Models\Tenant;
use App\Services\Mail\SchoolSiteMailer;
use App\Support\Mail\EmailBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SiteFormPublicController extends Controller
{
    use RendersPublicPages;

    public function show(Request $request, string $slug)
    {
        $tenant = $this->resolveTenant();
        $form = $this->resolveForm($tenant, $slug);

        return $this->renderPublic('public.site-form', $tenant, [
            'form' => $form,
        ]);
    }

    public function submit(Request $request, string $slug)
    {
        $tenant = $this->resolveTenant();
        $form = $this->resolveForm($tenant, $slug);

        $isSpam = false;
        if ($form->honeypot_enabled && filled($request->input('website_url'))) {
            $isSpam = true;
        }

        $payload = [];
        $rules = [];
        foreach ($form->fields_json ?? [] as $field) {
            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }

            $type = $field['type'] ?? 'text';
            $rules[$key] = [
                ! empty($field['required']) && ! $isSpam ? 'required' : 'nullable',
                match ($type) {
                    'email' => 'email',
                    'date' => 'date',
                    'number' => 'numeric',
                    default => 'string',
                },
                $type === 'textarea' ? 'max:5000' : 'max:500',
            ];
        }

        $validated = Validator::make($request->all(), $rules)->validate();

        foreach (array_keys($rules) as $key) {
            $value = $validated[$key] ?? null;
            $payload[$key] = is_string($value) ? strip_tags($value) : $value;
        }

        $submission = SiteFormSubmission::create([
            'site_form_id' => $form->id,
            'payload_json' => $payload,
            'ip' => $request->ip(),
            'user_agent' => Str::limit($request->userAgent() ?? '', 500),
            'is_spam' => $isSpam,
        ]);

        if (! $isSpam) {
            $sahodaya = $tenant->parent_id ? Tenant::query()->find($tenant->parent_id) : null;
            app(SchoolSiteMailer::class)->sendToAddress(
                $tenant,
                $form->notify_email,
                'New website form submission — '.$form->name,
                'emails.site-form-submission',
                array_merge(
                    EmailBranding::forTenant($sahodaya ?? $tenant),
                    [
                        'form' => $form,
                        'submission' => $submission,
                        'school' => $tenant,
                        'headerTitle' => 'New Website Enquiry',
                        'headerSubtitle' => $tenant->name,
                        'headerEyebrow' => 'Website',
                        'footerNote' => 'Submitted via '.$tenant->name.' website',
                    ],
                ),
            );
        }

        return back()->with('success', $form->success_message ?: 'Thank you — we received your message.');
    }

    private function resolveForm(Tenant $tenant, string $slug): SiteForm
    {
        $form = SiteForm::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->first();

        if (! $form && $tenant->type === 'school' && $slug === 'contact') {
            $form = SiteForm::ensureDefaultContact($tenant->id);
        }

        abort_if(! $form || ! $form->is_active, 404);

        return $form;
    }
}
