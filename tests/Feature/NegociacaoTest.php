<?php

namespace Tests\Feature;

use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\FunilNegociacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NegociacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_negotiation_tables_exist(): void
    {
        foreach (['funis', 'etapas_funil', 'negociacoes', 'historicos_negociacao'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}].");
        }
    }

    public function test_negociacao_factory_persists_relationships(): void
    {
        $negociacao = Negociacao::factory()->create();

        $this->assertModelExists($negociacao);
        $this->assertModelExists($negociacao->funil);
        $this->assertModelExists($negociacao->etapaFunil);
        $this->assertModelExists($negociacao->contato);
        $this->assertModelExists($negociacao->responsavel);
        $this->assertSame($negociacao->funil_id, $negociacao->etapaFunil->funil_id);
    }

    public function test_etapa_belongs_to_funil(): void
    {
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();

        $this->assertSame($funil->id, $etapa->funil_id);
        $this->assertSame(['origem', 'consentimento'], $etapa->campos);
    }

    public function test_prototype_seeder_creates_funnels_and_deals(): void
    {
        $this->seed(FunilNegociacaoSeeder::class);

        // The seeder provisions its own dev tenant (slug "asfadvogados"),
        // independent of the random tenant TestCase::setUp() created.
        $tenant = Tenant::query()->where('slug', 'asfadvogados')->firstOrFail();

        $this->asTenant($tenant, function () {
            $this->assertSame(2, Funil::query()->count());
            $this->assertSame(12, EtapaFunil::query()->count());
            $this->assertSame(15, Negociacao::query()->count());
            $this->assertSame(7, Negociacao::query()->whereNotNull('empresa_id')->count());
            $this->assertSame(8, Negociacao::query()->whereNull('empresa_id')->count());
            $this->assertTrue(HistoricoNegociacao::query()->count() > 15);

            $verano = Negociacao::query()
                ->where('assunto', 'Compliance trabalhista')
                ->first();

            $this->assertNotNull($verano);
            $this->assertSame('Metalúrgica Verano S/A', $verano->empresa?->nome);
            $this->assertSame('B2B consultivo', $verano->funil->nome);
            $this->assertSame(4, $verano->historicos()->count());
            $this->assertNotNull(User::query()->where('email', 'diego.alencar@asfadvogados.adv.br')->first());
        });
    }

    public function test_guest_cannot_move_negociacao_to_another_stage(): void
    {
        $negociacao = Negociacao::factory()->create();
        $novaEtapa = EtapaFunil::factory()->for($negociacao->funil)->create();

        $this->patch($this->tenantUrl('negociacoes.update-etapa', ['negociacao' => $negociacao]), [
            'etapa_funil_id' => $novaEtapa->id,
        ])->assertRedirect($this->tenantUrl('login'));
    }

    public function test_authenticated_user_can_move_negociacao_to_another_stage(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create();
        $etapaAnteriorId = $negociacao->etapa_funil_id;
        $novaEtapa = EtapaFunil::factory()->for($negociacao->funil)->create();

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.index'))
            ->patch($this->tenantUrl('negociacoes.update-etapa', ['negociacao' => $negociacao]), [
                'etapa_funil_id' => $novaEtapa->id,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.index'));

        $negociacao->refresh();

        $this->assertSame($novaEtapa->id, $negociacao->etapa_funil_id);
        $this->assertNotSame($etapaAnteriorId, $negociacao->etapa_funil_id);
        $this->assertNotNull($negociacao->etapa_desde);
    }

    public function test_authenticated_user_can_cycle_funil_distribuicao_rule(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create([
            'distribuicao' => 'round robin por especialidade',
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.index'))
            ->patch($this->tenantUrl('negociacoes.distribuicao.cycle', ['funil' => $funil]))
            ->assertRedirect($this->tenantUrl('negociacoes.index'));

        $this->assertSame('round robin simples', $funil->fresh()->distribuicao);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.distribuicao.cycle', ['funil' => $funil]));

        $this->assertSame('por carga de trabalho', $funil->fresh()->distribuicao);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.distribuicao.cycle', ['funil' => $funil]));

        $this->assertSame('round robin por especialidade', $funil->fresh()->distribuicao);
    }

    public function test_guest_cannot_cycle_funil_distribuicao_rule(): void
    {
        $funil = Funil::factory()->create();

        $this->patch($this->tenantUrl('negociacoes.distribuicao.cycle', ['funil' => $funil]))
            ->assertRedirect($this->tenantUrl('login'));
    }
}
