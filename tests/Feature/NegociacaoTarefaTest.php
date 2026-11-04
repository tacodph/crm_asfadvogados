<?php

namespace Tests\Feature;

use App\Enums\StatusTarefaNegociacao;
use App\Enums\TipoTarefaNegociacao;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NegociacaoTarefaTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_lists_tarefas(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
            'proxima_tarefa' => null,
            'proxima_tarefa_em' => null,
            'proxima_tarefa_hora' => null,
        ]);

        TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Ligar para decisor',
            'data' => '2026-08-28',
            'hora' => '10:30',
            'status' => StatusTarefaNegociacao::Pendente,
            'criado_por_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesEdit')
                ->has('negociacao.tarefas', 1)
                ->where('negociacao.tarefas.0.descricao', 'Ligar para decisor')
                ->where('negociacao.tarefas.0.status', 'pendente')
                ->where('negociacao.tarefas.0.tipo', 'follow_up')
                ->has('opcoes.tiposTarefa', 4)
                ->has('opcoes.statusTarefa', 4));
    }

    public function test_authenticated_user_can_store_a_tarefa(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
            'proxima_tarefa' => null,
            'proxima_tarefa_em' => null,
            'proxima_tarefa_hora' => null,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->post($this->tenantUrl('negociacoes.tarefas.store', ['negociacao' => $negociacao]), [
                'descricao' => 'Enviar proposta revisada',
                'data' => '2026-09-01',
                'hora' => '14:00',
                'status' => StatusTarefaNegociacao::Pendente->value,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]));

        $this->assertDatabaseHas('tarefas_negociacao', [
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Enviar proposta revisada',
            'status' => 'pendente',
        ]);

        $tarefa = TarefaNegociacao::query()
            ->where('negociacao_id', $negociacao->id)
            ->first();

        $this->assertNotNull($tarefa);
        $this->assertSame('2026-09-01', $tarefa->data->format('Y-m-d'));
        $this->assertSame('14:00', $tarefa->hora?->format('H:i'));

        $negociacao->refresh();

        $this->assertSame('Enviar proposta revisada', $negociacao->proxima_tarefa);
        $this->assertSame('2026-09-01', $negociacao->proxima_tarefa_em?->format('Y-m-d'));
        $this->assertSame('14:00', $negociacao->proxima_tarefa_hora?->format('H:i'));
    }

    public function test_authenticated_user_can_update_a_tarefa_and_sync_proxima(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
        ]);
        $tarefa = TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Tarefa antiga',
            'data' => '2026-08-20',
            'hora' => '09:00',
            'status' => StatusTarefaNegociacao::Pendente,
            'criado_por_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]))
            ->patch($this->tenantUrl('negociacoes.tarefas.update', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]), [
                'descricao' => 'Tarefa atualizada',
                'data' => '2026-08-25',
                'hora' => '11:15',
                'status' => StatusTarefaNegociacao::EmAndamento->value,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]));

        $tarefa->refresh();
        $negociacao->refresh();

        $this->assertSame('Tarefa atualizada', $tarefa->descricao);
        $this->assertSame(StatusTarefaNegociacao::EmAndamento, $tarefa->status);
        $this->assertSame('Tarefa atualizada', $negociacao->proxima_tarefa);
    }

    public function test_concluding_tarefa_clears_proxima_when_no_open_tasks_remain(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
        ]);
        $tarefa = TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Única aberta',
            'data' => '2026-08-21',
            'hora' => '10:00',
            'status' => StatusTarefaNegociacao::Pendente,
            'criado_por_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.tarefas.update', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]), [
                'descricao' => 'Única aberta',
                'data' => '2026-08-21',
                'hora' => '10:00',
                'status' => StatusTarefaNegociacao::Concluida->value,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]));

        $negociacao->refresh();

        $this->assertNull($negociacao->proxima_tarefa);
        $this->assertNull($negociacao->proxima_tarefa_em);
        $this->assertNull($negociacao->proxima_tarefa_hora);
    }

    public function test_authenticated_user_can_delete_a_tarefa(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
        ]);
        $tarefa = TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'criado_por_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete($this->tenantUrl('negociacoes.tarefas.destroy', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]))
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $negociacao]));

        $this->assertDatabaseMissing('tarefas_negociacao', [
            'id' => $tarefa->id,
        ]);
    }

    public function test_guest_cannot_store_a_tarefa(): void
    {
        $negociacao = Negociacao::factory()->create();

        $this->post($this->tenantUrl('negociacoes.tarefas.store', ['negociacao' => $negociacao]), [
            'descricao' => 'Sem auth',
            'data' => '2026-08-22',
            'status' => StatusTarefaNegociacao::Pendente->value,
        ])->assertRedirect($this->tenantUrl('login'));
    }

    public function test_store_defaults_tipo_to_follow_up_and_responsavel_to_negociacao_owner(): void
    {
        $user = User::factory()->create();
        $dono = User::factory()->create();
        $negociacao = Negociacao::factory()->create(['responsavel_user_id' => $dono->id]);

        $this->actingAs($user)
            ->post($this->tenantUrl('negociacoes.tarefas.store', ['negociacao' => $negociacao]), [
                'descricao' => 'Retomar contato',
                'data' => '2026-11-05',
                'status' => StatusTarefaNegociacao::Pendente->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tarefas_negociacao', [
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Retomar contato',
            'tipo' => 'follow_up',
            'responsavel_user_id' => $dono->id,
            'criado_por_user_id' => $user->id,
        ]);
    }

    public function test_store_accepts_tipo_and_responsavel(): void
    {
        $user = User::factory()->create();
        $colega = User::factory()->create();
        $negociacao = Negociacao::factory()->create(['responsavel_user_id' => $user->id]);

        $this->actingAs($user)
            ->post($this->tenantUrl('negociacoes.tarefas.store', ['negociacao' => $negociacao]), [
                'descricao' => 'Reunião de diagnóstico',
                'tipo' => TipoTarefaNegociacao::Reuniao->value,
                'responsavel_user_id' => $colega->id,
                'data' => '2026-11-05',
                'hora' => '15:00',
                'status' => StatusTarefaNegociacao::Pendente->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tarefas_negociacao', [
            'descricao' => 'Reunião de diagnóstico',
            'tipo' => 'reuniao',
            'responsavel_user_id' => $colega->id,
        ]);
    }

    public function test_store_rejects_responsavel_from_another_tenant(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create(['responsavel_user_id' => $user->id]);
        $alheio = $this->asTenant($this->createTenant(), fn () => User::factory()->create());

        $this->actingAs($user)
            ->post($this->tenantUrl('negociacoes.tarefas.store', ['negociacao' => $negociacao]), [
                'descricao' => 'Tarefa',
                'responsavel_user_id' => $alheio->id,
                'data' => '2026-11-05',
                'status' => StatusTarefaNegociacao::Pendente->value,
            ])
            ->assertSessionHasErrors('responsavel_user_id');
    }

    public function test_concluding_a_follow_up_records_contact_and_schedules_the_next_one(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
            'ultimo_contato_em' => null,
        ]);
        $tarefa = TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Ligar para cliente',
            'tipo' => TipoTarefaNegociacao::FollowUp,
            'responsavel_user_id' => $user->id,
            'data' => '2026-11-03',
        ]);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.tarefas.update', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]), [
                'descricao' => 'Ligar para cliente',
                'tipo' => TipoTarefaNegociacao::FollowUp->value,
                'data' => '2026-11-03',
                'status' => StatusTarefaNegociacao::Concluida->value,
                'proximo_follow_up_data' => '2026-11-10',
                'proximo_follow_up_hora' => '10:00',
                'proximo_follow_up_descricao' => 'Cobrar retorno da proposta',
            ])
            ->assertSessionHasNoErrors();

        $negociacao->refresh();

        $this->assertNotNull($negociacao->ultimo_contato_em);
        $this->assertSame('Cobrar retorno da proposta', $negociacao->proxima_tarefa);
        $this->assertSame('2026-11-10', $negociacao->proxima_tarefa_em?->format('Y-m-d'));
        $this->assertDatabaseHas('tarefas_negociacao', [
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Cobrar retorno da proposta',
            'tipo' => 'follow_up',
            'status' => 'pendente',
            'responsavel_user_id' => $user->id,
        ]);
    }

    public function test_concluding_a_generic_tarefa_does_not_record_contact(): void
    {
        $user = User::factory()->create();
        $negociacao = Negociacao::factory()->create([
            'responsavel_user_id' => $user->id,
            'ultimo_contato_em' => null,
        ]);
        $tarefa = TarefaNegociacao::factory()->tipo(TipoTarefaNegociacao::Tarefa)->create([
            'negociacao_id' => $negociacao->id,
        ]);

        $this->actingAs($user)
            ->patch($this->tenantUrl('negociacoes.tarefas.update', [
                'negociacao' => $negociacao,
                'tarefa' => $tarefa,
            ]), [
                'descricao' => $tarefa->descricao,
                'data' => '2026-11-03',
                'status' => StatusTarefaNegociacao::Concluida->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($negociacao->refresh()->ultimo_contato_em);
        $this->assertSame('tarefa', $tarefa->refresh()->tipo->value);
        $this->assertSame(1, TarefaNegociacao::query()->where('negociacao_id', $negociacao->id)->count());
    }

    public function test_changing_negociacao_owner_moves_open_tarefas_of_the_previous_owner(): void
    {
        $antigo = User::factory()->create();
        $novo = User::factory()->create();
        $outro = User::factory()->create();
        $negociacao = Negociacao::factory()->create(['responsavel_user_id' => $antigo->id]);

        $doAntigo = TarefaNegociacao::factory()->create(['negociacao_id' => $negociacao->id, 'responsavel_user_id' => $antigo->id]);
        $semDono = TarefaNegociacao::factory()->create(['negociacao_id' => $negociacao->id, 'responsavel_user_id' => null]);
        $deOutro = TarefaNegociacao::factory()->create(['negociacao_id' => $negociacao->id, 'responsavel_user_id' => $outro->id]);
        $concluida = TarefaNegociacao::factory()->concluida()->create(['negociacao_id' => $negociacao->id, 'responsavel_user_id' => $antigo->id]);

        $negociacao->update(['responsavel_user_id' => $novo->id]);

        $this->assertSame($novo->id, $doAntigo->refresh()->responsavel_user_id);
        $this->assertSame($novo->id, $semDono->refresh()->responsavel_user_id);
        $this->assertSame($outro->id, $deOutro->refresh()->responsavel_user_id);
        $this->assertSame($antigo->id, $concluida->refresh()->responsavel_user_id);
    }
}
