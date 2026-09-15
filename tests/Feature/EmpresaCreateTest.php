<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use App\Models\Setor;
use App\Models\StatusComercial;
use App\Models\StatusConflito;
use App\Models\Uf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmpresaCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_loads_defaults_and_options(): void
    {
        $user = User::factory()->create();
        Setor::factory()->create();
        StatusConflito::factory()->create();
        StatusComercial::factory()->create(['slug' => 'novo']);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('empresas.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/EmpresasCreate')
                ->has('defaults.setor_id')
                ->has('opcoes.setores')
                ->has('opcoes.statusComerciais')
                ->has('opcoes.estados')
                ->has('opcoes.responsaveis'));
    }

    public function test_can_store_a_new_empresa(): void
    {
        $user = User::factory()->create();
        $setor = Setor::factory()->create();
        $status = StatusConflito::factory()->create();
        $statusComercial = StatusComercial::factory()->create(['slug' => 'novo']);

        IbgeEstado::query()->updateOrCreate(
            ['id' => 43],
            [
                'txt_uf' => 'Rio Grande do Sul',
                'txt_sigla_uf' => 'RS',
            ],
        );
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
            ->post(route('empresas.store'), [
                'nome' => 'Nova Empresa Ltda',
                'cnpj' => '11.222.333/0001-44',
                'setor_id' => $setor->id,
                'porte' => '50 funcionários',
                'municipio_id' => 4314902,
                'status_conflito_id' => $status->id,
                'status_comercial_id' => $statusComercial->id,
                'conflito_texto' => null,
                'responsavel_user_id' => $user->id,
            ])
            ->assertRedirect(route('empresas.index'));

        $empresa = Empresa::query()->where('cnpj', '11.222.333/0001-44')->first();

        $this->assertNotNull($empresa);
        $this->assertSame('Nova Empresa Ltda', $empresa->nome);
        $this->assertSame($user->tenant_id, $empresa->tenant_id);
        $this->assertSame('Porto Alegre', $empresa->cidade);
        $this->assertSame('RS', $empresa->uf->sigla);
    }

    public function test_can_store_empresa_without_cnpj(): void
    {
        $user = User::factory()->create();
        $setor = Setor::factory()->create();
        $status = StatusConflito::factory()->create();
        $statusComercial = StatusComercial::factory()->create(['slug' => 'novo']);

        IbgeEstado::query()->updateOrCreate(
            ['id' => 43],
            [
                'txt_uf' => 'Rio Grande do Sul',
                'txt_sigla_uf' => 'RS',
            ],
        );
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
            ->post(route('empresas.store'), [
                'nome' => 'Empresa Sem CNPJ',
                'cnpj' => '',
                'setor_id' => $setor->id,
                'porte' => '10 funcionários',
                'municipio_id' => 4314902,
                'status_conflito_id' => $status->id,
                'status_comercial_id' => $statusComercial->id,
                'conflito_texto' => null,
                'responsavel_user_id' => $user->id,
            ])
            ->assertRedirect(route('empresas.index'));

        $empresa = Empresa::query()->where('nome', 'Empresa Sem CNPJ')->first();

        $this->assertNotNull($empresa);
        $this->assertNull($empresa->cnpj);
    }

    public function test_guest_cannot_create_an_empresa(): void
    {
        $this->get(route('empresas.create'))->assertRedirect(route('login'));
        $this->post(route('empresas.store'), [])->assertRedirect(route('login'));
    }
}
