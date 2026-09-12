<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o tenant do endpoint público de captação de leads a partir do
 * segredo enviado no header (`Authorization: Bearer <token>` ou
 * `X-Trafego-Token: <token>`). Sem token válido ⇒ 401, sem vazar nada.
 */
class ResolveTenantPorTokenLead
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-Trafego-Token');

        $tenant = is_string($token) && $token !== ''
            ? Tenant::pelaChaveLeadTrafego($token)
            : null;

        if (! $tenant instanceof Tenant) {
            return response()->json(['message' => 'Token de captação inválido.'], 401);
        }

        app(CurrentTenant::class)->set($tenant);

        return $next($request);
    }
}
