<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\FinalidadeConsentimento;
use App\Models\IbgeMunicipio;
use App\Models\Negociacao;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\Uf;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContatoUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{finalidade_consentimento_id: int, status_consentimento_id: int, concedido_em: ?string, revogado_em: ?string}>
     */
    private function consentimentosPayload(Contato $contato, ?int $statusId = null): array
    {
        $statusId ??= $contato->status_consentimento_id;

        return FinalidadeConsentimento::query()
            ->orderBy('ordem')
            ->get()
            ->map(fn (FinalidadeConsentimento $finalidade): array => [
                'finalidade_consentimento_id' => $finalidade->id,
                'status_consentimento_id' => $statusId,
                'concedido_em' => '2026-08-04',
                'revogado_em' => null,
            ])
            ->values()
            ->all();
    }

    public function test_guest_cannot_edit_a_contact(): void
    {
        $contato = Contato::factory()->pessoaFisica()->create();

        $this->get($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_update_a_contact(): void
    {
        $contato = Contato::factory()->pessoaFisica()->create();

        $this->patch($this->tenantUrl('contatos.update', ['contato' => $contato]), [
            'nome' => 'Nome Alterado',
            'tipo_pessoa_id' => $contato->tipo_pessoa_id,
            'canal_contato_id' => $contato->canal_contato_id,
            'status_consentimento_id' => $contato->status_consentimento_id,
            'status_comercial_id' => $contato->status_comercial_id,
            'registro_mesclado' => false,
            'consentimentos' => $this->consentimentosPayload($contato),
        ])->assertRedirect(route('login'));
    }

    public function test_edit_page_loads_when_tenant_is_resolved_from_auth_user(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create([
            'nome' => 'Contato Com Tenant Limpo',
        ]);

        // Simulate a fresh HTTP request: tenant must come from ResolveTenant,
        // not from TestCase::setUp().
        app(CurrentTenant::class)->set(null);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('contatos.edit', $contato))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/ContatosEdit')
                ->where('contato.id', $contato->id)
                ->where('contato.nome', 'Contato Com Tenant Limpo'));
    }

    public function test_edit_page_loads_contact_with_foreign_key_options_and_finalidades(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create(['nome' => 'Metalúrgica Verano']);
        $contato = Contato::factory()->create([
            'nome' => 'Diego Alencar',
            'cargo' => 'Diretor jurídico',
            'email' => 'diego@verano.com',
            'telefone' => '61988887777',
            'empresa_id' => $empresa->id,
        ]);

        FinalidadeConsentimento::factory()->create(['nome' => 'Newsletter', 'slug' => 'newsletter-test']);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/ContatosEdit')
                ->where('contato.id', $contato->id)
                ->where('contato.nome', 'Diego Alencar')
                ->where('contato.empresa_id', $empresa->id)
                ->has('contato.consentimentos')
                ->has('contato.negociacoes', 0)
                ->has('opcoes.tiposPessoa')
                ->has('opcoes.empresas')
                ->has('opcoes.canais')
                ->has('opcoes.statusConsentimentos')
                ->has('opcoes.statusComerciais')
                ->has('opcoes.estados'));
    }

    public function test_edit_page_includes_negociacao_links_when_contact_has_deals(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $negociacao = Negociacao::factory()->create([
            'contato_id' => $contato->id,
            'assunto' => 'Compliance trabalhista',
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/ContatosEdit')
                ->has('contato.negociacoes', 1)
                ->where('contato.negociacoes.0.id', $negociacao->id)
                ->where('contato.negociacoes.0.titulo', 'Compliance trabalhista'));
    }

    public function test_authenticated_user_can_update_a_contact_and_consent_finalidades(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create([
            'nome' => 'Nome Antigo',
            'email' => 'antigo@email.com',
            'empresa_id' => null,
        ]);
        $novoCanal = CanalContato::factory()->create();
        $novoStatus = StatusConsentimento::factory()->create();
        $tipoPj = TipoPessoa::query()->firstOrCreate(
            ['slug' => 'pj'],
            ['nome' => 'Pessoa jurídica', 'ordem' => 2],
        );
        $empresa = Empresa::factory()->create();
        $finalidade = FinalidadeConsentimento::factory()->create([
            'slug' => 'contato-comercial',
            'nome' => 'Contato comercial',
        ]);

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
            ->from($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->patch($this->tenantUrl('contatos.update', ['contato' => $contato]), [
                'nome' => 'Nome Novo',
                'cargo' => 'Sócia',
                'email' => 'novo@email.com',
                'telefone' => '61999990000',
                'cpf' => '123.456.789-00',
                'tipo_pessoa_id' => $tipoPj->id,
                'empresa_id' => $empresa->id,
                'municipio_id' => 4314902,
                'canal_contato_id' => $novoCanal->id,
                'status_consentimento_id' => $novoStatus->id,
                'status_comercial_id' => $contato->status_comercial_id,
                'registro_mesclado' => true,
                'observacao_deduplicacao' => 'unificado',
                'consentimentos' => [
                    [
                        'finalidade_consentimento_id' => $finalidade->id,
                        'status_consentimento_id' => $novoStatus->id,
                        'concedido_em' => '2026-08-01',
                        'revogado_em' => null,
                    ],
                ],
            ])
            ->assertRedirect($this->tenantUrl('contatos.edit', ['contato' => $contato]));

        $contato->refresh();

        $this->assertSame('Nome Novo', $contato->nome);
        $this->assertSame($empresa->id, $contato->empresa_id);
        $this->assertTrue($contato->registro_mesclado);
        $this->assertSame('Porto Alegre', $contato->cidade);
        $this->assertSame(4314902, $contato->municipio_id);
        $this->assertSame('RS', $contato->uf->sigla);

        $consentimento = ConsentimentoContato::query()
            ->where('contato_id', $contato->id)
            ->where('finalidade_consentimento_id', $finalidade->id)
            ->first();

        $this->assertNotNull($consentimento);
        $this->assertSame($novoStatus->id, $consentimento->status_consentimento_id);
        $this->assertSame('2026-08-01', $consentimento->concedido_em?->format('Y-m-d'));
    }

    public function test_contact_can_be_detached_from_company(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->create();
        $finalidade = FinalidadeConsentimento::factory()->create();

        $this->assertNotNull($contato->empresa_id);

        $this->actingAs($user)
            ->patch($this->tenantUrl('contatos.update', ['contato' => $contato]), [
                'nome' => $contato->nome,
                'email' => $contato->email,
                'tipo_pessoa_id' => $contato->tipo_pessoa_id,
                'empresa_id' => null,
                'canal_contato_id' => $contato->canal_contato_id,
                'status_consentimento_id' => $contato->status_consentimento_id,
                'status_comercial_id' => $contato->status_comercial_id,
                'registro_mesclado' => false,
                'consentimentos' => [
                    [
                        'finalidade_consentimento_id' => $finalidade->id,
                        'status_consentimento_id' => $contato->status_consentimento_id,
                        'concedido_em' => null,
                        'revogado_em' => null,
                    ],
                ],
            ])
            ->assertRedirect($this->tenantUrl('contatos.edit', ['contato' => $contato]));

        $this->assertNull($contato->fresh()->empresa_id);
    }

    public function test_contact_update_requires_a_name(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create();
        $finalidade = FinalidadeConsentimento::factory()->create();

        $this->actingAs($user)
            ->from($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->patch($this->tenantUrl('contatos.update', ['contato' => $contato]), [
                'nome' => '',
                'tipo_pessoa_id' => $contato->tipo_pessoa_id,
                'canal_contato_id' => $contato->canal_contato_id,
                'status_consentimento_id' => $contato->status_consentimento_id,
                'status_comercial_id' => $contato->status_comercial_id,
                'registro_mesclado' => false,
                'consentimentos' => [
                    [
                        'finalidade_consentimento_id' => $finalidade->id,
                        'status_consentimento_id' => $contato->status_consentimento_id,
                        'concedido_em' => null,
                        'revogado_em' => null,
                    ],
                ],
            ])
            ->assertRedirect($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->assertSessionHasErrors('nome');
    }

    public function test_contact_update_rejects_cpf_already_used_in_the_same_tenant(): void
    {
        $user = User::factory()->create();
        Contato::factory()->pessoaFisica()->create(['cpf' => '111.222.333-44']);
        $contato = Contato::factory()->pessoaFisica()->create(['cpf' => '555.666.777-88']);
        $finalidade = FinalidadeConsentimento::factory()->create();

        $this->actingAs($user)
            ->from($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->patch($this->tenantUrl('contatos.update', ['contato' => $contato]), [
                'nome' => $contato->nome,
                'email' => $contato->email,
                'cpf' => '111.222.333-44',
                'tipo_pessoa_id' => $contato->tipo_pessoa_id,
                'canal_contato_id' => $contato->canal_contato_id,
                'status_consentimento_id' => $contato->status_consentimento_id,
                'status_comercial_id' => $contato->status_comercial_id,
                'registro_mesclado' => false,
                'consentimentos' => [
                    [
                        'finalidade_consentimento_id' => $finalidade->id,
                        'status_consentimento_id' => $contato->status_consentimento_id,
                        'concedido_em' => null,
                        'revogado_em' => null,
                    ],
                ],
            ])
            ->assertRedirect($this->tenantUrl('contatos.edit', ['contato' => $contato]))
            ->assertSessionHasErrors('cpf');
    }
}
