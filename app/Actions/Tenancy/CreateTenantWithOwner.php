<?php

namespace App\Actions\Tenancy;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantProvisioned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a brand new tenant plus its first (owner) user, from the
 * self-service signup flow. Separate from `App\Actions\Fortify\CreateNewUser`,
 * which invites a colleague onto an already-existing tenant.
 */
class CreateTenantWithOwner
{
    public function __construct(
        private readonly SeedDefaultCatalogsForTenant $seedDefaultCatalogs,
    ) {}

    /**
     * @param  array{name: string, plan: string, owner_name: string, owner_email: string, owner_password: string}  $data
     */
    public function __invoke(array $data): TenantProvisioned
    {
        Role::ensureDefaults();

        return DB::transaction(function () use ($data): TenantProvisioned {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'plan' => $data['plan'],
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            // tenant_id is auto-filled from CurrentTenant (set by runAs()) by BelongsToTenant.
            $owner = app(CurrentTenant::class)->runAs($tenant, fn (): User => User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
                'role' => Role::OWNER,
            ]));

            ($this->seedDefaultCatalogs)($tenant);

            return new TenantProvisioned($tenant, $owner);
        });
    }

    /**
     * `slug` is just an internal unique identifier now (no longer a
     * subdomain), so it's derived from the office name instead of asked for.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'escritorio';
        $slug = $base;

        for ($i = 2; Tenant::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
