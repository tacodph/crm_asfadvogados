<?php

namespace Tests\Feature;

use App\Enums\StatusTarefaNegociacao;
use App\Enums\TipoTarefaNegociacao;
use App\Models\Negociacao;
use App\Models\Role;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeuDiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_meu_dia(): void
    {
        $this->get($this->tenantUrl('meu-dia'))
            ->assertRedirect($this->tenantUrl('login'));
    }

    public function test_meu_dia_shows_only_the_users_own_agenda(): void
    {
        $eu = User::factory()->create();
        $colega = User::factory()->create();

        $this->tarefa($eu, 'Minha de hoje', '2026-11-04');
        $this->tarefa($eu, 'Minha atrasada', '2026-11-01');
        $this->tarefa($colega, 'Do colega', '2026-11-04');

        $this->actingAs($eu)
            ->withoutVite()
            ->get($this->tenantUrl('meu-dia'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/MeuDia')
                ->where('escopo', 'minhas')
                ->where('podeVerEquipe', false)
                ->where('agenda.data', '2026-11-04')
                ->has('agenda.followUpsHoje', 1)
                ->where('agenda.followUpsHoje.0.descricao', 'Minha de hoje')
                ->has('agenda.followUpsPendentes', 1)
                ->where('agenda.totais.followUpsPendentes', 1));
    }

    public function test_member_cannot_see_team_agenda(): void
    {
        $membro = User::factory()->create();

        $this->actingAs($membro)
            ->get($this->tenantUrl('meu-dia', ['escopo' => 'equipe']))
            ->assertForbidden();

        $this->actingAs($membro)
            ->get($this->tenantUrl('atividades.index', ['escopo' => 'equipe']))
            ->assertForbidden();
    }

    public function test_gerente_can_see_team_agenda(): void
    {
        $gerente = User::factory()->create(['role_id' => Role::idFor(Role::GERENTE)]);
        $colega = User::factory()->create();

        $this->tarefa($colega, 'Do colega', '2026-11-04');

        $this->actingAs($gerente)
            ->withoutVite()
            ->get($this->tenantUrl('meu-dia', ['escopo' => 'equipe']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('escopo', 'equipe')
                ->where('podeVerEquipe', true)
                ->where('agenda.followUpsHoje.0.descricao', 'Do colega'));
    }

    public function test_atividades_opens_on_team_view_for_gerente(): void
    {
        $gerente = User::factory()->create(['role_id' => Role::idFor(Role::GERENTE)]);
        $colega = User::factory()->create();

        $this->tarefa($colega, 'Do colega', '2026-11-04');

        $this->actingAs($gerente)
            ->withoutVite()
            ->get($this->tenantUrl('atividades.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('escopo', 'equipe')
                ->has('atividades', 1)
                ->where('atividades.0.descricao', 'Do colega'));

        $this->actingAs($gerente)
            ->withoutVite()
            ->get($this->tenantUrl('atividades.index', ['escopo' => 'minhas']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('escopo', 'minhas')
                ->has('atividades', 0));
    }

    public function test_concluir_marks_tarefa_done_and_records_contact(): void
    {
        $user = User::factory()->create();
        $tarefa = $this->tarefa($user, 'Ligar', '2026-11-04');

        $this->actingAs($user)
            ->from($this->tenantUrl('meu-dia'))
            ->patch($this->tenantUrl('tarefas.concluir', ['tarefa' => $tarefa]))
            ->assertRedirect($this->tenantUrl('meu-dia'));

        $tarefa->refresh();

        $this->assertSame(StatusTarefaNegociacao::Concluida, $tarefa->status);
        $this->assertNotNull($tarefa->concluida_em);
        $this->assertNotNull($tarefa->negociacao->ultimo_contato_em);
        $this->assertNull($tarefa->negociacao->proxima_tarefa);
    }

    public function test_adiar_moves_tarefa_and_resets_reminder(): void
    {
        $user = User::factory()->create();
        $tarefa = $this->tarefa($user, 'Retomar', '2026-11-03');
        $tarefa->forceFill(['lembrete_enviado_em' => now()])->save();

        $this->actingAs($user)
            ->from($this->tenantUrl('meu-dia'))
            ->patch($this->tenantUrl('tarefas.adiar', ['tarefa' => $tarefa]), [
                'data' => '2026-11-07',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->tenantUrl('meu-dia'));

        $tarefa->refresh();

        $this->assertSame('2026-11-07', $tarefa->data->toDateString());
        $this->assertSame('09:00', $tarefa->hora?->format('H:i'));
        $this->assertNull($tarefa->lembrete_enviado_em);
        $this->assertSame('2026-11-07', $tarefa->negociacao->refresh()->proxima_tarefa_em?->toDateString());
    }

    public function test_adiar_rejects_a_past_date(): void
    {
        $user = User::factory()->create();
        $tarefa = $this->tarefa($user, 'Retomar', '2026-11-03');

        $this->actingAs($user)
            ->patch($this->tenantUrl('tarefas.adiar', ['tarefa' => $tarefa]), [
                'data' => '2026-11-01',
            ])
            ->assertSessionHasErrors('data');

        $this->assertSame('2026-11-03', $tarefa->refresh()->data->toDateString());
    }

    public function test_tarefa_from_another_tenant_cannot_be_concluded(): void
    {
        $user = User::factory()->create();
        $alheia = $this->asTenant($this->createTenant(), function (): TarefaNegociacao {
            return $this->tarefa(User::factory()->create(), 'Outro tenant', '2026-11-04');
        });

        $this->actingAs($user)
            ->patch($this->tenantUrl('tarefas.concluir', ['tarefa' => $alheia->id]))
            ->assertNotFound();
    }

    public function test_atividades_lists_real_tarefas_with_filters(): void
    {
        $user = User::factory()->create();
        $colega = User::factory()->create();

        $this->tarefa($user, 'Atrasada', '2026-11-01');
        $this->tarefa($user, 'Hoje', '2026-11-04');
        $this->tarefa($user, 'Feita', '2026-11-02', StatusTarefaNegociacao::Concluida);
        $this->tarefa($colega, 'Do colega', '2026-11-04');

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('atividades.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Atividades')
                ->where('filtro', 'atuais')
                ->has('atividades', 2)
                ->where('atividades.0.descricao', 'Atrasada')
                ->where('kpis.pendentes', 2)
                ->where('kpis.atrasadas', 1)
                ->where('kpis.hoje', 1)
                ->where('kpis.concluidas', 1));

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('atividades.index', ['filtro' => 'concluidas']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('atividades', 1)
                ->where('atividades.0.descricao', 'Feita'));
    }

    private function tarefa(
        User $responsavel,
        string $descricao,
        string $data,
        StatusTarefaNegociacao $status = StatusTarefaNegociacao::Pendente,
    ): TarefaNegociacao {
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $responsavel->id,
            'proxima_tarefa' => null,
            'proxima_tarefa_em' => null,
            'proxima_tarefa_hora' => null,
        ]);

        return TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'responsavel_user_id' => $responsavel->id,
            'tipo' => TipoTarefaNegociacao::FollowUp,
            'descricao' => $descricao,
            'data' => $data,
            'status' => $status,
            'concluida_em' => $status === StatusTarefaNegociacao::Concluida ? now() : null,
        ]);
    }
}
