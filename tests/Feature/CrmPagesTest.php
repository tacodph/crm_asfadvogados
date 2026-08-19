<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\Negociacao;
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
            ->get(route('contatos.index'))
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
            ->get(route('negociacoes.index'))
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
            ->get(route('contatos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Contatos')
                ->has('contatos', 1)
                ->where('contatos.0.nome', 'Ana Zulu')
                ->where('contatos.0.email', 'ana.zulu@email.com')
                ->has('contatos.0.drawer.campos')
                ->where('crmCounts.contatos', '1'));
    }

    public function test_negociacoes_page_lists_database_deals(): void
    {
        $user = User::factory()->create();
        Negociacao::factory()->create([
            'assunto' => 'Compliance trabalhista',
        ]);

        $this->actingAs($user);

        $this->withoutVite()
            ->get(route('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Negociacoes')
                ->has('funis', 1)
                ->has('funis.0.etapas', 1)
                ->has('negociacoes', 1)
                ->where('negociacoes.0.assunto', 'Compliance trabalhista')
                ->has('negociacoes.0.drawer.campos')
                ->where('crmCounts.negociacoes', '1'));
    }

    public function test_authenticated_users_can_visit_empresas(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->withoutVite()
            ->get(route('empresas.index'))
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
            ->get(route('empresas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Empresas')
                ->has('empresas', 1)
                ->where('empresas.0.nome', 'Metalúrgica Teste')
                ->where('empresas.0.cnpj', '11.222.333/0001-44')
                ->has('empresas.0.drawer.campos')
                ->where('crmCounts.empresas', '1'));
    }

    public function test_seeded_prototype_data_appears_on_crm_pages(): void
    {
        $this->seed(FunilNegociacaoSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->withoutVite()
            ->get(route('contatos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Contatos')
                ->has('contatos', 16)
                ->has('contatos.0.drawer.consentimentos'));

        $this->withoutVite()
            ->get(route('empresas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Empresas')
                ->has('empresas', 7)
                ->has('empresas.0.drawer.contatos')
                ->has('empresas.0.drawer.negociacoes'));

        $this->withoutVite()
            ->get(route('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/Negociacoes')
                ->has('funis', 2)
                ->has('negociacoes', 15)
                ->where('crmCounts.negociacoes', '15'));
    }

    public function test_guests_are_redirected_from_contatos(): void
    {
        $this->get(route('contatos.index'))->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_empresas(): void
    {
        $this->get(route('empresas.index'))->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_negociacoes(): void
    {
        $this->get(route('negociacoes.index'))->assertRedirect(route('login'));
    }
}
