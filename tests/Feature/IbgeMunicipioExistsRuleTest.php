<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\FinalidadeConsentimento;
use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use App\Models\Uf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class IbgeMunicipioExistsRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_can_be_updated_with_ibge_municipio(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create([
            'email' => 'localidade@email.test',
            'municipio_id' => null,
        ]);
        $finalidade = FinalidadeConsentimento::factory()->create();

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
            ->from(route('contatos.edit', $contato))
            ->patch(route('contatos.update', $contato), [
                'nome' => $contato->nome,
                'email' => $contato->email,
                'telefone' => $contato->telefone,
                'cpf' => $contato->cpf,
                'tipo_pessoa_id' => $contato->tipo_pessoa_id,
                'canal_contato_id' => $contato->canal_contato_id,
                'status_consentimento_id' => $contato->status_consentimento_id,
                'status_comercial_id' => $contato->status_comercial_id,
                'municipio_id' => 4314902,
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
            ->assertRedirect(route('contatos.edit', $contato))
            ->assertSessionHasNoErrors();

        $contato->refresh();

        $this->assertSame(4314902, $contato->municipio_id);
        $this->assertSame('Porto Alegre', $contato->cidade);
        $this->assertSame('RS', $contato->uf->sigla);
    }

    public function test_municipio_exists_rule_does_not_treat_postgres_schema_as_connection(): void
    {
        // Laravel's exists:connection.table parser would treat
        // "base_dados_ibge.tab_municipios" as a missing connection. Using the
        // Eloquent model class keeps schema-qualified tables on the default DB.
        $legacy = (string) Rule::exists('base_dados_ibge.tab_municipios', 'id');
        $fixed = (string) Rule::exists(IbgeMunicipio::class, 'id');

        $this->assertSame('exists:base_dados_ibge.tab_municipios,id', $legacy);
        $this->assertStringNotContainsString('base_dados_ibge.', $fixed);
    }
}
