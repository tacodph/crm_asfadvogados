<?php

namespace Tests\Feature;

use App\Actions\Crm\EnsureEtapaAtendimentosEncerrados;
use App\Actions\Crm\MoverNegociacoesEncerradasParaEtapa;
use App\Enums\EtapaFunilResultado;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\StatusAtendimento;
use App\Models\StatusQualificacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtapaAtendimentosEncerradosTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_etapa_vermelha_perdida_sem_alterar_ganho(): void
    {
        $funil = Funil::factory()->create();
        $aberta = EtapaFunil::factory()->for($funil)->create(['ordem' => 1, 'nome' => 'Triagem']);
        $ganho = EtapaFunil::factory()->for($funil)->ganho()->create(['ordem' => 2]);

        $etapa = app(EnsureEtapaAtendimentosEncerrados::class)($funil);

        $this->assertSame('Atendimentos encerrados', $etapa->nome);
        $this->assertSame(EtapaFunilResultado::Perdido, $etapa->resultado);
        $this->assertSame('#9B3B2F', $etapa->cor_fundo);
        $this->assertSame(3, $etapa->ordem);
        $this->assertSame(EtapaFunilResultado::Ganho, $ganho->fresh()->resultado);
        $this->assertSame(EtapaFunilResultado::Aberta, $aberta->fresh()->resultado);
    }

    public function test_move_leads_encerrados_e_desqualificados_para_etapa_vermelha(): void
    {
        $funil = Funil::factory()->create();
        $triagem = EtapaFunil::factory()->for($funil)->create(['ordem' => 1, 'nome' => 'Triagem']);
        $fechamento = EtapaFunil::factory()->for($funil)->ganho()->create(['ordem' => 2]);

        $encerrado = StatusAtendimento::factory()->create([
            'slug' => 'encerrado',
            'nome' => 'Encerrado',
        ]);
        $contrato = StatusAtendimento::factory()->create([
            'slug' => 'contrato-fechado',
            'nome' => 'Contrato Fechado',
        ]);
        $desqualificado = StatusQualificacao::factory()->create([
            'slug' => 'desqualificado',
            'nome' => 'Desqualificado',
        ]);
        $qualificado = StatusQualificacao::factory()->create([
            'slug' => 'qualificado',
            'nome' => 'Qualificado',
        ]);

        $user = User::factory()->create();

        $moverEncerrado = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $triagem->id,
            'responsavel_user_id' => $user->id,
            'status_atendimento_id' => $encerrado->id,
            'status_qualificacao_id' => $desqualificado->id,
            'concluida_em' => null,
        ]);

        $moverQualificadoEncerrado = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $triagem->id,
            'responsavel_user_id' => $user->id,
            'status_atendimento_id' => $encerrado->id,
            'status_qualificacao_id' => $qualificado->id,
            'concluida_em' => null,
        ]);

        $manterContrato = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $fechamento->id,
            'responsavel_user_id' => $user->id,
            'status_atendimento_id' => $contrato->id,
            'status_qualificacao_id' => $qualificado->id,
            'concluida_em' => null,
        ]);

        $resultado = app(MoverNegociacoesEncerradasParaEtapa::class)($funil);

        $this->assertSame(2, $resultado['movidas']);
        $this->assertSame($resultado['etapa_id'], $moverEncerrado->fresh()->etapa_funil_id);
        $this->assertSame($resultado['etapa_id'], $moverQualificadoEncerrado->fresh()->etapa_funil_id);
        $this->assertSame($fechamento->id, $manterContrato->fresh()->etapa_funil_id);
        $this->assertNull($moverEncerrado->fresh()->concluida_em);

        $etapaPerdida = EtapaFunil::query()->findOrFail($resultado['etapa_id']);
        $this->assertTrue($etapaPerdida->isPerdido());
        $this->assertSame('#9B3B2F', $etapaPerdida->cor_fundo);
    }

    public function test_nao_marca_concluida_em_ao_entrar_na_etapa_perdida(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $aberta = EtapaFunil::factory()->for($funil)->create(['ordem' => 1]);
        EtapaFunil::factory()->for($funil)->ganho()->create(['ordem' => 2]);
        $perdida = EtapaFunil::factory()->for($funil)->perdido()->create(['ordem' => 3]);

        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $aberta->id,
            'responsavel_user_id' => $user->id,
            'concluida_em' => null,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.index'))
            ->patch($this->tenantUrl('negociacoes.update-etapa', ['negociacao' => $negociacao]), [
                'etapa_funil_id' => $perdida->id,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.index'));

        $this->assertNull($negociacao->fresh()->concluida_em);
        $this->assertSame($perdida->id, $negociacao->fresh()->etapa_funil_id);
    }
}
