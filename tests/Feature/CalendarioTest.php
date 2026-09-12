<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_calendario(): void
    {
        $this->get($this->tenantUrl('calendario.index'))
            ->assertRedirect($this->tenantUrl('login'));
    }

    public function test_authenticated_users_can_visit_calendario(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('calendario.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Calendario')
                ->has('hoje')
                ->has('funis', 0)
                ->has('eventos', 0));
    }

    public function test_calendario_lists_funis_and_negotiation_events(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create([
            'nome' => 'B2B consultivo',
            'slug' => 'b2b',
        ]);
        $etapa = EtapaFunil::factory()->create([
            'funil_id' => $funil->id,
            'nome' => 'Diagnóstico',
        ]);
        $contato = Contato::factory()->pessoaFisica()->create([
            'nome' => 'Camila Moraes',
        ]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'assunto' => 'Compliance trabalhista',
            'proxima_tarefa' => 'Ligar para decisor',
            'proxima_tarefa_em' => '2026-08-20',
            'proxima_tarefa_hora' => '10:30',
            'previsao_fechamento' => '2026-09-15',
            'responsavel_user_id' => $user->id,
        ]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'proxima_tarefa' => null,
            'proxima_tarefa_em' => null,
            'proxima_tarefa_hora' => null,
            'previsao_fechamento' => null,
            'responsavel_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('calendario.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Calendario')
                ->has('funis', 1)
                ->where('funis.0.nome', 'B2B consultivo')
                ->has('eventos', 2)
                ->where('eventos.0.tipo', 'tarefa')
                ->where('eventos.0.titulo', 'Ligar para decisor')
                ->where('eventos.0.data', '2026-08-20')
                ->where('eventos.0.hora', '10:30')
                ->where('eventos.0.funilNome', 'B2B consultivo')
                ->where('eventos.0.contatoNome', 'Camila Moraes')
                ->where('eventos.0.etapaNome', 'Diagnóstico')
                ->where('eventos.0.responsavelNome', $user->name)
                ->where('eventos.1.tipo', 'previsao')
                ->where('eventos.1.data', '2026-09-15')
                ->where('eventos.1.titulo', 'Previsão de fechamento')
                ->where('eventos.1.contatoNome', 'Camila Moraes')
                ->where('eventos.1.etapaNome', 'Diagnóstico')
                ->where('eventos.1.responsavelNome', $user->name));
    }

    public function test_calendario_page_component_uses_tailwick_event_palette(): void
    {
        $source = file_get_contents(resource_path('js/pages/crm/Calendario.vue'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('bg-sky-100 text-sky-600', $source);
        $this->assertStringContainsString('bg-green-100 text-green-600', $source);
        $this->assertStringContainsString('bg-yellow-100 text-yellow-600', $source);
        $this->assertStringContainsString('bg-purple-100 text-purple-600', $source);
        $this->assertStringContainsString('bg-custom-500', $source);
        $this->assertStringContainsString('Eventos do dia selecionado', $source);
    }
}
