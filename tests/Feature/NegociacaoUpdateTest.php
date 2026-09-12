<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\StatusAtendimento;
use App\Models\StatusQualificacao;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NegociacaoUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_edit_a_negociacao(): void
    {
        $negociacao = Negociacao::factory()->create();

        $this->get($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->assertRedirect($this->tenantUrl('login'));
    }

    public function test_guest_cannot_update_a_negociacao(): void
    {
        $negociacao = Negociacao::factory()->create();

        $this->patch($this->tenantUrl('negociacoes.update', ['negociacao' => $negociacao]), [
            'assunto' => 'Assunto alterado',
        ])->assertRedirect($this->tenantUrl('login'));
    }

    public function test_edit_page_loads_when_tenant_is_resolved_from_auth_user(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'assunto' => 'Negociação com tenant limpo',
            'responsavel_user_id' => $user->id,
        ]);

        app(CurrentTenant::class)->set(null);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('negociacoes.edit', $negociacao))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesEdit')
                ->where('negociacao.id', $negociacao->id)
                ->where('negociacao.assunto', 'Negociação com tenant limpo'));
    }

    public function test_edit_page_loads_negociacao_with_options(): void
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
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
            'assunto' => 'Contrato societário',
            'valor' => 25000,
        ]);

        HistoricoNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'tipo' => 'sys',
            'titulo' => 'Negociação criada',
            'descricao' => 'Negociação criada no funil.',
            'autor' => $user->name,
            'ocorrido_em' => now(),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesEdit')
                ->where('negociacao.id', $negociacao->id)
                ->where('negociacao.assunto', 'Contrato societário')
                ->where('negociacao.funil_id', $funil->id)
                ->where('negociacao.etapa_funil_id', $etapa->id)
                ->where('negociacao.contato_id', $contato->id)
                ->has('negociacao.historicos', 1)
                ->where('negociacao.historicos.0.titulo', 'Negociação criada')
                ->has('resumo.campos', 5)
                ->where('resumo.canal', fn ($canal) => is_string($canal) && $canal !== '')
                ->where('resumo.responsavel', $user->name)
                ->has('opcoes.funis')
                ->has('opcoes.contatos')
                ->has('opcoes.empresas')
                ->has('opcoes.canais')
                ->has('opcoes.responsaveis')
                ->has('opcoes.statusAtendimentos')
                ->has('opcoes.statusQualificacoes'));
    }

    public function test_authenticated_user_can_update_a_negociacao_and_stay_on_edit(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $novaEtapa = EtapaFunil::factory()->for($funil)->create(['ordem' => 2]);
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();
        $statusAtendimento = StatusAtendimento::factory()->create([
            'slug' => 'encerrado',
            'nome' => 'Encerrado',
        ]);
        $statusQualificacao = StatusQualificacao::factory()->create([
            'slug' => 'desqualificado',
            'nome' => 'Desqualificado',
        ]);
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
            'assunto' => 'Assunto original',
            'valor' => 10000,
            'etapa_desde' => now()->subDays(3),
            'status_atendimento_id' => null,
            'status_qualificacao_id' => null,
        ]);

        $etapaDesdeAntes = $negociacao->etapa_desde?->toDateTimeString();

        $response = $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->patch($this->tenantUrl('negociacoes.update', ['negociacao' => $negociacao]), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $novaEtapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'status_atendimento_id' => $statusAtendimento->id,
                'status_qualificacao_id' => $statusQualificacao->id,
                'motivo_desqualificacao' => 'Contato por engano',
                'continuidade_atendimento' => 'Engajamento contínuo',
                'observacoes_complementares' => 'Lead importado da planilha',
                'responsavel_user_id' => $user->id,
                'assunto' => 'Assunto atualizado',
                'valor' => 18500,
                'previsao_fechamento' => now()->addMonth()->toDateString(),
            ]);

        $response->assertRedirect($this->tenantUrl('negociacoes.edit', [
            'negociacao' => $negociacao,
        ]));

        $negociacao->refresh();

        $this->assertSame('Assunto atualizado', $negociacao->assunto);
        $this->assertSame('18500.00', (string) $negociacao->valor);
        $this->assertSame($novaEtapa->id, $negociacao->etapa_funil_id);
        $this->assertSame($statusAtendimento->id, $negociacao->status_atendimento_id);
        $this->assertSame($statusQualificacao->id, $negociacao->status_qualificacao_id);
        $this->assertSame('Contato por engano', $negociacao->motivo_desqualificacao);
        $this->assertSame('Engajamento contínuo', $negociacao->continuidade_atendimento);
        $this->assertSame('Lead importado da planilha', $negociacao->observacoes_complementares);
        $this->assertNotSame($etapaDesdeAntes, $negociacao->etapa_desde?->toDateTimeString());
    }

    public function test_update_keeps_etapa_desde_when_etapa_unchanged(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();
        $etapaDesde = now()->subDays(5)->startOfSecond();
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
            'assunto' => 'Sem mudança de etapa',
            'valor' => 5000,
            'etapa_desde' => $etapaDesde,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->patch($this->tenantUrl('negociacoes.update', ['negociacao' => $negociacao]), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Assunto só mudou',
                'valor' => 5000,
                'previsao_fechamento' => null,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.edit', [
                'negociacao' => $negociacao,
            ]));

        $negociacao->refresh();

        $this->assertSame('Assunto só mudou', $negociacao->assunto);
        $this->assertTrue($negociacao->etapa_desde?->equalTo($etapaDesde));
    }

    public function test_update_rejects_etapa_from_another_funil(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $etapaDeOutroFunil = EtapaFunil::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->patch($this->tenantUrl('negociacoes.update', ['negociacao' => $negociacao]), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapaDeOutroFunil->id,
                'contato_id' => $contato->id,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Etapa inválida',
                'valor' => 1000,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->assertSessionHasErrors(['etapa_funil_id']);
    }
}
