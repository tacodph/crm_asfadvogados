<?php

namespace Tests\Feature;

use App\Actions\Crm\BuildAgendaDoDia;
use App\Enums\StatusTarefaNegociacao;
use App\Enums\TipoTarefaNegociacao;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BuildAgendaDoDiaTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $agora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agora = Carbon::parse('2026-11-03 10:00:00');
    }

    public function test_agrupa_tarefas_abertas_do_responsavel(): void
    {
        $user = User::factory()->create();
        $negociacao = $this->negociacaoAberta($user);

        $this->tarefa($negociacao, $user, TipoTarefaNegociacao::FollowUp, '2026-11-01', 'Retomar proposta');
        $this->tarefa($negociacao, $user, TipoTarefaNegociacao::FollowUp, '2026-11-03', 'Follow-up de hoje', '14:00');
        $this->tarefa($negociacao, $user, TipoTarefaNegociacao::Ligacao, '2026-11-02', 'Ligação atrasada');
        $this->tarefa($negociacao, $user, TipoTarefaNegociacao::Reuniao, '2026-11-03', 'Reunião com sócio', '09:00');
        $this->tarefa($negociacao, $user, TipoTarefaNegociacao::Tarefa, '2026-11-03', 'Enviar contrato');
        $this->tarefa($negociacao, $user, TipoTarefaNegociacao::FollowUp, '2026-11-04', 'Follow-up de amanhã');
        $this->tarefa($negociacao, $user, TipoTarefaNegociacao::FollowUp, '2026-11-01', 'Já concluído', status: StatusTarefaNegociacao::Concluida);

        $agenda = app(BuildAgendaDoDia::class)($user, $this->agora);

        $this->assertSame('2026-11-03', $agenda['data']);
        $this->assertSame(['Retomar proposta'], array_column($agenda['followUpsPendentes'], 'descricao'));
        $this->assertSame(2, $agenda['followUpsPendentes'][0]['diasAtraso']);
        $this->assertSame(['Follow-up de hoje'], array_column($agenda['followUpsHoje'], 'descricao'));
        $this->assertFalse($agenda['followUpsHoje'][0]['horarioPassou']);
        $this->assertSame(['Ligação atrasada'], array_column($agenda['atrasadas'], 'descricao'));
        $this->assertSame(['Reunião com sócio'], array_column($agenda['reunioesHoje'], 'descricao'));
        $this->assertTrue($agenda['reunioesHoje'][0]['horarioPassou']);
        $this->assertSame(['Enviar contrato'], array_column($agenda['demaisHoje'], 'descricao'));
        $this->assertSame($negociacao->id, $agenda['followUpsHoje'][0]['negociacaoId']);
        $this->assertSame([], $agenda['semProximoPasso']);
        $this->assertSame(1, $agenda['totais']['followUpsPendentes']);
        $this->assertSame(0, $agenda['totais']['semProximoPasso']);
    }

    public function test_agenda_pessoal_ignora_tarefas_de_outro_responsavel_e_equipe_inclui(): void
    {
        $eu = User::factory()->create();
        $colega = User::factory()->create();

        $this->tarefa($this->negociacaoAberta($eu), $eu, TipoTarefaNegociacao::FollowUp, '2026-11-03', 'Minha');
        $this->tarefa($this->negociacaoAberta($colega), $colega, TipoTarefaNegociacao::FollowUp, '2026-11-03', 'Do colega');

        $minha = app(BuildAgendaDoDia::class)($eu, $this->agora);
        $equipe = app(BuildAgendaDoDia::class)(null, $this->agora);

        $this->assertSame(['Minha'], array_column($minha['followUpsHoje'], 'descricao'));
        $this->assertEqualsCanonicalizing(['Minha', 'Do colega'], array_column($equipe['followUpsHoje'], 'descricao'));
    }

    public function test_negociacao_aberta_sem_tarefa_aberta_entra_como_sem_proximo_passo(): void
    {
        $user = User::factory()->create();
        $semTarefa = $this->negociacaoAberta($user, ['ultimo_contato_em' => '2026-10-29 16:00:00']);
        $comTarefaConcluida = $this->negociacaoAberta($user);
        $this->tarefa($comTarefaConcluida, $user, TipoTarefaNegociacao::FollowUp, '2026-11-01', 'Feito', status: StatusTarefaNegociacao::Concluida);

        $agenda = app(BuildAgendaDoDia::class)($user, $this->agora);

        $this->assertEqualsCanonicalizing(
            [$semTarefa->id, $comTarefaConcluida->id],
            array_column($agenda['semProximoPasso'], 'negociacaoId'),
        );

        $item = collect($agenda['semProximoPasso'])->firstWhere('negociacaoId', $semTarefa->id);
        $this->assertSame(5, $item['diasSemContato']);
    }

    public function test_negociacoes_ganhas_ou_perdidas_nao_aparecem(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();

        foreach ([EtapaFunil::factory()->ganho(), EtapaFunil::factory()->perdido()] as $etapaFactory) {
            $etapa = $etapaFactory->create(['funil_id' => $funil->id]);
            $negociacao = Negociacao::factory()->create([
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'responsavel_user_id' => $user->id,
            ]);
            $this->tarefa($negociacao, $user, TipoTarefaNegociacao::FollowUp, '2026-11-01', 'Não deve aparecer');
        }

        $agenda = app(BuildAgendaDoDia::class)($user, $this->agora);

        $this->assertSame([], $agenda['followUpsPendentes']);
        $this->assertSame([], $agenda['semProximoPasso']);
    }

    public function test_tarefas_de_outro_tenant_nao_aparecem(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, function (): void {
            $alheio = User::factory()->create();
            $this->tarefa($this->negociacaoAberta($alheio), $alheio, TipoTarefaNegociacao::FollowUp, '2026-11-03', 'Outro tenant');
        });

        $agenda = app(BuildAgendaDoDia::class)(null, $this->agora);

        $this->assertSame([], $agenda['followUpsHoje']);
        $this->assertSame([], $agenda['semProximoPasso']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function negociacaoAberta(User $responsavel, array $atributos = []): Negociacao
    {
        return Negociacao::factory()->create([
            'responsavel_user_id' => $responsavel->id,
            ...$atributos,
        ]);
    }

    private function tarefa(
        Negociacao $negociacao,
        User $responsavel,
        TipoTarefaNegociacao $tipo,
        string $data,
        string $descricao,
        ?string $hora = null,
        StatusTarefaNegociacao $status = StatusTarefaNegociacao::Pendente,
    ): TarefaNegociacao {
        return TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'responsavel_user_id' => $responsavel->id,
            'tipo' => $tipo,
            'data' => $data,
            'hora' => $hora,
            'descricao' => $descricao,
            'status' => $status,
            'concluida_em' => $status === StatusTarefaNegociacao::Concluida ? now() : null,
        ]);
    }
}
