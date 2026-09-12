<?php

namespace Tests\Feature;

use App\Actions\Meta\RegistrarEventoConversaoMeta;
use App\Enums\MetaConversaoEventoStatus;
use App\Enums\MetaEventName;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use App\Models\Negociacao;
use App\Models\StatusConsentimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistrarEventoConversaoMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function agir(Negociacao $negociacao, ?string $slug = null): ?MetaConversaoEvento
    {
        return app(RegistrarEventoConversaoMeta::class)($negociacao, MetaEventName::Lead, $slug);
    }

    /**
     * Cria negociação ANTES de qualquer config (observer é no-op), depois monta
     * a campanha. Devolve [negociacao, contato].
     *
     * @return array{0: Negociacao, 1: Contato}
     */
    private function cenario(string $nomeFunil = 'Bancário'): array
    {
        $contato = Contato::factory()->create(['email' => 'lead@exemplo.com', 'telefone' => '11988887777']);
        $funil = Funil::factory()->create(['nome' => $nomeFunil]);
        $negociacao = Negociacao::factory()->create(['funil_id' => $funil->id, 'contato_id' => $contato->id]);

        return [$negociacao, $contato];
    }

    private function concederConsentimento(Contato $contato, string $finalidade = 'marketing'): void
    {
        ConsentimentoContato::factory()->create([
            'contato_id' => $contato->id,
            'finalidade_consentimento_id' => FinalidadeConsentimento::factory()->create(['slug' => $finalidade])->id,
            'status_consentimento_id' => StatusConsentimento::factory()->create(['slug' => 'vigente'])->id,
        ]);
    }

    public function test_with_consent_creates_pending_row_and_dispatches(): void
    {
        [$negociacao, $contato] = $this->cenario();
        MetaConversaoConfig::factory()->bancario()->create();
        $this->concederConsentimento($contato);

        $evento = $this->agir($negociacao);

        $this->assertSame(MetaConversaoEventoStatus::Pendente, $evento?->status);
        $this->assertSame('bancario', $evento->config->slug);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $evento->request_payload['user_data']['em'][0],
        );
        $this->assertStringNotContainsString('lead@exemplo.com', json_encode($evento->request_payload) ?: '');
        Queue::assertPushed(EnviarEventoConversaoMeta::class, 1);
    }

    public function test_without_consent_is_discarded_without_pii_or_job(): void
    {
        [$negociacao, $contato] = $this->cenario();
        MetaConversaoConfig::factory()->bancario()->create();

        $evento = $this->agir($negociacao);

        $this->assertSame(MetaConversaoEventoStatus::Descartado, $evento?->status);
        $this->assertSame('sem_consentimento', $evento->motivo_descarte);
        $this->assertArrayNotHasKey('user_data', $evento->request_payload);
        Queue::assertNothingPushed();
    }

    public function test_is_idempotent(): void
    {
        [$negociacao, $contato] = $this->cenario();
        MetaConversaoConfig::factory()->bancario()->create();
        $this->concederConsentimento($contato);

        $primeiro = $this->agir($negociacao);
        $segundo = $this->agir($negociacao);

        $this->assertTrue($primeiro?->is($segundo));
        $this->assertSame(1, MetaConversaoEvento::query()->where('negociacao_id', $negociacao->id)->count());
        Queue::assertPushed(EnviarEventoConversaoMeta::class, 1);
    }

    public function test_redispatches_when_existing_row_is_erro(): void
    {
        [$negociacao, $contato] = $this->cenario();
        $config = MetaConversaoConfig::factory()->bancario()->create();
        $this->concederConsentimento($contato);

        $linha = $this->agir($negociacao);
        $linha->update(['status' => MetaConversaoEventoStatus::Erro]);
        Queue::fake(); // zera o contador

        $this->agir($negociacao);

        Queue::assertPushed(EnviarEventoConversaoMeta::class, 1);
    }

    public function test_inactive_campaign_is_discarded(): void
    {
        [$negociacao, $contato] = $this->cenario('Genérico');
        MetaConversaoConfig::factory()->bancario()->inativa()->create();
        $this->concederConsentimento($contato);

        $evento = $this->agir($negociacao, 'bancario');

        $this->assertSame(MetaConversaoEventoStatus::Descartado, $evento?->status);
        $this->assertSame('campanha_inativa', $evento->motivo_descarte);
    }

    public function test_no_campaign_configured_is_a_silent_noop(): void
    {
        [$negociacao] = $this->cenario();

        $this->assertNull($this->agir($negociacao));
        $this->assertSame(0, MetaConversaoEvento::query()->count());
    }
}
