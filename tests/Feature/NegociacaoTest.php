<?php

namespace Tests\Feature;

use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
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

        $this->assertSame(2, Funil::query()->count());
        $this->assertSame(10, EtapaFunil::query()->count());
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
    }

    public function test_guest_cannot_move_negociacao_to_another_stage(): void
    {
        $negociacao = Negociacao::factory()->create();
        $novaEtapa = EtapaFunil::factory()->for($negociacao->funil)->create();

        $this->patch(route('negociacoes.update-etapa', $negociacao), [
            'etapa_funil_id' => $novaEtapa->id,
        ])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_move_negociacao_to_another_stage(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create();
        $etapaAnteriorId = $negociacao->etapa_funil_id;
        $novaEtapa = EtapaFunil::factory()->for($negociacao->funil)->create();

        $this->actingAs($user)
            ->from(route('negociacoes.index'))
            ->patch(route('negociacoes.update-etapa', $negociacao), [
                'etapa_funil_id' => $novaEtapa->id,
            ])
            ->assertRedirect(route('negociacoes.index'));

        $negociacao->refresh();

        $this->assertSame($novaEtapa->id, $negociacao->etapa_funil_id);
        $this->assertNotSame($etapaAnteriorId, $negociacao->etapa_funil_id);
        $this->assertNotNull($negociacao->etapa_desde);
    }

    public function test_cannot_move_negociacao_to_etapa_from_other_funil(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create();
        $etapaDeOutroFunil = EtapaFunil::factory()->create();

        $this->actingAs($user)
            ->from(route('negociacoes.index'))
            ->patch(route('negociacoes.update-etapa', $negociacao), [
                'etapa_funil_id' => $etapaDeOutroFunil->id,
            ])
            ->assertRedirect(route('negociacoes.index'))
            ->assertSessionHasErrors('etapa_funil_id');

        $this->assertSame(
            $negociacao->etapa_funil_id,
            $negociacao->fresh()->etapa_funil_id,
        );
    }
}
