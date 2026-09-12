<?php

namespace Tests\Feature;

use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAdsExpurgoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['meta.capi.retencao_dias' => 180]);
    }

    public function test_zera_brutos_antigos_preservando_os_agregados(): void
    {
        $conta = MetaAdsConta::factory()->create();
        $anuncio = MetaAdsAnuncio::factory()->create(['meta_ads_conta_id' => $conta->id]);

        $antigo = MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->create([
            'meta_ads_conta_id' => $conta->id,
            'investimento_centavos' => 4200,
            'resultados' => 5,
            'acoes' => [['action_type' => 'lead', 'value' => '5']],
            'bruto' => ['spend' => '42.00', 'foo' => 'bar'],
            'created_at' => now()->subDays(200),
        ]);

        $recente = MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->create([
            'meta_ads_conta_id' => $conta->id,
            'bruto' => ['spend' => '1.00'],
            'created_at' => now()->subDays(10),
        ]);

        $anuncioAntigo = MetaAdsAnuncio::factory()->create([
            'meta_ads_conta_id' => $conta->id,
            'bruto' => ['id' => 'x', 'name' => 'antigo'],
            'created_at' => now()->subDays(300),
        ]);

        $this->artisan('meta:ads-expurgar-brutos')->assertSuccessful();

        $antigo->refresh();
        $this->assertSame([], $antigo->bruto);
        $this->assertNull($antigo->acoes);
        $this->assertSame(4200, $antigo->investimento_centavos);
        $this->assertSame(5, $antigo->resultados);

        $this->assertNotSame([], $recente->fresh()->bruto);
        $this->assertSame([], $anuncioAntigo->fresh()->bruto);
    }

    public function test_e_idempotente(): void
    {
        $conta = MetaAdsConta::factory()->create();
        $anuncio = MetaAdsAnuncio::factory()->create(['meta_ads_conta_id' => $conta->id]);
        MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->create([
            'meta_ads_conta_id' => $conta->id,
            'bruto' => ['x' => 1],
            'created_at' => now()->subDays(300),
        ]);

        $this->artisan('meta:ads-expurgar-brutos')->assertSuccessful();
        $this->artisan('meta:ads-expurgar-brutos')
            ->expectsOutputToContain('0 registro(s)')
            ->assertSuccessful();
    }
}
