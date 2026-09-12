<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\StatusQualificacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get($this->tenantUrl('dashboard'))
            ->assertRedirect($this->tenantUrl('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('periodo', '30 dias')
                ->has('kpis', 4)
                ->has('conversao.etapas')
                ->has('conversaoFunis')
                ->has('conversaoNegociacoes')
                ->has('canais')
                ->has('perdas')
                ->has('equipe')
                ->has('atendimentosEncerrados')
                ->has('atendimentosEncerrados.motivos')
                ->has('atendimentosEncerrados.continuidade')
                ->has('atendimentosEncerrados.qualificacao')
                ->has('atendimentosEncerrados.leads')
                ->has('perfilLeadsMeta')
                ->has('perfilLeadsMeta.topDdds')
                ->has('perfilLeadsMeta.clustersConcurso')
                ->has('perfilLeadsMeta.fasesDemanda')
                ->has('perfilLeadsMeta.recomendacoesMeta'));
    }

    public function test_dashboard_provides_analise_atendimentos_encerrados(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create(['nome' => 'Funil Geral']);
        $etapaEncerrada = EtapaFunil::factory()->for($funil)->perdido()->create(['nome' => 'Atendimentos encerrados']);

        $contato = Contato::factory()->create(['nome' => 'Carlos Pereira', 'telefone' => '(61) 98888-7777']);
        $statusQualificado = StatusQualificacao::factory()->create(['nome' => 'Qualificado', 'cor_fundo' => '#E1F5EA', 'cor_texto' => '#0F5132']);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaEncerrada->id,
            'contato_id' => $contato->id,
            'responsavel_user_id' => $user->id,
            'assunto' => 'PMDF - Recurso Administrativo',
            'motivo_desqualificacao' => 'Não respondeu mesmo após tentativa de follow-up',
            'continuidade_atendimento' => 'Interrupção no 1º contato',
            'status_qualificacao_id' => $statusQualificado->id,
            'observacoes_complementares' => 'Aguardar publicação do novo edital',
            'etapa_desde' => now()->subDays(2),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('atendimentosEncerrados.total', 1)
                ->where('atendimentosEncerrados.totalQualificados', 1)
                ->where('atendimentosEncerrados.totalDesqualificados', 0)
                ->where('atendimentosEncerrados.totalSemResposta', 1)
                ->where('atendimentosEncerrados.motivos.0.motivo', 'Não respondeu mesmo após tentativa de follow-up')
                ->where('atendimentosEncerrados.continuidade.0.nome', 'Interrupção no 1º contato')
                ->where('atendimentosEncerrados.leads.0.assunto', 'PMDF - Recurso Administrativo')
                ->where('atendimentosEncerrados.leads.0.contatoNome', 'Carlos Pereira')
                ->where('atendimentosEncerrados.leads.0.observacoes', 'Aguardar publicação do novo edital')
            );
    }

    public function test_dashboard_provides_perfil_leads_para_campanhas_meta(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create(['nome' => 'Funil Concursos']);
        $etapa = EtapaFunil::factory()->for($funil)->create(['nome' => 'Novo lead']);

        $contato = Contato::factory()->create([
            'nome' => 'Renato Silva',
            'telefone' => '(61) 99123-4567',
        ]);

        $statusQualificado = StatusQualificacao::factory()->create(['nome' => 'Qualificado']);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'responsavel_user_id' => $user->id,
            'assunto' => 'PMDF - QUESTÕES',
            'status_qualificacao_id' => $statusQualificado->id,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('perfilLeadsMeta.total', 1)
                ->where('perfilLeadsMeta.totalQualificados', 1)
                ->where('perfilLeadsMeta.taxaQualificacao', 100)
                ->where('perfilLeadsMeta.topDdds.0.ddd', '61')
                ->where('perfilLeadsMeta.clustersConcurso.0.nome', 'PMDF (Polícia Militar DF)')
                ->where('perfilLeadsMeta.fasesDemanda.0.fase', 'Anulação de Questões (Prova Objetiva)')
                ->has('perfilLeadsMeta.recomendacoesMeta.publicosSugeridos')
                ->has('perfilLeadsMeta.recomendacoesMeta.geotargeting')
                ->has('perfilLeadsMeta.recomendacoesMeta.ganchosCriativos')
            );
    }

    public function test_dashboard_uses_real_negotiation_metrics(): void
    {
        $user = User::factory()->create(['name' => 'Camila Moraes']);
        $funil = Funil::factory()->create(['nome' => 'B2B consultivo', 'slug' => 'b2b', 'ordem' => 1]);
        $etapaInicial = EtapaFunil::factory()->for($funil)->create(['nome' => 'Prospecção', 'ordem' => 1]);
        $etapaFinal = EtapaFunil::factory()->for($funil)->ganho()->create(['nome' => 'Fechamento', 'ordem' => 2]);
        $canal = CanalContato::factory()->create(['nome' => 'WhatsApp', 'cor' => '#14574F']);

        $aberta = Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaInicial->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
            'valor' => 10000,
            'created_at' => now()->subDays(2),
        ]);

        Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaFinal->id,
            'canal_contato_id' => $canal->id,
            'responsavel_user_id' => $user->id,
            'valor' => 25000,
            'created_at' => now()->subDays(5),
            'etapa_desde' => now()->subDay(),
        ]);

        HistoricoNegociacao::factory()->create([
            'negociacao_id' => $aberta->id,
            'tipo' => 'call',
            'titulo' => 'Primeiro contato',
            'ocorrido_em' => $aberta->created_at->copy()->addHours(2),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('dashboard', ['periodo' => '30 dias']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('kpis.0.value', '2')
                ->where('kpis.1.value', '50%')
                ->where('conversao.funil', 'B2B consultivo')
                ->where('canais.0.nome', 'WhatsApp')
                ->where('canais.0.qtd', 2)
                ->where('equipe.0.nome', 'Camila Moraes')
                ->where('equipe.0.leads', 2)
                ->where('equipe.0.fechados', 1)
                ->has('conversaoFunis', 1)
                ->where('conversaoFunis.0.nome', 'B2B consultivo')
                ->where('conversaoFunis.0.leads', 2)
                ->where('conversaoFunis.0.fechados', 1)
                ->where('conversaoFunis.0.pct', 50)
                ->has('conversaoNegociacoes', 2)
                ->where('conversaoNegociacoes.0.rotulo', 'Em andamento')
                ->where('conversaoNegociacoes.0.qtd', 1)
                ->where('conversaoNegociacoes.1.rotulo', 'Fechadas')
                ->where('conversaoNegociacoes.1.qtd', 1)
                ->has('perdas', 0));
    }

    public function test_dashboard_accepts_periodo_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('dashboard', ['periodo' => '7 dias']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('periodo', '7 dias'));
    }

    public function test_authenticated_users_can_logout_from_the_crm(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
