<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\IbgeMunicipio;
use App\Models\Setor;
use App\Models\StatusConflito;
use App\Models\Uf;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmpresaUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_loads_when_tenant_is_resolved_from_auth_user(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create(['nome' => 'Empresa Com Tenant Limpo']);

        app(CurrentTenant::class)->set(null);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('empresas.edit', $empresa))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/EmpresasEdit')
                ->where('empresa.id', $empresa->id)
                ->where('empresa.nome', 'Empresa Com Tenant Limpo')
                ->has('contatos'));
    }

    public function test_edit_page_lists_linked_contacts(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create();
        $contato = Contato::factory()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'Contato da Empresa',
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('empresas.edit', $empresa))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/EmpresasEdit')
                ->has('contatos', 1)
                ->where('contatos.0.id', $contato->id)
                ->where('contatos.0.nome', 'Contato da Empresa'));
    }

    public function test_edit_page_includes_ibge_estado_and_municipio_options(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('empresas.edit', ['empresa' => $empresa]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/EmpresasEdit')
                ->where('empresa.id', $empresa->id)
                ->where('empresa.municipio_id', 4305108)
                ->has('opcoes.estados')
                ->has('opcoes.municipios'));
    }

    public function test_can_update_empresa_localidade_from_municipio(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create();
        $status = StatusConflito::factory()->create();

        IbgeMunicipio::query()->updateOrCreate(
            ['id' => 4314902],
            [
                'txt_nome_municipios' => 'Porto Alegre',
                'cod_municipio_6dig' => 431490,
                'estado_id' => 43,
            ],
        );

        Uf::query()->firstOrCreate(
            ['sigla' => 'RS'],
            ['nome' => 'Rio Grande do Sul', 'ordem' => 21],
        );

        $this->actingAs($user)
            ->patch($this->tenantUrl('empresas.update', ['empresa' => $empresa]), [
                'nome' => 'Empresa Atualizada',
                'cnpj' => $empresa->cnpj,
                'setor_id' => $setor->id,
                'porte' => '100 funcionários',
                'municipio_id' => 4314902,
                'status_conflito_id' => $status->id,
                'status_comercial_id' => $empresa->status_comercial_id,
                'conflito_texto' => null,
                'responsavel_user_id' => null,
            ])
            ->assertRedirect($this->tenantUrl('empresas.index'));

        $empresa->refresh();

        $this->assertSame('Empresa Atualizada', $empresa->nome);
        $this->assertSame('Porto Alegre', $empresa->cidade);
        $this->assertSame(4314902, $empresa->municipio_id);
        $this->assertSame('RS', $empresa->uf->sigla);
    }

    public function test_guests_cannot_update_empresas(): void
    {
        $empresa = Empresa::factory()->create();

        $this->patch($this->tenantUrl('empresas.update', ['empresa' => $empresa]), [
            'nome' => 'Hack',
            'cnpj' => $empresa->cnpj,
            'setor_id' => $empresa->setor_id,
            'porte' => $empresa->porte,
            'municipio_id' => 4305108,
            'status_conflito_id' => $empresa->status_conflito_id,
            'status_comercial_id' => $empresa->status_comercial_id,
        ])->assertRedirect($this->tenantUrl('login'));
    }
}
