<?php

namespace Tests\Feature;

use App\Actions\Meta\RegistrarEventoConversaoMeta;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use App\Models\StatusConsentimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NegociacaoDisparaEventosMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * @return array{0: Negociacao, 1: Funil}
     */
    private function cenarioComConsentimento(): array
    {
        MetaConversaoConfig::factory()->bancario()->create();

        $contato = Contato::factory()->create(['email' => 'lead@exemplo.com']);
        ConsentimentoContato::factory()->create([
            'contato_id' => $contato->id,
            'finalidade_consentimento_id' => FinalidadeConsentimento::factory()->create(['slug' => 'marketing'])->id,
            'status_consentimento_id' => StatusConsentimento::factory()->create(['slug' => 'vigente'])->id,
        ]);

        $funil = Funil::factory()->create(['nome' => 'Bancário']);
        $negociacao = Negociacao::factory()->create(['funil_id' => $funil->id, 'contato_id' => $contato->id]);

        return [$negociacao, $funil];
    }

    public function test_creating_negociacao_emits_lead(): void
    {
        [$negociacao] = $this->cenarioComConsentimento();

        $this->assertDatabaseHas('meta_conversao_eventos', [
            'negociacao_id' => $negociacao->id,
            'event_name' => 'Lead',
            'status' => 'pendente',
        ]);
        Queue::assertPushed(EnviarEventoConversaoMeta::class);
    }

    public function test_advancing_to_meeting_stage_emits_schedule(): void
    {
        [$negociacao, $funil] = $this->cenarioComConsentimento();

        $etapaReuniao = EtapaFunil::factory()->create([
            'funil_id' => $funil->id,
            'nome' => 'Reunião de diagnóstico',
        ]);

        $negociacao->update(['etapa_funil_id' => $etapaReuniao->id]);

        $this->assertDatabaseHas('meta_conversao_eventos', [
            'negociacao_id' => $negociacao->id,
            'event_name' => 'Schedule',
        ]);
    }

    public function test_stage_meta_evento_column_wins_over_name_heuristic(): void
    {
        [$negociacao, $funil] = $this->cenarioComConsentimento();

        $etapa = EtapaFunil::factory()->create([
            'funil_id' => $funil->id,
            'nome' => 'Etapa com nome qualquer',
            'meta_evento' => 'Purchase',
        ]);

        $negociacao->update(['etapa_funil_id' => $etapa->id]);

        $this->assertDatabaseHas('meta_conversao_eventos', [
            'negociacao_id' => $negociacao->id,
            'event_name' => 'Purchase',
        ]);
    }

    public function test_stage_change_without_mapping_emits_nothing_new(): void
    {
        [$negociacao, $funil] = $this->cenarioComConsentimento();

        $outra = EtapaFunil::factory()->create(['funil_id' => $funil->id, 'nome' => 'Qualificação']);
        $negociacao->update(['etapa_funil_id' => $outra->id]);

        $this->assertDatabaseMissing('meta_conversao_eventos', [
            'negociacao_id' => $negociacao->id,
            'event_name' => 'Schedule',
        ]);
    }

    public function test_action_failure_never_breaks_the_save(): void
    {
        $this->app->bind(RegistrarEventoConversaoMeta::class, fn () => new class
        {
            public function __invoke(): void
            {
                throw new RuntimeException('integração fora do ar');
            }
        });

        $negociacao = Negociacao::factory()->create();

        $this->assertModelExists($negociacao);
    }
}
