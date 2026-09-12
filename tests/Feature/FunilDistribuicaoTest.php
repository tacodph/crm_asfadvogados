<?php

namespace Tests\Feature;

use App\Actions\Crm\DistribuirNegociacaoResponsavel;
use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\Setor;
use App\Models\StatusConflito;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FunilDistribuicaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_robin_simples_cycles_available_users(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        User::factory()->create([
            'name' => 'Carol ausente',
            'ausente_ate' => now()->addDays(3)->toDateString(),
        ]);

        $funil = Funil::factory()->create([
            'distribuicao' => Funil::DISTRIBUICAO_SIMPLES,
            'ultimo_responsavel_user_id' => null,
        ]);

        $distribuir = app(DistribuirNegociacaoResponsavel::class);

        $primeiro = $distribuir($funil);
        $segundo = $distribuir($funil->fresh());
        $terceiro = $distribuir($funil->fresh());

        $this->assertSame($alice->id, $primeiro->id);
        $this->assertSame($bob->id, $segundo->id);
        $this->assertSame($alice->id, $terceiro->id);
        $this->assertSame($alice->id, $funil->fresh()->ultimo_responsavel_user_id);
    }

    public function test_round_robin_por_especialidade_prefers_matching_users(): void
    {
        $setor = Setor::factory()->create(['slug' => 'software-b2b']);
        $especialista = User::factory()->create([
            'name' => 'Especialista',
            'especialidades' => ['software-b2b'],
        ]);
        User::factory()->create([
            'name' => 'Outro',
            'especialidades' => ['alimentos'],
        ]);

        $empresa = Empresa::factory()->create([
            'setor_id' => $setor->id,
            'status_conflito_id' => StatusConflito::factory()->create(['slug' => 'verificado'])->id,
        ]);

        $funil = Funil::factory()->create([
            'distribuicao' => Funil::DISTRIBUICAO_ESPECIALIDADE,
        ]);

        $escolhido = app(DistribuirNegociacaoResponsavel::class)($funil, $empresa);

        $this->assertSame($especialista->id, $escolhido->id);
    }

    public function test_por_carga_de_trabalho_picks_user_with_fewest_open_deals(): void
    {
        $leve = User::factory()->create(['name' => 'Carga leve']);
        $pesado = User::factory()->create(['name' => 'Carga pesada']);

        $funil = Funil::factory()->create([
            'distribuicao' => Funil::DISTRIBUICAO_CARGA,
        ]);
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();

        Negociacao::factory()->count(3)->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $pesado->id,
        ]);
        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $leve->id,
        ]);

        $escolhido = app(DistribuirNegociacaoResponsavel::class)($funil);

        $this->assertSame($leve->id, $escolhido->id);
    }

    public function test_pending_conflict_blocks_automatic_assignment(): void
    {
        User::factory()->create();
        $pendente = StatusConflito::factory()->create(['slug' => 'pendente']);
        $empresa = Empresa::factory()->create([
            'status_conflito_id' => $pendente->id,
        ]);
        $funil = Funil::factory()->create([
            'distribuicao' => Funil::DISTRIBUICAO_SIMPLES,
        ]);

        $this->expectException(ValidationException::class);

        app(DistribuirNegociacaoResponsavel::class)($funil, $empresa);
    }

    public function test_store_negociacao_auto_assigns_by_funil_rule(): void
    {
        $user = User::factory()->create();
        $candidato = User::factory()->create(['name' => 'Auto Assign']);
        $funil = Funil::factory()->create([
            'distribuicao' => Funil::DISTRIBUICAO_SIMPLES,
            'ultimo_responsavel_user_id' => null,
        ]);
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('negociacoes.store'), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => null,
                'assunto' => 'Distribuída automaticamente',
                'valor' => 5000,
            ])
            ->assertRedirect();

        $negociacao = Negociacao::query()->where('assunto', 'Distribuída automaticamente')->first();

        $this->assertNotNull($negociacao);
        $this->assertContains($negociacao->responsavel_user_id, [$user->id, $candidato->id]);
        $this->assertSame($negociacao->responsavel_user_id, $funil->fresh()->ultimo_responsavel_user_id);
    }

    public function test_store_negociacao_keeps_manual_responsavel_override(): void
    {
        $user = User::factory()->create();
        $manual = User::factory()->create(['name' => 'Manual']);
        $funil = Funil::factory()->create([
            'distribuicao' => Funil::DISTRIBUICAO_SIMPLES,
        ]);
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('negociacoes.store'), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $manual->id,
                'assunto' => 'Atribuição manual',
                'valor' => 1000,
            ])
            ->assertRedirect();

        $this->assertSame(
            $manual->id,
            Negociacao::query()->where('assunto', 'Atribuição manual')->value('responsavel_user_id'),
        );
    }

    public function test_cycling_distribuicao_rule_redistributes_open_deals(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $actor = User::factory()->create();

        $funil = Funil::factory()->create([
            'distribuicao' => Funil::DISTRIBUICAO_SIMPLES,
            'ultimo_responsavel_user_id' => null,
        ]);
        $etapaAberta = EtapaFunil::factory()->for($funil)->create(['ordem' => 1]);
        EtapaFunil::factory()->for($funil)->create(['ordem' => 2]);
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();

        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaAberta->id,
            'contato_id' => $contato->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $bob->id,
        ]);

        $this->actingAs($actor)
            ->from($this->tenantUrl('negociacoes.index'))
            ->patch($this->tenantUrl('negociacoes.distribuicao.cycle', ['funil' => $funil]))
            ->assertRedirect($this->tenantUrl('negociacoes.index'));

        // SIMPLES → próxima regra = CARGA; redistribui a quem está com menor carga (Alice).
        $this->assertSame(Funil::DISTRIBUICAO_CARGA, $funil->fresh()->distribuicao);
        $this->assertSame($alice->id, $negociacao->fresh()->responsavel_user_id);
    }
}
