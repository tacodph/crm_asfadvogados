<?php

namespace Tests\Feature;

use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use App\Notifications\FollowUpLembreteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LembretesFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'crm.timezone' => 'America/Sao_Paulo',
            'crm.lembretes.antecedencia_minutos' => 15,
            'crm.lembretes.hora_sem_horario' => '08:00',
        ]);

        // 14:00 em São Paulo (UTC-3).
        Carbon::setTestNow('2026-11-04 17:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reminds_tarefas_whose_time_is_near_only_once(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $proxima = $this->tarefa($user, '14:10');
        $depois = $this->tarefa($user, '15:00');
        $semHora = $this->tarefa($user, null);
        $amanha = $this->tarefa($user, '14:05', '2026-11-05');

        $this->artisan('crm:lembretes-follow-up')->assertSuccessful();
        $this->artisan('crm:lembretes-follow-up')->assertSuccessful();

        Notification::assertSentToTimes($user, FollowUpLembreteNotification::class, 2);
        Notification::assertSentTo(
            $user,
            FollowUpLembreteNotification::class,
            fn (FollowUpLembreteNotification $notificacao): bool => $notificacao->tarefa->is($proxima),
        );

        $this->assertNotNull($proxima->refresh()->lembrete_enviado_em);
        $this->assertNotNull($semHora->refresh()->lembrete_enviado_em);
        $this->assertNull($depois->refresh()->lembrete_enviado_em);
        $this->assertNull($amanha->refresh()->lembrete_enviado_em);
    }

    public function test_tarefas_without_time_wait_for_the_configured_hour(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-11-04 10:30:00'); // 07:30 em São Paulo

        $user = User::factory()->create();
        $this->tarefa($user, null);

        $this->artisan('crm:lembretes-follow-up')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_closed_negociacoes_are_not_reminded(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapaGanha = EtapaFunil::factory()->ganho()->create(['funil_id' => $funil->id]);
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaGanha->id,
            'responsavel_user_id' => $user->id,
        ]);
        TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'responsavel_user_id' => $user->id,
            'data' => '2026-11-04',
            'hora' => '14:05',
        ]);

        $this->artisan('crm:lembretes-follow-up')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminders_are_sent_per_tenant(): void
    {
        Notification::fake();

        $outro = $this->createTenant();
        $alheio = $this->asTenant($outro, function (): User {
            $usuario = User::factory()->create();
            $this->tarefa($usuario, '14:05');

            return $usuario;
        });

        $this->artisan('crm:lembretes-follow-up')->assertSuccessful();

        Notification::assertSentToTimes($alheio, FollowUpLembreteNotification::class, 1);
    }

    public function test_bell_lists_unread_reminders_and_opening_marks_as_read(): void
    {
        $user = User::factory()->create();
        $tarefa = $this->tarefa($user, '14:05');

        $this->artisan('crm:lembretes-follow-up')->assertSuccessful();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('meu-dia'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notificacoes.naoLidas', 1)
                ->where('notificacoes.itens.0.tarefaId', $tarefa->id)
                ->where('notificacoes.itens.0.negociacaoId', $tarefa->negociacao_id)
                ->where('crmCounts.meuDia', '1'));

        $notificacao = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->patch($this->tenantUrl('notificacoes.abrir', ['notificacao' => $notificacao->id]))
            ->assertRedirect($this->tenantUrl('negociacoes.edit', ['negociacao' => $tarefa->negociacao_id]));

        $this->assertNotNull($notificacao->refresh()->read_at);
    }

    public function test_user_cannot_open_someone_elses_notification(): void
    {
        $dono = User::factory()->create();
        $this->tarefa($dono, '14:05');
        $this->artisan('crm:lembretes-follow-up')->assertSuccessful();

        $outro = User::factory()->create();

        $this->actingAs($outro)
            ->patch($this->tenantUrl('notificacoes.abrir', ['notificacao' => $dono->notifications()->firstOrFail()->id]))
            ->assertNotFound();
    }

    public function test_funil_cards_expose_return_marker(): void
    {
        $user = User::factory()->create();

        $atrasada = Negociacao::factory()->create(['responsavel_user_id' => $user->id, 'proxima_tarefa_em' => '2026-11-02']);
        $hoje = Negociacao::factory()->create(['responsavel_user_id' => $user->id, 'proxima_tarefa_em' => '2026-11-04']);
        $semPasso = Negociacao::factory()->create(['responsavel_user_id' => $user->id, 'proxima_tarefa_em' => null, 'proxima_tarefa' => null, 'proxima_tarefa_hora' => null]);
        $futura = Negociacao::factory()->create(['responsavel_user_id' => $user->id, 'proxima_tarefa_em' => '2026-11-10']);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($atrasada, $hoje, $semPasso, $futura): void {
                $retornos = collect($page->toArray()['props']['negociacoes'])->pluck('retorno', 'id');

                $this->assertSame('atrasado', $retornos[$atrasada->id]);
                $this->assertSame('hoje', $retornos[$hoje->id]);
                $this->assertSame('sem_proximo_passo', $retornos[$semPasso->id]);
                $this->assertNull($retornos[$futura->id]);
            });
    }

    private function tarefa(User $responsavel, ?string $hora, string $data = '2026-11-04'): TarefaNegociacao
    {
        $negociacao = Negociacao::factory()->create(['responsavel_user_id' => $responsavel->id]);

        return TarefaNegociacao::factory()->create([
            'negociacao_id' => $negociacao->id,
            'responsavel_user_id' => $responsavel->id,
            'data' => $data,
            'hora' => $hora,
        ]);
    }
}
