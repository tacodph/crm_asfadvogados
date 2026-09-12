<?php

namespace Tests\Feature;

use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CapturarLeadTrafegoTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();

        $this->token = $this->tenant->gerarTokenLeadTrafego();

        TipoPessoa::factory()->create(['slug' => 'pf']);
        CanalContato::factory()->create(['slug' => 'site']);
        StatusConsentimento::factory()->create(['slug' => 'opt-in-registrado']);
        StatusComercial::factory()->create(['slug' => 'novo']);
        FinalidadeConsentimento::factory()->create(['slug' => 'contato-comercial']);
        User::factory()->create();

        $funil = Funil::factory()->create(['nome' => 'Site', 'ordem' => 1]);
        EtapaFunil::factory()->for($funil)->create(['ordem' => 1, 'nome' => 'Novo lead']);

        MetaConversaoConfig::factory()->create([
            'ativo' => true,
            'finalidade_consentimento_slug' => 'contato-comercial',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return [
            'nome' => 'Marina Yoshida',
            'email' => 'marina@exemplo.com',
            'finalidade' => 'contato-comercial',
            'consentimento' => true,
            ...$overrides,
        ];
    }

    private function enviar(array $payload, ?string $token = null): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.($token ?? $this->token)])
            ->postJson(route('api.trafego.leads'), $payload);
    }

    public function test_missing_or_invalid_token_is_rejected(): void
    {
        $this->postJson(route('api.trafego.leads'), $this->payload())->assertUnauthorized();
        $this->enviar($this->payload(), 'asf_naovale')->assertUnauthorized();

        $this->assertSame(0, Contato::query()->count());
    }

    public function test_creates_contato_consent_and_negociacao_with_pending_lead(): void
    {
        $response = $this->enviar($this->payload([
            'meta_event_id' => 'lead_abc',
            'meta_fbp' => 'fb.1.1.1',
            'utm_campaign' => 'concursos-2026',
            'utm_content' => '120210000000000000',
        ]));

        $response->assertCreated()->assertJson(['ok' => true]);

        $contato = Contato::query()->firstOrFail();
        $this->assertSame('marina@exemplo.com', $contato->email);

        $this->assertDatabaseHas('consentimentos_contato', [
            'contato_id' => $contato->id,
            'revogado_em' => null,
        ]);

        $negociacao = Negociacao::query()->firstOrFail();
        $this->assertSame('lead_abc', $negociacao->meta_event_id);
        $this->assertSame('fb.1.1.1', $negociacao->meta_fbp);
        $this->assertSame('site', $negociacao->origem_utm['fonte'] ?? null);
        $this->assertSame('concursos-2026', $negociacao->origem_utm['utm_campaign'] ?? null);

        $this->assertDatabaseHas('meta_conversao_eventos', [
            'negociacao_id' => $negociacao->id,
            'event_name' => 'Lead',
            'status' => 'pendente',
        ]);
        Queue::assertPushed(EnviarEventoConversaoMeta::class);
    }

    public function test_existing_contato_is_reused_not_duplicated(): void
    {
        $contato = Contato::factory()->create(['email' => 'marina@exemplo.com', 'cidade' => null]);

        $this->enviar($this->payload(['cidade' => 'Curitiba', 'uf' => 'PR']))->assertCreated();

        $this->assertSame(1, Contato::query()->count());
        $this->assertSame('Curitiba', $contato->fresh()->cidade);
    }

    public function test_server_to_server_client_hints_are_used(): void
    {
        $this->enviar($this->payload([
            'client_ip_address' => '203.0.113.7',
            'client_user_agent' => 'Mozilla/5.0 (lead)',
        ]))->assertCreated();

        $negociacao = Negociacao::query()->firstOrFail();
        $this->assertSame('203.0.113.7', $negociacao->meta_client_ip);
        $this->assertSame('Mozilla/5.0 (lead)', $negociacao->meta_client_user_agent);
    }

    public function test_rejects_missing_consent_or_unknown_finalidade(): void
    {
        $this->enviar($this->payload(['consentimento' => false]))->assertStatus(422);
        $this->enviar($this->payload(['finalidade' => 'inexistente']))->assertStatus(422);
        $this->enviar($this->payload(['email' => null, 'telefone' => null]))->assertStatus(422);

        $this->assertSame(0, Negociacao::query()->count());
    }

    public function test_token_is_scoped_to_its_tenant(): void
    {
        $outro = $this->createTenant();
        $tokenOutro = $outro->gerarTokenLeadTrafego();

        $this->asTenant($outro, function (): void {
            // TipoPessoa é catálogo global — já criado no setUp.
            CanalContato::factory()->create(['slug' => 'site']);
            StatusConsentimento::factory()->create(['slug' => 'opt-in-registrado']);
            StatusComercial::factory()->create(['slug' => 'novo']);
            FinalidadeConsentimento::factory()->create(['slug' => 'contato-comercial']);
            User::factory()->create();
            $funil = Funil::factory()->create(['ordem' => 1]);
            EtapaFunil::factory()->for($funil)->create(['ordem' => 1]);
        });

        $this->enviar($this->payload(['email' => 'outro@exemplo.com']), $tokenOutro)->assertCreated();

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Contato::query()->where('email', 'outro@exemplo.com')->count()));
        $this->assertSame(1, $this->asTenant($outro, fn () => Contato::query()->where('email', 'outro@exemplo.com')->count()));
    }
}
