<?php

namespace Tests\Feature;

use App\Actions\Meta\SincronizarEstatisticasPixelMeta;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEstatistica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SincronizarEstatisticasPixelMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_upsert_por_dia_nao_duplica(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '1', 'name' => 'Pixel', 'last_fired_time' => '2026-09-01T10:00:00+0000',
            ], 200),
        ]);

        $config = MetaConversaoConfig::factory()->create();
        $action = app(SincronizarEstatisticasPixelMeta::class);

        $primeira = $action($config);
        $segunda = $action($config);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, MetaConversaoEstatistica::query()->count());
        $this->assertNotNull($primeira->pixel_last_fired_at);
    }

    public function test_erro_de_api_nao_lanca_e_grava_o_que_conseguiu(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'quota', 'code' => 4]], 500),
        ]);

        $config = MetaConversaoConfig::factory()->create();

        $estatistica = app(SincronizarEstatisticasPixelMeta::class)($config);

        $this->assertNull($estatistica->pixel_last_fired_at);
        $this->assertSame(1, MetaConversaoEstatistica::query()->count());
        $this->assertArrayHasKey('pixel_ok', $estatistica->payload_bruto);
    }
}
