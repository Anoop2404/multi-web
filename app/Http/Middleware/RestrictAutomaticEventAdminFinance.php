<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\EventAdminFinancePolicy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RestrictAutomaticEventAdminFinance
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user instanceof User && $user->hasRestrictedEventFinance()) {
            EventAdminFinancePolicy::assertRequestAllowed($request);
        }

        $response = $next($request);
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasRestrictedEventFinance()) {
            return $response;
        }
        if ($response instanceof JsonResponse) {
            $response->setData(EventAdminFinancePolicy::withoutFinance($response->getData(true)));
        } elseif ($response instanceof Response && $response->original instanceof View) {
            $view = $response->original;
            $page = $view->getData()['page'] ?? null;
            if (is_array($page) && isset($page['props'])) {
                $page['props'] = EventAdminFinancePolicy::withoutFinance($page['props']);
                $response->setContent($view->with('page', $page)->render());
                $response->original = $view;
            }
        }

        return $response;
    }
}
