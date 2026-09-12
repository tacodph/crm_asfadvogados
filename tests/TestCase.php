<?php

namespace Tests;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Concerns\InteractsWithTenants;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithTenants;

    /**
     * The tenant most tests implicitly operate under. Individual isolation
     * tests create a second tenant explicitly via `createTenant()`.
     */
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        // Mirrors what `ResolveTenant` would set from the request host, so
        // factory calls made before an HTTP request (the "arrange" phase of
        // a test) are already tenant-scoped. The HTTP kernel re-resolves it
        // from the request's host on every `tenantUrl()`/`centralUrl()` call
        // made through this class, so this is just the default for setup.
        app(CurrentTenant::class)->set($this->tenant);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
