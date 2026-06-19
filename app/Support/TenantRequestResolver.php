<?php

namespace App\Support;

use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

class TenantRequestResolver
{
    public static function initializeFromHost(string $host): void
    {
        $host = strtolower($host);

        $domain = Domain::where('domain', $host)->first();
        if ($domain?->tenant) {
            TenancyDatabase::initializeForTenant($domain->tenant);

            return;
        }

        $base = config('tenancy.tenant_base_domain');
        if ($base && str_ends_with($host, '.'.strtolower($base))) {
            $subdomain = substr($host, 0, -strlen('.'.strtolower($base)));
            $slug = Domain::where('domain', $subdomain)->first();
            if ($slug?->tenant) {
                TenancyDatabase::initializeForTenant($slug->tenant);

                return;
            }
        }

        throw new TenantCouldNotBeIdentifiedOnDomainException($host);
    }

    public static function initializeFromRequest(Request $request): void
    {
        self::initializeFromHost($request->getHost());
    }
}
