<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmPrototypePagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function enabledPages(): array
    {
        return [
            'painel' => ['dashboard', 'Dashboard'],
            'atividades' => ['atividades.index', 'crm/Atividades'],
            'calendario' => ['calendario.index', 'crm/Calendario'],
            'propostas' => ['propostas.index', 'crm/Propostas'],
            'automacoes' => ['automacoes.index', 'crm/Automacoes'],
            'trafego_configuracao' => ['trafego.index', 'crm/Trafego'],
            'trafego_eventos' => ['trafego.eventos.index', 'crm/TrafegoEventos'],
            'trafego_diagnostico' => ['trafego.diagnostico.index', 'crm/TrafegoDiagnostico'],
            'trafego_investimento' => ['trafego.investimento.index', 'crm/TrafegoInvestimento'],
            'trafego_email' => ['trafego.email.index', 'crm/TrafegoEmail'],
            'site' => ['site.index', 'crm/Site'],
            'compliance' => ['compliance.index', 'crm/Compliance'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function enabledRoutes(): array
    {
        return [
            'painel' => ['dashboard'],
            'atividades' => ['atividades.index'],
            'calendario' => ['calendario.index'],
            'propostas' => ['propostas.index'],
            'automacoes' => ['automacoes.index'],
            'trafego_configuracao' => ['trafego.index'],
            'trafego_eventos' => ['trafego.eventos.index'],
            'trafego_diagnostico' => ['trafego.diagnostico.index'],
            'trafego_investimento' => ['trafego.investimento.index'],
            'trafego_email' => ['trafego.email.index'],
            'site' => ['site.index'],
            'compliance' => ['compliance.index'],
        ];
    }

    #[DataProvider('enabledPages')]
    public function test_authenticated_users_can_visit_newly_enabled_menu_pages(
        string $route,
        string $component,
    ): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[DataProvider('enabledRoutes')]
    public function test_guests_are_redirected_from_newly_enabled_menu_pages(
        string $route,
    ): void {
        $this->get($this->tenantUrl($route))
            ->assertRedirect($this->tenantUrl('login'));
    }

    public function test_nav_groups_expose_trafego_as_submenu_pages(): void
    {
        $source = file_get_contents(resource_path('js/data/crm.ts'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString("title: 'API de tráfego'", $source);
        $this->assertStringContainsString("label: 'Configuração'", $source);
        $this->assertStringContainsString("label: 'Eventos'", $source);
        $this->assertStringContainsString("label: 'Diagnóstico'", $source);
        $this->assertStringContainsString("label: 'Investimento'", $source);
        $this->assertStringContainsString("label: 'E-mail'", $source);
        $this->assertStringNotContainsString("title: 'Operação'", $source);
    }

    public function test_trafego_pages_do_not_render_tabs_component(): void
    {
        foreach ([
            'Trafego.vue',
            'TrafegoEventos.vue',
            'TrafegoDiagnostico.vue',
            'TrafegoInvestimento.vue',
            'TrafegoEmail.vue',
        ] as $page) {
            $source = file_get_contents(resource_path('js/pages/crm/'.$page));

            $this->assertNotFalse($source);
            $this->assertStringNotContainsString('TrafegoTabs', $source);
        }

        $this->assertFileDoesNotExist(resource_path('js/components/crm/TrafegoTabs.vue'));
    }
}
