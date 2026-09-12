<?php

namespace Tests\Feature;

use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaCapiExpurgarPayloadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['meta.capi.retencao_dias' => 180]);
    }

    public function test_zera_payloads_antigos_preservando_metadados(): void
    {
        $config = MetaConversaoConfig::factory()->create();

        $antigo = MetaConversaoEvento::factory()->enviado()->create([
            'meta_conversao_config_id' => $config->id,
            'request_payload' => ['event_name' => 'Lead', 'user_data' => ['em' => ['abc']]],
            'response_body' => ['events_received' => 1],
            'fbtrace_id' => 'trace-preservado',
            'created_at' => now()->subDays(200),
        ]);

        $recente = MetaConversaoEvento::factory()->enviado()->create([
            'meta_conversao_config_id' => $config->id,
            'request_payload' => ['event_name' => 'Lead', 'user_data' => ['em' => ['xyz']]],
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('meta:capi-expurgar-payloads')->assertSuccessful();

        $antigo->refresh();
        $this->assertSame([], $antigo->request_payload);
        $this->assertNull($antigo->response_body);
        $this->assertSame('trace-preservado', $antigo->fbtrace_id);
        $this->assertSame('enviado', $antigo->status->value);
        $this->assertSame(1, $antigo->events_received);

        $recente->refresh();
        $this->assertNotSame([], $recente->request_payload);
    }

    public function test_nao_toca_eventos_ja_expurgados(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        $evento = MetaConversaoEvento::factory()->enviado()->create([
            'meta_conversao_config_id' => $config->id,
            'request_payload' => [],
            'response_body' => null,
            'created_at' => now()->subDays(300),
            'updated_at' => now()->subDays(300),
        ]);

        $this->artisan('meta:capi-expurgar-payloads')->assertSuccessful();

        $this->assertTrue($evento->fresh()->updated_at->isBefore(now()->subDays(200)));
    }
}
