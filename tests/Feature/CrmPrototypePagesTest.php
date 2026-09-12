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
            'trafego' => ['trafego.index', 'crm/Trafego'],
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
            'trafego' => ['trafego.index'],
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
}
