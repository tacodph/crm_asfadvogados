<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on a tenant-scoped model to the current tenant.
 *
 * Fails closed: if no tenant is resolved (central-domain request, or a
 * console command/seeder that hasn't opted in via `CurrentTenant::runAs()`),
 * the query matches zero rows rather than every tenant's rows. This is the
 * correct default for data covered by LGPD/client confidentiality.
 */
/**
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentTenant = app(CurrentTenant::class);

        if (! $currentTenant->resolved()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $currentTenant->id());
    }
}
