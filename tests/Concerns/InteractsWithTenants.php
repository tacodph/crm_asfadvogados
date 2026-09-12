<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\URL;

/**
 * Test helpers for multitenancy. Login is a single normal domain — the
 * current tenant comes from the authenticated user, not the URL — so these
 * are now just route() wrappers kept for call-site compatibility.
 */
trait InteractsWithTenants
{
    /**
     * Create a tenant and its default catalogs, without making it "current".
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        return Tenant::factory()->create($attributes);
    }

    protected function tenantUrl(string $name, array $parameters = [], ?Tenant $tenant = null): string
    {
        return route($name, $parameters);
    }

    protected function centralUrl(string $name, array $parameters = []): string
    {
        return route($name, $parameters);
    }

    /**
     * Run a callback as if the given tenant were resolved (mirrors
     * `CurrentTenant::runAs`, exposed here so tests read naturally).
     */
    protected function asTenant(Tenant $tenant, \Closure $callback): mixed
    {
        return app(CurrentTenant::class)->runAs($tenant, $callback);
    }

    protected function signedTenantRoute(
        string $name,
        \DateTimeInterface $expiration,
        array $parameters = [],
        ?Tenant $tenant = null,
    ): string {
        return URL::temporarySignedRoute($name, $expiration, $parameters);
    }
}
