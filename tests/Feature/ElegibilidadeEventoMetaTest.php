<?php

namespace Tests\Feature;

use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use App\Models\StatusConsentimento;
use App\Support\Meta\ElegibilidadeEventoMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElegibilidadeEventoMetaTest extends TestCase
{
    use RefreshDatabase;

    private function negociacao(string $nomeFunil = 'Bancário'): Negociacao
    {
        $funil = Funil::factory()->create(['nome' => $nomeFunil]);

        return Negociacao::factory()->create([
            'funil_id' => $funil->id,
            'contato_id' => Contato::factory()->create()->id,
        ]);
    }

    private function concederConsentimento(Negociacao $negociacao, string $finalidade): void
    {
        ConsentimentoContato::factory()->create([
            'contato_id' => $negociacao->contato_id,
            'finalidade_consentimento_id' => FinalidadeConsentimento::factory()->create(['slug' => $finalidade])->id,
            'status_consentimento_id' => StatusConsentimento::factory()->create(['slug' => 'vigente'])->id,
        ]);
    }

    public function test_desligado_when_no_active_campaign(): void
    {
        $this->assertSame('desligado', app(ElegibilidadeEventoMeta::class)->estado($this->negociacao()));
    }

    public function test_sem_consentimento_when_campaign_resolves_but_no_consent(): void
    {
        MetaConversaoConfig::factory()->bancario()->create(['finalidade_consentimento_slug' => 'marketing']);

        $this->assertSame('sem_consentimento', app(ElegibilidadeEventoMeta::class)->estado($this->negociacao()));
    }

    public function test_enviavel_when_campaign_and_consent_ok(): void
    {
        MetaConversaoConfig::factory()->bancario()->create(['finalidade_consentimento_slug' => 'marketing']);
        $negociacao = $this->negociacao();
        $this->concederConsentimento($negociacao, 'marketing');

        $this->assertSame('enviavel', app(ElegibilidadeEventoMeta::class)->estado($negociacao->fresh()));
    }

    public function test_campanha_indefinida_when_multiple_active_and_none_matches(): void
    {
        MetaConversaoConfig::factory()->create(['slug' => 'a', 'ativo' => true, 'nome_campanha' => 'A']);
        MetaConversaoConfig::factory()->create(['slug' => 'b', 'ativo' => true, 'nome_campanha' => 'B']);

        $this->assertSame(
            'campanha_indefinida',
            app(ElegibilidadeEventoMeta::class)->estado($this->negociacao('Funil sem palavra-chave')),
        );
    }
}
