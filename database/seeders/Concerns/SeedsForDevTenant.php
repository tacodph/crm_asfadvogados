<?php

namespace Database\Seeders\Concerns;

use App\Models\Tenant;

/**
 * Resolves (idempotently) the tenant that local/dev seeders operate under.
 * Every seeder in this namespace uses the same slug, so nested `$this->call()`
 * chains (DatabaseSeeder → FunilNegociacaoSeeder → EmpresaContatoSeeder →
 * DominioCrmSeeder) all land on the same tenant.
 */
trait SeedsForDevTenant
{
    protected function devTenant(): Tenant
    {
        return Tenant::query()->firstOrCreate(
            ['slug' => 'asfadvogados'],
            ['name' => 'ASF Advogados', 'plan' => 'escritorio', 'status' => 'active'],
        );
    }
}
