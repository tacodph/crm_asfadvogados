<?php

namespace Tests\Feature;

use App\Enums\StatusTarefaNegociacao;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegociacaoConclusaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_concluding_tarefa_sets_concluida_em(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
        ]);
        $tarefa = TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Ligar para decisor',
            'data' => '2026-08-21',
            'hora' => '10:00',
            'status' => StatusTarefaNegociacao::Pendente,
            'criado_por_user_id' => $user->id,
            'concluida_em' => null,
        ]);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.tarefas.update', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]), [
                'descricao' => 'Ligar para decisor',
                'data' => '2026-08-21',
                'hora' => '10:00',
                'status' => StatusTarefaNegociacao::Concluida->value,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]));

        $tarefa->refresh();

        $this->assertSame(StatusTarefaNegociacao::Concluida, $tarefa->status);
        $this->assertNotNull($tarefa->concluida_em);
    }

    public function test_reopening_tarefa_clears_concluida_em(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
        ]);
        $tarefa = TarefaNegociacao::factory()->concluida()->create([
            'negociacao_id' => $negociacao->id,
            'criado_por_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.tarefas.update', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]), [
                'descricao' => $tarefa->descricao,
                'data' => $tarefa->data->format('Y-m-d'),
                'hora' => $tarefa->hora?->format('H:i') ?? '10:00',
                'status' => StatusTarefaNegociacao::Pendente->value,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]));

        $this->assertNull($tarefa->fresh()->concluida_em);
    }

    public function test_moving_to_final_stage_sets_negociacao_concluida_em(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapaInicial = EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Diagnóstico',
            'ordem' => 1,
        ]);
        $etapaFinal = EtapaFunil::factory()->for($funil)->ganho()->create([
            'ordem' => 2,
            'campos' => ['contrato assinado'],
        ]);

        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaInicial->id,
            'responsavel_user_id' => $user->id,
            'concluida_em' => null,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.index'))
            ->patch($this->tenantUrl('negociacoes.update-etapa', ['negociacao' => $negociacao]), [
                'etapa_funil_id' => $etapaFinal->id,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.index'));

        $negociacao->refresh();

        $this->assertSame($etapaFinal->id, $negociacao->etapa_funil_id);
        $this->assertNotNull($negociacao->concluida_em);
    }

    public function test_leaving_final_stage_clears_negociacao_concluida_em(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapaInicial = EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Diagnóstico',
            'ordem' => 1,
        ]);
        $etapaFinal = EtapaFunil::factory()->for($funil)->ganho()->create([
            'ordem' => 2,
        ]);

        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaFinal->id,
            'responsavel_user_id' => $user->id,
            'concluida_em' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.index'))
            ->patch($this->tenantUrl('negociacoes.update-etapa', ['negociacao' => $negociacao]), [
                'etapa_funil_id' => $etapaInicial->id,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.index'));

        $this->assertNull($negociacao->fresh()->concluida_em);
    }
}
