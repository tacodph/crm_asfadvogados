<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\StatusAtendimento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegociacaoBuscaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_search(): void
    {
        $this->getJson($this->tenantUrl('negociacoes.busca', ['q' => 'maria']))
            ->assertRedirect(route('login'));
    }

    public function test_finds_by_contact_name_in_any_funil_and_etapa(): void
    {
        $user = User::factory()->create();
        $maria = $this->negociacao(['nome' => 'Maria Souza']);
        $mariana = $this->negociacao(['nome' => 'Mariana Lima']);
        $this->negociacao(['nome' => 'João Pedro']);

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['q' => 'MARIA']))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'resultados')
            ->assertJsonFragment(['id' => $maria->id, 'contatoNome' => 'Maria Souza'])
            ->assertJsonFragment(['id' => $mariana->id]);
    }

    public function test_finds_by_empresa_name_and_assunto(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::factory()->create(['nome' => 'Construtora Horizonte']);
        $porEmpresa = $this->negociacao([], ['empresa_id' => $empresa->id]);
        $porAssunto = $this->negociacao([], ['assunto' => 'Revisão de contrato Horizonte']);

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['q' => 'horizonte']))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['id' => $porEmpresa->id, 'nome' => 'Construtora Horizonte'])
            ->assertJsonFragment(['id' => $porAssunto->id]);
    }

    public function test_finds_by_phone_with_or_without_mask(): void
    {
        $user = User::factory()->create();
        $alvo = $this->negociacao(['telefone' => '(11) 98765-4321']);
        $this->negociacao(['telefone' => '(21) 91234-5678']);

        foreach (['(11) 98765-4321', '987654321', '98765 4321'] as $termo) {
            $this->actingAs($user)
                ->getJson($this->tenantUrl('negociacoes.busca', ['q' => $termo]))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('resultados.0.id', $alvo->id);
        }
    }

    public function test_filters_combine_with_text(): void
    {
        $user = User::factory()->create();
        $outroResponsavel = User::factory()->create();
        $funil = Funil::factory()->create();
        $etapaA = EtapaFunil::factory()->create(['funil_id' => $funil->id, 'ordem' => 1]);
        $etapaB = EtapaFunil::factory()->create(['funil_id' => $funil->id, 'ordem' => 2]);
        $ganho = EtapaFunil::factory()->ganho()->create(['funil_id' => $funil->id, 'ordem' => 3]);
        $status = StatusAtendimento::factory()->create();

        $certa = $this->negociacao(['nome' => 'Ana Clara'], [
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapaA->id,
            'responsavel_user_id' => $user->id,
            'status_atendimento_id' => $status->id,
        ]);
        $this->negociacao(['nome' => 'Ana Beatriz'], ['funil_id' => $funil->id, 'etapa_funil_id' => $etapaB->id, 'responsavel_user_id' => $user->id]);
        $this->negociacao(['nome' => 'Ana Paula'], ['funil_id' => $funil->id, 'etapa_funil_id' => $etapaA->id, 'responsavel_user_id' => $outroResponsavel->id]);
        $ganha = $this->negociacao(['nome' => 'Ana Luiza'], ['funil_id' => $funil->id, 'etapa_funil_id' => $ganho->id, 'responsavel_user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', [
                'q' => 'ana',
                'responsavel_id' => $user->id,
                'etapa_id' => $etapaA->id,
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('resultados.0.id', $certa->id);

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['q' => 'ana', 'resultado' => 'ganho']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('resultados.0.id', $ganha->id)
            ->assertJsonPath('resultados.0.resultado', 'ganho');

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['status_atendimento_id' => $status->id]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('resultados.0.id', $certa->id);
    }

    public function test_short_term_without_filters_returns_nothing(): void
    {
        $user = User::factory()->create();
        $this->negociacao(['nome' => 'Ana']);

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['q' => 'a']))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_like_wildcards_in_the_term_are_literal(): void
    {
        $user = User::factory()->create();
        $this->negociacao(['nome' => 'Carlos Alberto']);
        $comPercentual = $this->negociacao([], ['assunto' => 'Desconto de 100% na taxa']);

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['q' => '%%']))
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['q' => '100%']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('resultados.0.id', $comPercentual->id);
    }

    public function test_does_not_return_other_tenants_negociacoes(): void
    {
        $user = User::factory()->create();
        $this->asTenant($this->createTenant(), fn () => $this->negociacao(['nome' => 'Maria do Outro Tenant']));

        $this->actingAs($user)
            ->getJson($this->tenantUrl('negociacoes.busca', ['q' => 'maria']))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_index_exposes_search_options(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('negociacoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('opcoesBusca.responsaveis')
                ->has('opcoesBusca.statusAtendimentos')
                ->has('opcoesBusca.statusQualificacoes'));
    }

    /**
     * @param  array<string, mixed>  $contato
     * @param  array<string, mixed>  $negociacao
     */
    private function negociacao(array $contato = [], array $negociacao = []): Negociacao
    {
        return Negociacao::factory()->create([
            'contato_id' => Contato::factory()->pessoaFisica()->create($contato)->id,
            ...$negociacao,
        ]);
    }
}
