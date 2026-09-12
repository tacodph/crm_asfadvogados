<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\FinalidadeConsentimento;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContatoDeduplicacaoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{finalidade_consentimento_id: int, status_consentimento_id: int, concedido_em: null, revogado_em: null}>
     */
    private function consentimentosPayload(int $statusId): array
    {
        return FinalidadeConsentimento::query()
            ->orderBy('ordem')
            ->get()
            ->map(fn (FinalidadeConsentimento $finalidade): array => [
                'finalidade_consentimento_id' => $finalidade->id,
                'status_consentimento_id' => $statusId,
                'concedido_em' => null,
                'revogado_em' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{tipo: TipoPessoa, canal: CanalContato, status: StatusConsentimento, statusComercial: StatusComercial}
     */
    private function catalogos(): array
    {
        return [
            'tipo' => TipoPessoa::factory()->create(['slug' => 'pf']),
            'canal' => CanalContato::factory()->create(),
            'status' => StatusConsentimento::factory()->create(['slug' => 'nao-concedido']),
            'statusComercial' => StatusComercial::factory()->create(['slug' => 'novo']),
        ];
    }

    public function test_preview_returns_matches_by_email_telefone_and_cpf(): void
    {
        $user = User::factory()->create();
        $existente = Contato::factory()->pessoaFisica()->create([
            'nome' => 'Ana Existente',
            'email' => 'ana@escritorio.test',
            'telefone' => '(54) 99712-4408',
            'cpf' => '123.456.789-00',
        ]);

        $this->actingAs($user)
            ->getJson(route('contatos.duplicatas', [
                'email' => 'ANA@escritorio.test',
                'telefone' => '54997124408',
                'cpf' => '12345678900',
            ]))
            ->assertOk()
            ->assertJsonCount(3, 'duplicatas')
            ->assertJsonFragment(['campo' => 'email'])
            ->assertJsonFragment(['campo' => 'telefone'])
            ->assertJsonFragment(['campo' => 'cpf'])
            ->assertJsonFragment(['id' => $existente->id]);
    }

    public function test_preview_ignores_the_contact_being_edited(): void
    {
        $user = User::factory()->create();
        $contato = Contato::factory()->pessoaFisica()->create([
            'email' => 'mesmo@email.test',
            'telefone' => '51988887777',
            'cpf' => '11122233344',
        ]);

        $this->actingAs($user)
            ->getJson(route('contatos.duplicatas', [
                'email' => 'mesmo@email.test',
                'ignore' => $contato->id,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'duplicatas');
    }

    public function test_store_blocks_duplicate_email_and_normalizes_identifiers(): void
    {
        $user = User::factory()->create();
        ['tipo' => $tipo, 'canal' => $canal, 'status' => $status, 'statusComercial' => $statusComercial] = $this->catalogos();
        FinalidadeConsentimento::factory()->create();

        Contato::factory()->pessoaFisica()->create([
            'email' => 'duplicado@email.test',
            'telefone' => '51999990000',
            'cpf' => '55566677788',
        ]);

        $this->actingAs($user)
            ->from(route('contatos.create'))
            ->post(route('contatos.store'), [
                'nome' => 'Novo Contato',
                'email' => 'Duplicado@Email.TEST',
                'telefone' => '(51) 99999-0001',
                'cpf' => '999.888.777-66',
                'tipo_pessoa_id' => $tipo->id,
                'canal_contato_id' => $canal->id,
                'status_consentimento_id' => $status->id,
                'status_comercial_id' => $statusComercial->id,
                'consentimentos' => $this->consentimentosPayload($status->id),
            ])
            ->assertRedirect(route('contatos.create'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('contatos', ['nome' => 'Novo Contato']);
    }

    public function test_store_blocks_duplicate_telefone_even_when_formatted_differently(): void
    {
        $user = User::factory()->create();
        ['tipo' => $tipo, 'canal' => $canal, 'status' => $status, 'statusComercial' => $statusComercial] = $this->catalogos();
        FinalidadeConsentimento::factory()->create();

        Contato::factory()->pessoaFisica()->create([
            'email' => 'outro@email.test',
            'telefone' => '(54) 99712-4408',
            'cpf' => null,
        ]);

        $this->actingAs($user)
            ->from(route('contatos.create'))
            ->post(route('contatos.store'), [
                'nome' => 'Telefone Duplicado',
                'email' => 'unico@email.test',
                'telefone' => '54997124408',
                'cpf' => null,
                'tipo_pessoa_id' => $tipo->id,
                'canal_contato_id' => $canal->id,
                'status_consentimento_id' => $status->id,
                'status_comercial_id' => $statusComercial->id,
                'consentimentos' => $this->consentimentosPayload($status->id),
            ])
            ->assertRedirect(route('contatos.create'))
            ->assertSessionHasErrors('telefone');
    }

    public function test_update_blocks_duplicate_identifiers_of_another_contact(): void
    {
        $user = User::factory()->create();
        FinalidadeConsentimento::factory()->create();

        $alvo = Contato::factory()->pessoaFisica()->create([
            'email' => 'alvo@email.test',
            'telefone' => '51911112222',
            'cpf' => '10120230340',
        ]);
        Contato::factory()->pessoaFisica()->create([
            'email' => 'ocupado@email.test',
            'telefone' => '51933334444',
            'cpf' => '40450560670',
        ]);

        $this->actingAs($user)
            ->from(route('contatos.edit', $alvo))
            ->patch(route('contatos.update', $alvo), [
                'nome' => $alvo->nome,
                'email' => 'ocupado@email.test',
                'telefone' => $alvo->telefone,
                'cpf' => $alvo->cpf,
                'tipo_pessoa_id' => $alvo->tipo_pessoa_id,
                'canal_contato_id' => $alvo->canal_contato_id,
                'status_consentimento_id' => $alvo->status_consentimento_id,
                'status_comercial_id' => $alvo->status_comercial_id,
                'registro_mesclado' => false,
                'observacao_deduplicacao' => null,
                'consentimentos' => $this->consentimentosPayload($alvo->status_consentimento_id),
            ])
            ->assertRedirect(route('contatos.edit', $alvo))
            ->assertSessionHasErrors('email');
    }

    public function test_store_persists_normalized_identifiers(): void
    {
        $user = User::factory()->create();
        ['tipo' => $tipo, 'canal' => $canal, 'status' => $status, 'statusComercial' => $statusComercial] = $this->catalogos();
        FinalidadeConsentimento::factory()->create();

        $this->actingAs($user)
            ->post(route('contatos.store'), [
                'nome' => 'Normalizado',
                'email' => '  Maria.Nova@Email.TEST ',
                'telefone' => '(51) 98888-7777',
                'cpf' => '123.456.789-00',
                'tipo_pessoa_id' => $tipo->id,
                'canal_contato_id' => $canal->id,
                'status_consentimento_id' => $status->id,
                'status_comercial_id' => $statusComercial->id,
                'consentimentos' => $this->consentimentosPayload($status->id),
            ])
            ->assertRedirect(route('contatos.index'));

        $this->assertDatabaseHas('contatos', [
            'nome' => 'Normalizado',
            'email' => 'maria.nova@email.test',
            'telefone' => '51988887777',
            'cpf' => '12345678900',
            'registro_mesclado' => false,
        ]);
    }

    public function test_contacts_index_exposes_dedupe_mesclado_flag(): void
    {
        $user = User::factory()->create();
        Contato::factory()->pessoaFisica()->create([
            'nome' => 'Mesclado',
            'registro_mesclado' => true,
            'observacao_deduplicacao' => 'WhatsApp + formulário',
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('contatos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('crm/Contatos')
                ->where('contatos.0.dedupeMesclado', true)
                ->where('contatos.0.dedupe', 'WhatsApp + formulário'));
    }

    public function test_guest_cannot_preview_duplicatas(): void
    {
        $this->getJson(route('contatos.duplicatas', ['email' => 'a@b.test']))
            ->assertRedirect(route('login'));
    }
}
