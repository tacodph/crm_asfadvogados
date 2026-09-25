<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\FunilNegociacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_visit_contatos(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('contatos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Contatos')
                ->has('contatos', 0)
                ->where('crmCounts.contatos', '0')
                ->where('crmCounts.empresas', '0'));
    }

    public function test_authenticated_users_can_visit_negociacoes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Negociacoes')
                ->has('funis', 0)
                ->has('negociacoes', 0)
                ->where('crmCounts.negociacoes', '0'));
    }

    public function test_contatos_page_lists_database_contacts(): void
    {
        $user = User::factory()->create();
        Contato::factory()->pessoaFisica()->create([
            'nome' => 'Ana Zulu',
            'email' => 'ana.zulu@email.com',
        ]);

        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('contatos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Contatos')
                ->has('contatos', 1)
                ->where('contatos.0.nome', 'Ana Zulu')
                ->where('contatos.0.email', 'ana.zulu@email.com')
                ->where('contatos.0.uf', '—')
                ->where('contatos.0.municipio', '—')
                ->has('contatos.0.drawer.campos')
                ->where('crmCounts.contatos', '1'));
    }

    public function test_negociacoes_page_lists_database_deals(): void
    {
        $user = User::factory()->create([
            'name' => 'Bruno Gabriel',
        ]);
        $funil = Funil::factory()->create();
        $etapaInicial = EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Diagnóstico',
            'ordem' => 1,
        ]);
        EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Fechamento',
            'ordem' => 2,
            'campos' => ['contrato assinado'],
        ]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaInicial->id,
            'assunto' => 'Compliance trabalhista',
            'responsavel_user_id' => $user->id,
            'proxima_tarefa_em' => '2026-08-20',
            'previsao_fechamento' => '2026-09-15',
        ]);

        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Negociacoes')
                ->has('funis', 1)
                ->has('funis.0.etapas', 2)
                ->has('negociacoes', 1)
                ->where('negociacoes.0.assunto', 'Compliance trabalhista')
                ->where('negociacoes.0.responsavel', 'Bruno Gabriel')
                ->has('negociacoes.0.contato')
                ->where('negociacoes.0.empresa', null)
                ->where('negociacoes.0.dataLimiteLabel', 'Previsão de fechamento')
                ->where('negociacoes.0.dataLimite', '15/09/2026')
                ->where('negociacoes.0.contratoAssinado', false)
                ->has('negociacoes.0.drawer.campos')
                ->where('negociacoes.0.capi.estado', 'desligado')
                ->where('negociacoes.0.capi.label', '')
                ->where('crmCounts.negociacoes', '1'));
    }

    public function test_negociacoes_funnel_card_highlights_previsao_fechamento_urgency(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapa = EtapaFunil::factory()->for($funil)->create(['ordem' => 1]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'assunto' => 'Fechamento hoje',
            'responsavel_user_id' => $user->id,
            'proxima_tarefa_em' => null,
            'previsao_fechamento' => now()->toDateString(),
        ]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'assunto' => 'Fechamento em dois dias',
            'responsavel_user_id' => $user->id,
            'proxima_tarefa_em' => null,
            'previsao_fechamento' => now()->addDays(2)->toDateString(),
        ]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'assunto' => 'Fechamento longe',
            'responsavel_user_id' => $user->id,
            'proxima_tarefa_em' => null,
            'previsao_fechamento' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Negociacoes')
                ->where('negociacoes.0.assunto', 'Fechamento longe')
                ->where('negociacoes.0.cardFundo', null)
                ->where('negociacoes.1.assunto', 'Fechamento em dois dias')
                ->where('negociacoes.1.cardFundo', '#FFFBEB')
                ->where('negociacoes.2.assunto', 'Fechamento hoje')
                ->where('negociacoes.2.cardFundo', '#FDF2F0'));
    }

    public function test_negociacoes_funnel_card_shows_assinatura_date_on_final_stage(): void
    {
        $user = User::factory()->create([
            'name' => 'Flávio Augusto',
        ]);
        $funil = Funil::factory()->create();
        EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Diagnóstico',
            'ordem' => 1,
        ]);
        $etapaFinal = EtapaFunil::factory()->for($funil)->ganho()->create([
            'ordem' => 2,
            'campos' => ['contrato assinado'],
        ]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaFinal->id,
            'assunto' => 'Contrato trabalhista',
            'responsavel_user_id' => $user->id,
            'proxima_tarefa_em' => '2026-08-20',
            'etapa_desde' => '2026-08-28 10:00:00',
            'concluida_em' => '2026-09-01 14:00:00',
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Negociacoes')
                ->where('negociacoes.0.contratoAssinado', true)
                ->where('negociacoes.0.dataLimiteLabel', 'Concluída em')
                ->where('negociacoes.0.dataLimite', '01/09/2026')
                ->where('negociacoes.0.dataLimiteCor', '#14574F'));
    }

    public function test_negociacoes_funnel_columns_stretch_for_sideways_drop(): void
    {
        $source = file_get_contents(resource_path('js/pages/crm/Negociacoes.vue'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('items-stretch', $source);
        $this->assertStringContainsString('overflow-x-auto', $source);
        $this->assertStringContainsString('overflow-y-auto', $source);
        $this->assertStringContainsString('min-h-0 flex-1', $source);
    }

    public function test_negociacoes_page_has_multiselect_filters_for_lead_fields(): void
    {
        $source = file_get_contents(resource_path('js/pages/crm/Negociacoes.vue'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('selecionarFunil', $source);
        $this->assertStringContainsString('funilAtivo', $source);
        $this->assertStringContainsString('etapasSelecionadas', $source);
        $this->assertStringContainsString('empresasSelecionadas', $source);
        $this->assertStringContainsString('contatosSelecionados', $source);
        $this->assertStringContainsString('canaisSelecionados', $source);
        $this->assertStringContainsString('atendimentosSelecionados', $source);
        $this->assertStringContainsString('qualificacoesSelecionadas', $source);
        $this->assertStringContainsString('responsaveisSelecionados', $source);
        $this->assertStringContainsString('negociacoesFiltradas', $source);
        $this->assertStringContainsString('alternarQualificacao(status)', $source);
        $this->assertStringContainsString('alternarEmpresa(empresa)', $source);
        $this->assertStringContainsString('alternarContato(contato)', $source);
        $this->assertStringContainsString('type="checkbox"', $source);
        $this->assertMatchesRegularExpression('/>\s*Etapa\s*<\/span>/u', $source);
        $this->assertMatchesRegularExpression('/>\s*Empresa\s*<\/span>/u', $source);
        $this->assertMatchesRegularExpression('/>\s*Contato\s*<\/span>/u', $source);
        $this->assertMatchesRegularExpression('/>\s*Canal\s*<\/span>/u', $source);
        $this->assertMatchesRegularExpression('/>\s*Atendimento\s*<\/span>/u', $source);
        $this->assertMatchesRegularExpression('/>\s*Qualificação\s*<\/span>/u', $source);
        $this->assertMatchesRegularExpression('/>\s*Responsável\s*<\/span>/u', $source);
        $this->assertStringContainsString('Nova lead', $source);
        $this->assertStringContainsString('funil_id: funilAtivo.id', $source);
        $this->assertStringNotContainsString('DropdownMenuCheckboxItem', $source);
        $this->assertStringNotContainsString('funisSelecionados', $source);
        $this->assertStringNotContainsString('funisVisiveis', $source);
    }

    public function test_authenticated_users_can_visit_empresas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('empresas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Empresas')
                ->has('empresas', 0));
    }

    public function test_empresas_page_lists_database_companies(): void
    {
        $user = User::factory()->create();
        Empresa::factory()->create([
            'nome' => 'Metalúrgica Teste',
            'cnpj' => '11.222.333/0001-44',
        ]);

        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('empresas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Empresas')
                ->has('empresas', 1)
                ->where('empresas.0.nome', 'Metalúrgica Teste')
                ->where('empresas.0.cnpj', '11.222.333/0001-44')
                ->where('empresas.0.uf', 'RS')
                ->where('empresas.0.municipio', 'Caxias do Sul')
                ->has('empresas.0.drawer.campos')
                ->where('crmCounts.empresas', '1'));
    }

    public function test_seeded_prototype_data_appears_on_crm_pages(): void
    {
        $this->seed(FunilNegociacaoSeeder::class);

        // The seeder provisions its own dev tenant (slug "asfadvogados"),
        // independent of the random tenant TestCase::setUp() created.
        $tenant = Tenant::query()->where('slug', 'asfadvogados')->firstOrFail();
        $user = $this->asTenant($tenant, fn () => User::factory()->create());
        $this->actingAs($user);

        $this->withoutVite()
            ->get($this->tenantUrl('contatos.index', tenant: $tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Contatos')
                ->has('contatos', 16)
                ->has('contatos.0.drawer.consentimentos'));

        $this->withoutVite()
            ->get($this->tenantUrl('empresas.index', tenant: $tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Empresas')
                ->has('empresas', 7)
                ->has('empresas.0.drawer.contatos')
                ->has('empresas.0.drawer.negociacoes'));

        $this->withoutVite()
            ->get($this->tenantUrl('negociacoes.index', tenant: $tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Negociacoes')
                ->has('funis', 2)
                ->has('negociacoes', 15)
                ->where('crmCounts.negociacoes', '15'));
    }

    public function test_guests_are_redirected_from_contatos(): void
    {
        $this->get($this->tenantUrl('contatos.index'))->assertRedirect($this->tenantUrl('login'));
    }

    public function test_guests_are_redirected_from_empresas(): void
    {
        $this->get($this->tenantUrl('empresas.index'))->assertRedirect($this->tenantUrl('login'));
    }

    public function test_guests_are_redirected_from_negociacoes(): void
    {
        $this->get($this->tenantUrl('negociacoes.index'))->assertRedirect($this->tenantUrl('login'));
    }
}
