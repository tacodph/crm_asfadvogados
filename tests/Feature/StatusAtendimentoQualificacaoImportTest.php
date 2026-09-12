<?php

namespace Tests\Feature;

use App\Actions\Tenancy\SeedDefaultCatalogsForTenant;
use App\Models\Contato;
use App\Models\Negociacao;
use App\Models\StatusAtendimento;
use App\Models\StatusQualificacao;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StatusAtendimentoQualificacaoImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_updates_negociacao_statuses_from_planilha_json(): void
    {
        $tenant = Tenant::query()->where('slug', 'asfadvogados')->first()
            ?? Tenant::factory()->create(['slug' => 'asfadvogados', 'name' => 'ASF']);

        app(SeedDefaultCatalogsForTenant::class)($tenant);

        app(CurrentTenant::class)->runAs($tenant, function () use ($tenant): void {
            $user = User::factory()->create();
            $contato = Contato::factory()->pessoaFisica()->create([
                'nome' => 'Samuel',
                'telefone' => '6296124175',
            ]);
            $negociacao = Negociacao::factory()->create([
                'contato_id' => $contato->id,
                'responsavel_user_id' => $user->id,
                'status_atendimento_id' => null,
                'status_qualificacao_id' => null,
            ]);

            $json = database_path('data/qualificacao-atendimentos-agosto-test.json');
            File::put($json, json_encode([
                [
                    'nome' => 'Samuel',
                    'ddd' => '62',
                    'whatsapp' => '96124175',
                    'status_atendimento' => 'Encerrado',
                    'status_qualificacao' => 'Desqualificado',
                    'motivo_desqualificacao' => 'Contato por engano',
                    'continuidade' => 'Engajamento Contínuo',
                    'observacoes' => 'Cliente era de outro bloco',
                ],
            ], JSON_THROW_ON_ERROR));

            $this->artisan('crm:sync-qualificacao-planilha-status', [
                '--tenant' => $tenant->slug,
                '--json' => $json,
            ])->assertSuccessful();

            $negociacao->refresh();

            $this->assertNotNull($negociacao->status_atendimento_id);
            $this->assertNotNull($negociacao->status_qualificacao_id);
            $this->assertSame(
                'Encerrado',
                StatusAtendimento::query()->find($negociacao->status_atendimento_id)?->nome,
            );
            $this->assertSame(
                'Desqualificado',
                StatusQualificacao::query()->find($negociacao->status_qualificacao_id)?->nome,
            );
            $this->assertSame('Contato por engano', $negociacao->motivo_desqualificacao);
            $this->assertSame('Engajamento Contínuo', $negociacao->continuidade_atendimento);
            $this->assertSame('Cliente era de outro bloco', $negociacao->observacoes_complementares);

            File::delete($json);
        });
    }

    public function test_negociacoes_index_exposes_status_atendimento_on_cards(): void
    {
        $user = User::factory()->create();
        $status = StatusAtendimento::factory()->create([
            'nome' => 'Retorno a agendar',
            'slug' => 'retorno-a-agendar',
            'cor_fundo' => '#DBEAFE',
            'cor_texto' => '#1D4ED8',
        ]);

        Negociacao::factory()->create([
            'assunto' => 'Lead com status',
            'responsavel_user_id' => $user->id,
            'status_atendimento_id' => $status->id,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('crm/Negociacoes')
                ->where('negociacoes.0.statusAtendimento', 'Retorno a agendar')
                ->where('negociacoes.0.statusAtendimentoCor', '#1D4ED8'));
    }
}
