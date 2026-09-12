<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies `TenantScope` and auto-fills `tenant_id` on creation from the
 * currently resolved tenant. `tenant_id` is intentionally left out of each
 * model's `#[Fillable]` list so it can never be mass-assigned from input.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->tenant_id === null) {
                $model->tenant_id = app(CurrentTenant::class)->id();
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
