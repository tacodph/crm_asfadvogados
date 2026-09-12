<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;

/**
 * Result of `App\Actions\Tenancy\CreateTenantWithOwner`. Carries the owner
 * user alongside the tenant so callers never need to re-query `User` outside
 * the tenant's own scope (the owner would be invisible from a central-domain
 * request, since `TenantScope` fails closed there).
 */
final class TenantProvisioned
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $owner,
    ) {}
}
