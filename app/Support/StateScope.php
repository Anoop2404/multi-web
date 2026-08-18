<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Data-isolation fix (FRD-13 gap analysis, Finding A): state_admin/state_staff
 * accounts had no state assignment and no ownership check anywhere, so any state
 * user could read/write every other state's data. EnsureStateAdmin middleware
 * stashes the acting user's state_id on the request as 'stateId' (superadmin
 * bypasses that middleware's checks entirely and is never scoped here either).
 *
 * Two call patterns:
 *  - apply() for index/listing queries.
 *  - assertOwns() for methods that receive an already route-bound single model —
 *    doesn't rely on middleware/SubstituteBindings ordering, since route-model
 *    binding resolves independently of query scoping.
 */
class StateScope
{
    public static function shouldScope(?Request $request = null): bool
    {
        $request ??= request();

        return ! ($request->user()?->isSuperAdmin() ?? false);
    }

    public static function id(?Request $request = null): ?string
    {
        $request ??= request();

        return $request->attributes->get('stateId');
    }

    public static function apply(Builder $query, string $column = 'state_id', ?Request $request = null): Builder
    {
        if (! self::shouldScope($request)) {
            return $query;
        }

        $stateId = self::id($request);

        // No state assigned yet — fail closed (see nothing), not open.
        return $stateId === null
            ? $query->whereRaw('1 = 0')
            : $query->where($column, $stateId);
    }

    /** Abort 403 unless the resource's own state_id matches the acting user's. Superadmin always passes. */
    public static function assertOwns(?string $resourceStateId, ?Request $request = null): void
    {
        if (! self::shouldScope($request)) {
            return;
        }

        $stateId = self::id($request);

        abort_if(
            $stateId === null || $resourceStateId !== $stateId,
            403,
            "You do not have access to this state's data."
        );
    }
}
