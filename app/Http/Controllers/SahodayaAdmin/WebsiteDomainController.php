<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\WebsiteSite;
use App\Support\TenantDomainSync;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WebsiteDomainController extends SahodayaAdminController
{
    public function index()
    {
        $tenant = $this->sahodaya->fresh();
        $domains = $tenant->domains()->orderByDesc('is_primary')->orderBy('domain')->get()->map(fn ($d) => [
            'id' => $d->id,
            'domain' => $d->domain,
            'is_primary' => (bool) ($d->is_primary ?? false),
            'verified_at' => $d->verified_at,
            'dns_token' => $d->dns_token,
            'ssl_status' => $d->ssl_status,
            'txt_record' => $d->dns_token ? "sahodaya-verify={$d->dns_token}" : null,
        ]);

        return $this->inertia('Sahodaya/Website/Domains', [
            'domains' => $domains,
            'currentDomain' => $tenant->domain,
            'currentSubdomain' => $tenant->subdomain,
            'publicUrl' => TenantDomainSync::publicUrl($tenant),
            'baseDomain' => config('tenancy.tenant_base_domain'),
            'sites' => WebsiteSite::where('tenant_id', $tenant->id)->orderByDesc('is_primary')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => [
                'required', 'string', 'max:255',
                Rule::unique('domains', 'domain'),
            ],
        ]);

        $host = TenantDomainSync::normalizeCustomDomain($data['domain']);
        abort_if(TenantDomainSync::isCentralHost($host), 422, 'That domain is reserved.');

        $token = Str::random(32);
        $this->sahodaya->update(['domain' => $host]);
        TenantDomainSync::sync($this->sahodaya->fresh());

        $domain = $this->sahodaya->domains()->where('domain', $host)->first();
        if ($domain) {
            $domain->update([
                'is_primary' => true,
                'dns_token' => $token,
                'verified_at' => null,
                'ssl_status' => 'pending',
            ]);
        }

        return back()->with('success', 'Custom domain saved. Add the TXT record to verify ownership.');
    }

    public function verify(string $tenantId, int $domainId)
    {
        $domain = $this->sahodaya->domains()->findOrFail($domainId);
        abort_unless($domain->dns_token, 422, 'No verification token.');

        $expected = 'sahodaya-verify='.$domain->dns_token;
        $verified = $this->lookupTxtContains($domain->domain, $expected);

        if (! $verified) {
            // Dev/local convenience: allow verify when APP_ENV is local
            if (app()->environment('local', 'testing')) {
                $verified = true;
            }
        }

        abort_unless($verified, 422, 'TXT record not found yet. DNS can take a few minutes to propagate.');

        $domain->update([
            'verified_at' => now(),
            'ssl_status' => 'active',
        ]);

        return back()->with('success', 'Domain verified.');
    }

    public function destroy(string $tenantId, int $domainId)
    {
        $domain = $this->sahodaya->domains()->findOrFail($domainId);
        $wasCustom = $this->sahodaya->domain && $domain->domain === TenantDomainSync::normalizeCustomDomain($this->sahodaya->domain);

        $domain->delete();
        if ($wasCustom) {
            $this->sahodaya->update(['domain' => null]);
            TenantDomainSync::sync($this->sahodaya->fresh());
        }

        return back()->with('success', 'Domain removed.');
    }

    private function lookupTxtContains(string $host, string $needle): bool
    {
        try {
            $records = dns_get_record($host, DNS_TXT) ?: [];
            foreach ($records as $rec) {
                $txt = $rec['txt'] ?? '';
                if (is_string($txt) && str_contains($txt, $needle)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
