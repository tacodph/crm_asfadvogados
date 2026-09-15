<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\FinalidadeConsentimento;
use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\Uf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContatoCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_loads_defaults_and_options(): void
    {
        $user = User::factory()->create();
        TipoPessoa::factory()->create();
        CanalContato::factory()->create();
        StatusConsentimento::factory()->create(['slug' => 'nao-concedido']);
        StatusComercial::factory()->create(['slug' => 'novo']);
        FinalidadeConsentimento::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('contatos.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/ContatosCreate')
                ->has('defaults.consentimentos', 1)
                ->has('opcoes.tiposPessoa')
                ->has('opcoes.canais')
                ->has('opcoes.statusComerciais')
                ->has('opcoes.estados'));
    }

    public function test_can_store_a_new_contact(): void
    {
        $user = User::factory()->create();
        $tipo = TipoPessoa::factory()->create(['slug' => 'pf']);
        $canal = CanalContato::factory()->create();
        $status = StatusConsentimento::factory()->create(['slug' => 'nao-concedido']);
        $statusComercial = StatusComercial::factory()->create(['slug' => 'novo']);
        $finalidade = FinalidadeConsentimento::factory()->create();

        IbgeEstado::query()->updateOrCreate(
            ['id' => 43],
            [
                'txt_uf' => 'Rio Grande do Sul',
                'txt_sigla_uf' => 'RS',
            ],
        );
        IbgeMunicipio::query()->updateOrCreate(
            ['id' => 4305108],
            [
                'txt_nome_municipios' => 'Caxias do Sul',
                'cod_municipio_6dig' => 430510,
                'estado_id' => 43,
            ],
        );
        Uf::query()->firstOrCreate(
            ['sigla' => 'RS'],
            ['nome' => 'Rio Grande do Sul', 'ordem' => 21],
        );

        $response = $this->actingAs($user)
            ->post(route('contatos.store'), [
                'nome' => 'Maria Nova',
                'cargo' => 'Sócia',
                'email' => 'maria.nova@email.test',
                'telefone' => '51999998888',
                'cpf' => '123.456.789-00',
                'tipo_pessoa_id' => $tipo->id,
                'empresa_id' => null,
                'municipio_id' => 4305108,
                'canal_contato_id' => $canal->id,
                'status_consentimento_id' => $status->id,
                'status_comercial_id' => $statusComercial->id,
                'registro_mesclado' => false,
                'observacao_deduplicacao' => null,
                'consentimentos' => [
                    [
                        'finalidade_consentimento_id' => $finalidade->id,
                        'status_consentimento_id' => $status->id,
                        'concedido_em' => null,
                        'revogado_em' => null,
                    ],
                ],
            ]);

        $response->assertRedirect(route('contatos.index'));

        $contato = Contato::query()->where('email', 'maria.nova@email.test')->first();

        $this->assertNotNull($contato);
        $this->assertSame('Maria Nova', $contato->nome);
        $this->assertSame('maria.nova@email.test', $contato->email);
        $this->assertSame('51999998888', $contato->telefone);
        $this->assertSame('12345678900', $contato->cpf);
        $this->assertSame($user->tenant_id, $contato->tenant_id);
        $this->assertSame('Caxias do Sul', $contato->cidade);
        $this->assertSame(1, $contato->consentimentos()->count());
    }

    public function test_create_page_prefills_empresa_from_query(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create();
        TipoPessoa::factory()->create();
        CanalContato::factory()->create();
        StatusConsentimento::factory()->create(['slug' => 'nao-concedido']);
        StatusComercial::factory()->create(['slug' => 'novo']);
        FinalidadeConsentimento::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('contatos.create', ['empresa_id' => $empresa->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/ContatosCreate')
                ->where('defaults.empresa_id', $empresa->id)
                ->where('defaults.return_to_empresa', true));
    }

    public function test_can_store_contact_and_return_to_empresa_edit(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create();
        $tipo = TipoPessoa::factory()->create(['slug' => 'pf']);
        $canal = CanalContato::factory()->create();
        $status = StatusConsentimento::factory()->create(['slug' => 'nao-concedido']);
        $statusComercial = StatusComercial::factory()->create(['slug' => 'novo']);
        $finalidade = FinalidadeConsentimento::factory()->create();

        $this->actingAs($user)
            ->post(route('contatos.store'), [
                'nome' => 'Lead Vinculado',
                'cargo' => 'Diretor',
                'email' => 'lead.vinculado@email.test',
                'telefone' => '61988887777',
                'cpf' => null,
                'tipo_pessoa_id' => $tipo->id,
                'empresa_id' => $empresa->id,
                'municipio_id' => null,
                'canal_contato_id' => $canal->id,
                'status_consentimento_id' => $status->id,
                'status_comercial_id' => $statusComercial->id,
                'return_to_empresa' => true,
                'consentimentos' => [
                    [
                        'finalidade_consentimento_id' => $finalidade->id,
                        'status_consentimento_id' => $status->id,
                        'concedido_em' => null,
                        'revogado_em' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('empresas.edit', $empresa));

        $this->assertDatabaseHas('contatos', [
            'email' => 'lead.vinculado@email.test',
            'empresa_id' => $empresa->id,
        ]);
    }

    public function test_guest_cannot_create_a_contact(): void
    {
        $this->get(route('contatos.create'))->assertRedirect(route('login'));
        $this->post(route('contatos.store'), [])->assertRedirect(route('login'));
    }
}
