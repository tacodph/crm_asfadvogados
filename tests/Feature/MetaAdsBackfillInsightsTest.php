<?php

namespace Tests\Feature;

use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MetaAdsBackfillInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_falha_sem_desde(): void
    {
        $this->artisan('meta:ads-backfill-insights')->assertFailed();
    }

    public function test_recarrega_janela_pequena_de_forma_idempotente(): void
    {
        MetaAdsConta::factory()->create(['ad_account_id' => '111']);

        Http::fake([
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [['permission' => 'ads_read', 'status' => 'granted']]]),
            'graph.facebook.com/*/act_*/insights*' => Http::response(['data' => [
                ['ad_id' => 'ad_1', 'date_start' => '2026-06-01', 'spend' => '10.00', 'actions' => [['action_type' => 'lead', 'value' => '2']]],
                ['ad_id' => 'ad_1', 'date_start' => '2026-06-02', 'spend' => '8.00', 'actions' => [['action_type' => 'lead', 'value' => '1']]],
            ]]),
            'graph.facebook.com/*' => Http::response(['id' => '1', 'currency' => 'BRL']),
        ]);

        $this->artisan('meta:ads-backfill-insights --desde=2026-06-01 --ate=2026-06-05 --force')->assertSuccessful();
        $this->assertSame(2, MetaAdsInsightDiario::query()->count());
        $this->assertSame(1000, (int) MetaAdsInsightDiario::query()
            ->where('objeto_id', 'ad_1')->whereDate('referencia', '2026-06-01')->value('investimento_centavos'));

        $this->artisan('meta:ads-backfill-insights --desde=2026-06-01 --ate=2026-06-05 --force')->assertSuccessful();
        $this->assertSame(2, MetaAdsInsightDiario::query()->count());
    }

    public function test_janela_grande_usa_o_relatorio_assincrono(): void
    {
        MetaAdsConta::factory()->create(['ad_account_id' => '222']);

        Http::fake([
            'graph.facebook.com/*/run_bf/insights*' => Http::response(['data' => [
                ['ad_id' => 'ad_9', 'date_start' => '2026-04-01', 'spend' => '3.00', 'actions' => []],
            ]]),
            'graph.facebook.com/*/run_bf*' => Http::response(['async_status' => 'Job Completed']),
            'graph.facebook.com/*/act_*/insights*' => Http::response(['report_run_id' => 'run_bf']),
            'graph.facebook.com/*' => Http::response(['id' => '1']),
        ]);

        $this->artisan('meta:ads-backfill-insights --desde=2026-04-01 --ate=2026-06-01 --force')->assertSuccessful();

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/insights'));
        $this->assertSame(1, MetaAdsInsightDiario::query()->count());
    }
}
