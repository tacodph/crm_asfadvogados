<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NegociacaoCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_visit_negociacao_create_page_with_contato_prefills(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        Funil::factory()->create();

        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('negociacoes.create', ['contato_id' => $contato->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesCreate')
                ->where('defaults.contato_id', $contato->id)
                ->where('origem.contato_id', $contato->id));
    }

    public function test_authenticated_user_can_visit_negociacao_create_page_with_empresa_prefills(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create();
        Contato::factory()->create(['empresa_id' => $empresa->id]);
        Funil::factory()->create();

        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('negociacoes.create', ['empresa_id' => $empresa->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesCreate')
                ->where('defaults.empresa_id', $empresa->id)
                ->where('origem.empresa_id', $empresa->id));
    }

    public function test_authenticated_user_can_store_a_negociacao_and_is_redirected_to_open_it(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();

        $response = $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.create'))
            ->post($this->tenantUrl('negociacoes.store'), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'empresa_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Assessoria trabalhista',
                'valor' => 15000,
                'previsao_fechamento' => null,
                'proxima_tarefa' => 'Primeiro contato',
                'proxima_tarefa_em' => now()->toDateString(),
                'proxima_tarefa_hora' => '09:00',
            ]);

        $negociacao = Negociacao::query()->where('assunto', 'Assessoria trabalhista')->first();

        $this->assertNotNull($negociacao);
        $this->assertSame($contato->id, $negociacao->contato_id);
        $this->assertSame($funil->id, $negociacao->funil_id);
        $this->assertSame($etapa->id, $negociacao->etapa_funil_id);
        $this->assertSame('Primeiro contato', $negociacao->proxima_tarefa);
        $this->assertDatabaseHas('tarefas_negociacao', [
            'negociacao_id' => $negociacao->id,
            'descricao' => 'Primeiro contato',
            'status' => 'pendente',
        ]);

        $response->assertRedirect($this->tenantUrl('negociacoes.index', [
            'negociacao' => $negociacao->id,
        ]));
    }

    public function test_create_page_prefills_funil_from_query_string(): void
    {
        $user = User::factory()->create();
        Funil::factory()->create(['ordem' => 1]);
        $funilAlvo = Funil::factory()->create(['ordem' => 2]);
        $etapaAlvo = EtapaFunil::factory()->for($funilAlvo)->create(['ordem' => 1]);

        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('negociacoes.create', ['funil_id' => $funilAlvo->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesCreate')
                ->where('defaults.funil_id', $funilAlvo->id)
                ->where('defaults.etapa_funil_id', $etapaAlvo->id));
    }

    public function test_create_page_prefills_origem_from_query_string(): void
    {
        $user = User::factory()->create();
        Funil::factory()->create();

        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('negociacoes.create', [
                'utm_campaign' => 'concursos-2026',
                'utm_content' => '120210000000000000',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/NegociacoesCreate')
                ->where('defaults.utm_campaign', 'concursos-2026')
                ->where('defaults.utm_content', '120210000000000000'));
    }

    public function test_store_persists_origem_utm_when_provided(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $canal = CanalContato::factory()->create();

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.create'))
            ->post($this->tenantUrl('negociacoes.store'), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapa->id,
                'contato_id' => $contato->id,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Lead pago',
                'valor' => 5000,
                'utm_source' => 'facebook',
                'utm_campaign' => 'bancarios-rs',
                'meta_ad_id' => '120210999999999999',
                'utm_medium' => '',
            ]);

        $negociacao = Negociacao::query()->where('assunto', 'Lead pago')->firstOrFail();

        $this->assertSame([
            'utm_source' => 'facebook',
            'utm_campaign' => 'bancarios-rs',
            'meta_ad_id' => '120210999999999999',
        ], $negociacao->origem_utm);
    }

    public function test_store_negociacao_requires_contato_and_valid_etapa_for_funil(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapaDeOutroFunil = EtapaFunil::factory()->create();
        $canal = CanalContato::factory()->create();

        $this->actingAs($user)
            ->from($this->tenantUrl('negociacoes.create'))
            ->post($this->tenantUrl('negociacoes.store'), [
                'funil_id' => $funil->id,
                'etapa_funil_id' => $etapaDeOutroFunil->id,
                'contato_id' => null,
                'canal_contato_id' => $canal->id,
                'responsavel_user_id' => $user->id,
                'assunto' => 'Sem contato',
                'valor' => 1000,
            ])
            ->assertRedirect($this->tenantUrl('negociacoes.create'))
            ->assertSessionHasErrors(['contato_id', 'etapa_funil_id']);

        $this->assertSame(0, Negociacao::query()->count());
    }

    public function test_guest_cannot_create_negociacao(): void
    {
        $this->get($this->tenantUrl('negociacoes.create'))
            ->assertRedirect($this->tenantUrl('login'));

        $this->post($this->tenantUrl('negociacoes.store'), [])
            ->assertRedirect($this->tenantUrl('login'));
    }
}
