<?php

namespace Tests\Feature;

use App\Enums\StatusProposta;
use App\Models\Proposta;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\FunilNegociacaoSeeder;
use Database\Seeders\PropostaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PropostaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_lists_proposals_with_kpis(): void
    {
        $proposta = Proposta::factory()->create([
            'status' => StatusProposta::EmNegociacao,
            'honorarios' => 210000,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('propostas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Propostas')
                ->has('kpis', 3)
                ->has('propostas', 1)
                ->where('propostas.0.codigo', $proposta->codigo)
                ->where('propostas.0.status', 'Em negociação'));
    }

    public function test_show_opens_proposal_with_negotiation_contact_and_company(): void
    {
        $proposta = Proposta::factory()->create([
            'codigo' => 'PR-2026-124',
            'versao' => 3,
            'status' => StatusProposta::EmNegociacao,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('propostas.show', $proposta))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/PropostasShow')
                ->where('proposta.codigo', 'PR-2026-124')
                ->where('proposta.versaoNumero', 3)
                ->has('proposta.negociacao.id')
                ->has('proposta.contatoDetalhe.nome')
                ->has('proposta.clausulas'));
    }

    public function test_cannot_view_proposal_from_another_tenant(): void
    {
        $outro = $this->createTenant();
        $alheia = $this->asTenant($outro, fn () => Proposta::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('propostas.show', $alheia))
            ->assertNotFound();
    }

    public function test_prototype_seeder_creates_proposals_for_dev_tenant(): void
    {
        $this->seed(FunilNegociacaoSeeder::class);
        $this->seed(PropostaSeeder::class);

        $tenant = Tenant::query()->where('slug', 'asfadvogados')->firstOrFail();

        $this->asTenant($tenant, function (): void {
            $this->assertGreaterThanOrEqual(5, Proposta::query()->count());
            $this->assertTrue(
                Proposta::query()->where('codigo', 'PR-2026-124')->exists(),
            );
            $this->assertTrue(
                Proposta::query()->whereNotNull('negociacao_id')->whereNotNull('contato_id')->exists(),
            );
        });
    }

    public function test_guests_are_redirected_from_proposals(): void
    {
        $this->get(route('propostas.index'))->assertRedirect(route('login'));
    }
}
