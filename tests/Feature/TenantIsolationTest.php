<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Negociacao;
use App\Models\Setor;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresas_are_scoped_to_the_current_tenant(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, fn () => Empresa::factory()->create(['nome' => 'Empresa de Outro Tenant']));

        Empresa::factory()->create(['nome' => 'Empresa do Meu Tenant']);

        $this->assertSame(['Empresa do Meu Tenant'], Empresa::query()->pluck('nome')->all());
    }

    public function test_user_cannot_see_another_tenants_empresa_on_the_empresas_page(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, fn () => Empresa::factory()->create());

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('empresas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('empresas', 0));
    }

    public function test_deep_link_to_another_tenants_negociacao_returns_not_found(): void
    {
        $outro = $this->createTenant();

        [$negociacaoAlheia, $etapaAlheia] = $this->asTenant($outro, function () {
            $negociacao = Negociacao::factory()->create();
            $etapa = EtapaFunil::factory()->for($negociacao->funil)->create();

            return [$negociacao, $etapa];
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(
                $this->tenantUrl('negociacoes.update-etapa', ['negociacao' => $negociacaoAlheia]),
                ['etapa_funil_id' => $etapaAlheia->id],
            )
            // Route model binding resolves `negociacao` through the
            // tenant-scoped model, so another tenant's id simply doesn't bind.
            ->assertNotFound();
    }

    public function test_tenant_scope_fails_closed_when_no_tenant_is_resolved(): void
    {
        app(CurrentTenant::class)->set(null);

        $this->assertSame(0, Empresa::query()->count());
        $this->assertSame(0, Contato::query()->count());
    }

    public function test_catalog_tables_are_scoped_to_the_current_tenant(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, fn () => Setor::factory()->create(['slug' => 'setor-alheio', 'nome' => 'Setor Alheio']));

        Setor::factory()->create(['slug' => 'meu-setor', 'nome' => 'Meu Setor']);

        $this->assertSame(['meu-setor'], Setor::query()->pluck('slug')->all());
    }

    protected function tearDown(): void
    {
        // A test above may have unset the current tenant to exercise the
        // fail-closed scope; restore it so RefreshDatabase's own teardown
        // (which may touch tenant-scoped models) isn't affected.
        app(CurrentTenant::class)->set($this->tenant);

        parent::tearDown();
    }
}
