<?php

namespace Tests\Feature;

use App\Enums\StatusTarefaNegociacao;
use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NegociacaoHistoricoTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_negociacao_appends_historico(): void
    {
        $user = User::factory()->create(['name' => 'Camila Moraes']);
        $funil = Funil::factory()->create(['nome' => 'B2B consultivo']);
        $etapa = EtapaFunil::factory()->for($funil)->create(['nome' => 'Qualificação']);
        $contato = Contato::factory()->pessoaFisica()->create(['nome' => 'Ana Zulu']);
        $canal = CanalContato::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('negociacoes.store'), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Compliance trabalhista',
                'valor' => 15000,
                'previsao_fechamento' => null,
                'proxima_tarefa' => 'Primeiro contato',
                'proxima_tarefa_em' => now()->toDateString(),
                'proxima_tarefa_hora' => '09:00',
            ])
            ->assertRedirect();

        $negociacao = Negociacao::query()->where('assunto', 'Compliance trabalhista')->first();

        $this->assertNotNull($negociacao);
        $this->assertDatabaseHas('historicos_negociacao', [
            'negociacao_id' => $negociacao->id,
            'tipo' => 'sys',
            'titulo' => 'Negociação criada',
            'autor' => 'Camila Moraes',
        ]);
        $this->assertDatabaseHas('historicos_negociacao', [
            'negociacao_id' => $negociacao->id,
            'tipo' => 'task',
            'titulo' => 'Tarefa cadastrada',
        ]);
    }

    public function test_updating_negociacao_appends_historico_with_changes(): void
    {
        $user = User::factory()->create(['name' => 'Rafael Prado']);
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
            'assunto' => 'Assunto original',
            'valor' => 10000,
        ]);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.update', ['negociacao' => $negociacao]), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Assunto atualizado',
                'valor' => 18500,
                'previsao_fechamento' => null,
            ])
            ->assertRedirect();

        $historico = HistoricoNegociacao::query()
            ->where('negociacao_id', $negociacao->id)
            ->where('titulo', 'Negociação atualizada')
            ->first();

        $this->assertNotNull($historico);
        $this->assertSame('sys', $historico->tipo);
        $this->assertSame('Rafael Prado', $historico->autor);
        $this->assertStringContainsString('Assunto:', $historico->descricao);
        $this->assertStringContainsString('Valor:', $historico->descricao);
    }

    public function test_moving_etapa_appends_historico(): void
    {
        $user = User::factory()->create(['name' => 'Letícia Bonfim']);
        $funil = Funil::factory()->create();
        $etapaAtual = EtapaFunil::factory()->for($funil)->create(['nome' => 'Triagem', 'ordem' => 1]);
        $etapaNova = EtapaFunil::factory()->for($funil)->create(['nome' => 'Proposta', 'ordem' => 2]);
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaAtual->id,
            'responsavel_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.update-etapa', ['negociacao' => $negociacao]), [
                'etapa_funil_id' => $etapaNova->id,
            ])
            ->assertRedirect();

        $historico = HistoricoNegociacao::query()
            ->where('negociacao_id', $negociacao->id)
            ->where('titulo', 'Etapa alterada')
            ->first();

        $this->assertNotNull($historico);
        $this->assertStringContainsString('Triagem', $historico->descricao);
        $this->assertStringContainsString('Proposta', $historico->descricao);
        $this->assertSame('Letícia Bonfim', $historico->autor);
    }

    public function test_tarefa_lifecycle_appends_historico(): void
    {
        $user = User::factory()->create(['name' => 'Diego Alencar']);
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post($this->tenantUrl('negociacoes.tarefas.store', ['negociacao' => $negociacao]), [
                'descricao' => 'Ligar para decisor',
                'data' => '2026-09-01',
                'hora' => '10:00',
                'status' => StatusTarefaNegociacao::Pendente->value,
            ])
            ->assertRedirect();

        $tarefa = TarefaNegociacao::query()->where('negociacao_id', $negociacao->id)->first();
        $this->assertNotNull($tarefa);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.tarefas.update', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]), [
                'descricao' => 'Ligar para decisor',
                'data' => '2026-09-01',
                'hora' => '10:00',
                'status' => StatusTarefaNegociacao::Concluida->value,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->delete($this->tenantUrl('negociacoes.tarefas.destroy', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]))
            ->assertRedirect();

        $titulos = HistoricoNegociacao::query()
            ->where('negociacao_id', $negociacao->id)
            ->where('tipo', 'task')
            ->pluck('titulo')
            ->all();

        $this->assertContains('Tarefa cadastrada', $titulos);
        $this->assertContains('Tarefa atualizada', $titulos);
        $this->assertContains('Tarefa removida', $titulos);
    }

    public function test_update_without_changes_does_not_append_historico(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'empresa_id' => null,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
            'assunto' => 'Sem mudança',
            'valor' => 5000,
            'previsao_fechamento' => null,
        ]);

        $antes = HistoricoNegociacao::query()->where('negociacao_id', $negociacao->id)->count();

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.update', ['negociacao' => $negociacao]), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Sem mudança',
                'valor' => 5000,
                'previsao_fechamento' => null,
            ])
            ->assertRedirect();

        $this->assertSame(
            $antes,
            HistoricoNegociacao::query()->where('negociacao_id', $negociacao->id)->count(),
        );
    }

    public function test_edit_page_lists_historicos_in_descending_date_order(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
        ]);

        HistoricoNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'titulo' => 'Mais antigo',
            'ocorrido_em' => now()->subDays(3),
        ]);
        HistoricoNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'titulo' => 'Mais recente',
            'ocorrido_em' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesEdit')
                ->has('negociacao.historicos', 2)
                ->where('negociacao.historicos.0.titulo', 'Mais recente')
                ->where('negociacao.historicos.1.titulo', 'Mais antigo'));
    }
}
