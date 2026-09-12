<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the current tenant from the authenticated user (login is a single,
 * normal domain — there's no subdomain to resolve it from). Guests simply
 * get no tenant, same as before any auth middleware runs.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        app(CurrentTenant::class)->set($request->user()?->tenant);

        return $next($request);
    }
}
