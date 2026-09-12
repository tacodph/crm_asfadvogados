<?php

namespace Tests\Feature;

use App\Actions\Tenancy\SeedDefaultCatalogsForTenant;
use App\Models\CanalContato;
use App\Models\Role;
use App\Models\Setor;
use App\Models\StatusComercial;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSelfServiceSignupTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nova Banca Advocacia',
            'plan' => 'escritorio',
            'owner_name' => 'Sócia Fundadora',
            'owner_email' => 'socia@novabanca.adv.br',
            'owner_password' => 'password',
            'owner_password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_signup_form_can_be_rendered(): void
    {
        $this->withoutVite()->get($this->centralUrl('tenants.create'))->assertOk();
    }

    public function test_signup_creates_tenant_owner_and_default_catalogs_atomically(): void
    {
        $response = $this->post($this->centralUrl('tenants.store'), $this->payload());

        $tenant = Tenant::query()->where('name', 'Nova Banca Advocacia')->firstOrFail();

        $this->assertNotNull($tenant->slug);
        $this->assertSame('escritorio', $tenant->plan);
        $this->assertSame('trial', $tenant->status);
        $this->assertNotNull($tenant->trial_ends_at);

        $catalogs = new SeedDefaultCatalogsForTenant;

        $this->asTenant($tenant, function () use ($catalogs) {
            $owner = User::query()->where('email', 'socia@novabanca.adv.br')->firstOrFail();
            $this->assertSame(Role::OWNER, $owner->role);
            $this->assertSame(count($catalogs->setores()), Setor::query()->count());
            $this->assertSame(count($catalogs->canais()), CanalContato::query()->count());
            $this->assertSame(count($catalogs->statusComerciais()), StatusComercial::query()->count());
        });

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_signup_logs_the_owner_in_as_the_new_tenants_owner(): void
    {
        $this->post($this->centralUrl('tenants.store'), $this->payload());

        $tenant = Tenant::query()->where('name', 'Nova Banca Advocacia')->firstOrFail();
        $owner = $this->asTenant($tenant, fn () => User::query()->where('email', 'socia@novabanca.adv.br')->firstOrFail());

        $this->assertAuthenticatedAs($owner);
    }

    public function test_signup_derives_a_unique_slug_from_the_office_name(): void
    {
        $this->createTenant(['name' => 'Nova Banca Advocacia', 'slug' => 'nova-banca-advocacia']);

        $this->post($this->centralUrl('tenants.store'), $this->payload())->assertSessionHasNoErrors();

        $novo = Tenant::query()->where('slug', 'nova-banca-advocacia-2')->first();
        $this->assertNotNull($novo);
    }

    public function test_signup_fails_validation_when_owner_email_is_already_taken(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, fn () => User::factory()->create(['email' => 'repetido@email.com']));

        $this->post($this->centralUrl('tenants.store'), $this->payload(['owner_email' => 'repetido@email.com']))
            ->assertSessionHasErrors('owner_email');

        $this->assertNull(Tenant::query()->where('name', 'Nova Banca Advocacia')->first());
    }

    public function test_failed_signup_does_not_leave_a_partial_tenant_behind(): void
    {
        // Missing owner_password triggers a validation failure inside the
        // request lifecycle, before the transactional action ever runs.
        $this->post($this->centralUrl('tenants.store'), $this->payload(['owner_password' => '', 'owner_password_confirmation' => '']))
            ->assertSessionHasErrors('owner_password');

        $this->assertNull(Tenant::query()->where('name', 'Nova Banca Advocacia')->first());
    }

    protected function tearDown(): void
    {
        app(CurrentTenant::class)->set($this->tenant);

        parent::tearDown();
    }
}
