<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * Request-scoped (or console-scoped) holder for "which tenant are we operating as".
 *
 * Bound as a singleton so both `ResolveTenant` middleware and `TenantScope`
 * read/write the same instance. Central-domain requests leave this unresolved.
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function resolved(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Run a callback as if the given tenant were resolved, restoring the
     * previous tenant afterwards. Used by console commands, seeders, and the
     * tenant-provisioning flow, which don't go through `ResolveTenant`.
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
