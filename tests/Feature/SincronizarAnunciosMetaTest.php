<?php

namespace Tests\Feature;

use App\Actions\Meta\SincronizarEstruturaAnunciosMeta;
use App\Actions\Meta\SincronizarInsightsAnunciosMeta;
use App\Jobs\SincronizarEstruturaAnunciosMetaJob;
use App\Jobs\SincronizarInsightsAnunciosMetaJob;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SincronizarAnunciosMetaTest extends TestCase
{
    use RefreshDatabase;

    private MetaAdsConta $conta;

    /** @var array<string, mixed> */
    private array $fixture = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        $this->conta = MetaAdsConta::factory()->create(['ad_account_id' => '123456789']);
    }

    /**
     * Um único stub-closure que lê `$this->fixture` a cada chamada — assim os
     * testes podem mutar a fixture entre rodadas (`Http::fake` merge + first-win
     * não deixa re-stubar o mesmo padrão).
     *
     * @param  array<string, mixed>  $fixture
     */
    private function fakeMeta(array $fixture): void
    {
        $this->fixture = $fixture;

        Http::fake(function (Request $request) {
            $url = $request->url();
            $fx = $this->fixture;

            return match (true) {
                str_contains($url, '/me/permissions') => Http::response([
                    'data' => array_map(
                        fn ($p) => ['permission' => $p, 'status' => 'granted'],
                        $fx['permissoes'] ?? ['ads_read'],
                    ),
                ]),
                str_contains($url, '/campaigns') => Http::response(['data' => $fx['campanhas'] ?? []]),
                str_contains($url, '/adsets') => Http::response(['data' => $fx['adsets'] ?? []]),
                str_contains($url, '/insights') => Http::response(['data' => $fx['insights'] ?? []]),
                str_contains($url, '/ads') => Http::response(['data' => $fx['ads'] ?? []]),
                default => Http::response([
                    'id' => 'act_123456789', 'name' => 'ASF', 'currency' => 'BRL',
                    'timezone_name' => 'America/Sao_Paulo', 'account_status' => 1,
                ]),
            };
        });
    }

    private function estruturaFixture(bool $comQuartoAnuncio = true): array
    {
        $ads = [
            ['id' => 'ad_1', 'name' => 'Anúncio 1', 'adset_id' => 'as_1', 'campaign_id' => 'c_1', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'],
            ['id' => 'ad_2', 'name' => 'Anúncio 2', 'adset_id' => 'as_1', 'campaign_id' => 'c_1', 'status' => 'PAUSED', 'effective_status' => 'PAUSED'],
            ['id' => 'ad_3', 'name' => 'Anúncio 3', 'adset_id' => 'as_2', 'campaign_id' => 'c_1', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'],
        ];

        if ($comQuartoAnuncio) {
            $ads[] = ['id' => 'ad_4', 'name' => 'Anúncio 4', 'adset_id' => 'as_3', 'campaign_id' => 'c_2', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'];
        }

        return [
            'campanhas' => [
                ['id' => 'c_1', 'name' => 'Bancário', 'objective' => 'LEAD_GENERATION', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'daily_budget' => '5000'],
                ['id' => 'c_2', 'name' => 'Concurso', 'objective' => 'OUTCOME_LEADS', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'lifetime_budget' => '120000'],
            ],
            'adsets' => [
                ['id' => 'as_1', 'name' => 'Interesses', 'campaign_id' => 'c_1', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE', 'optimization_goal' => 'LEAD_GENERATION'],
                ['id' => 'as_2', 'name' => 'Lookalike', 'campaign_id' => 'c_1', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'],
                ['id' => 'as_3', 'name' => 'Ampla', 'campaign_id' => 'c_2', 'status' => 'ACTIVE', 'effective_status' => 'ACTIVE'],
            ],
            'ads' => $ads,
        ];
    }

    public function test_estrutura_cria_hierarquia_e_e_idempotente(): void
    {
        $this->fakeMeta($this->estruturaFixture());

        $acao = app(SincronizarEstruturaAnunciosMeta::class);
        $exec = $acao($this->conta);

        $this->assertSame('ok', $exec->status->value);
        $this->assertSame(9, $exec->objetos_afetados); // 2 + 3 + 4
        $this->assertSame(2, MetaAdsCampanha::query()->count());
        $this->assertSame(3, MetaAdsConjunto::query()->count());
        $this->assertSame(4, MetaAdsAnuncio::query()->count());

        $campanha = MetaAdsCampanha::query()->where('meta_campaign_id', 'c_1')->firstOrFail();
        $this->assertSame('Bancário', $campanha->nome);
        $this->assertSame('OUTCOME_LEADS', $campanha->objetivo->value);
        $this->assertSame(5000, $campanha->orcamento_diario_centavos);

        // 2ª rodada: nome alterado, sem duplicar.
        $this->fixture['campanhas'][0]['name'] = 'Bancário 2026';

        $acao($this->conta);

        $this->assertSame(2, MetaAdsCampanha::query()->count());
        $this->assertSame(4, MetaAdsAnuncio::query()->count());
        $this->assertSame('Bancário 2026', $campanha->fresh()->nome);
    }

    public function test_estrutura_arquiva_orfaos(): void
    {
        $this->fakeMeta($this->estruturaFixture());
        $acao = app(SincronizarEstruturaAnunciosMeta::class);
        $acao($this->conta);

        $this->assertNull(MetaAdsAnuncio::query()->where('meta_ad_id', 'ad_4')->firstOrFail()->arquivado_em);

        // ad_4 some da Meta na 2ª rodada.
        $this->fixture['ads'] = array_values(array_filter(
            $this->fixture['ads'],
            fn ($a) => $a['id'] !== 'ad_4',
        ));
        $acao($this->conta);

        $this->assertNotNull(MetaAdsAnuncio::query()->where('meta_ad_id', 'ad_4')->firstOrFail()->arquivado_em);
        $this->assertSame(3, MetaAdsAnuncio::query()->vigentes()->count());
        // A linha continua no banco (não foi deletada).
        $this->assertDatabaseHas('meta_ads_anuncios', ['meta_ad_id' => 'ad_4']);
    }

    public function test_estrutura_fica_parcial_quando_pai_ainda_nao_existe(): void
    {
        $fx = $this->estruturaFixture();
        $fx['campanhas'] = []; // adsets/ads referenciam campanhas que não vieram

        $this->fakeMeta($fx);
        $exec = app(SincronizarEstruturaAnunciosMeta::class)($this->conta);

        $this->assertSame('parcial', $exec->status->value);
        $this->assertSame(0, MetaAdsConjunto::query()->count());
    }

    private function insightsFixture(): array
    {
        $linhas = [];
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $dia) {
            foreach (['ad_1' => 3, 'ad_2' => 1] as $adId => $leads) {
                $linhas[] = [
                    'ad_id' => $adId, 'adset_id' => 'as_1', 'campaign_id' => 'c_1', 'date_start' => $dia,
                    'spend' => '12.34', 'impressions' => '1000', 'clicks' => '40', 'inline_link_clicks' => '30',
                    'reach' => '800', 'cpc' => '0.31', 'cpm' => '12.34', 'ctr' => '4.0', 'frequency' => '1.25',
                    'actions' => [
                        ['action_type' => 'lead', 'value' => (string) $leads],
                        ['action_type' => 'onsite_conversion.lead_grouped', 'value' => (string) ($leads + 1)],
                        ['action_type' => 'link_click', 'value' => '30'],
                    ],
                    'cost_per_action_type' => [['action_type' => 'lead', 'value' => '4.11']],
                ];
            }
        }

        return ['insights' => $linhas];
    }

    public function test_insights_grava_uma_linha_por_dia_e_anuncio(): void
    {
        $this->fakeMeta($this->insightsFixture());

        $de = CarbonImmutable::parse('2026-09-01');
        $ate = CarbonImmutable::parse('2026-09-03');
        $exec = app(SincronizarInsightsAnunciosMeta::class)($this->conta, $de, $ate);

        $this->assertSame('ok', $exec->status->value);
        $this->assertSame(6, MetaAdsInsightDiario::query()->count());

        $linha = MetaAdsInsightDiario::query()
            ->where('objeto_id', 'ad_1')->where('referencia', CarbonImmutable::parse('2026-09-01'))->firstOrFail();

        $this->assertSame(1234, $linha->investimento_centavos);   // round(12.34 * 100)
        $this->assertSame(4, $linha->resultados);                 // max(3, 4) — não soma
        $this->assertSame(411, $linha->custo_por_resultado_centavos);
        $this->assertSame(31, $linha->cpc_centavos);

        // Idempotente.
        app(SincronizarInsightsAnunciosMeta::class)($this->conta, $de, $ate);
        $this->assertSame(6, MetaAdsInsightDiario::query()->count());
    }

    public function test_insights_janela_longa_usa_o_assincrono(): void
    {
        Http::fake([
            'graph.facebook.com/*/run_1/insights*' => Http::response(['data' => [
                ['ad_id' => 'ad_1', 'date_start' => '2026-08-01', 'spend' => '5.00', 'actions' => [['action_type' => 'lead', 'value' => '2']]],
            ]]),
            'graph.facebook.com/*/run_1*' => Http::response(['async_status' => 'Job Completed']),
            'graph.facebook.com/*/act_*/insights*' => Http::response(['report_run_id' => 'run_1']),
        ]);

        $exec = app(SincronizarInsightsAnunciosMeta::class)(
            $this->conta,
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-09-15'),
        );

        $this->assertSame(1, MetaAdsInsightDiario::query()->count());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/insights'));
        $this->assertSame('ok', $exec->status->value);
    }

    public function test_throttle_marca_parcial_e_job_reagenda(): void
    {
        Http::fake([
            'graph.facebook.com/*/act_*/campaigns*' => Http::response(
                ['data' => [['id' => 'c_1', 'name' => 'X', 'status' => 'ACTIVE']]],
                200,
                ['x-business-use-case-usage' => json_encode(['b' => [['estimated_time_to_regain_access' => 300]]])],
            ),
            'graph.facebook.com/*' => Http::response(['data' => []]),
        ]);

        $exec = app(SincronizarEstruturaAnunciosMeta::class)($this->conta);
        $this->assertSame('parcial', $exec->status->value);

        $job = new SincronizarEstruturaAnunciosMetaJob($this->conta);
        $job->withFakeQueueInteractions();
        $job->handle(app(SincronizarEstruturaAnunciosMeta::class));
        $job->assertReleased(900);
    }

    public function test_comando_sync_popula_e_e_idempotente(): void
    {
        $this->fakeMeta([...$this->estruturaFixture(), ...$this->insightsFixture()]);

        $this->artisan('meta:ads-sincronizar', ['--sync' => true, '--dias' => 3])->assertSuccessful();

        $this->assertSame(4, MetaAdsAnuncio::query()->count());
        $this->assertSame(6, MetaAdsInsightDiario::query()->count());

        $this->artisan('meta:ads-sincronizar', ['--sync' => true, '--dias' => 3])->assertSuccessful();
        $this->assertSame(4, MetaAdsAnuncio::query()->count());
        $this->assertSame(6, MetaAdsInsightDiario::query()->count());
    }

    public function test_comando_pula_conta_sem_ads_read(): void
    {
        $this->fakeMeta([...$this->estruturaFixture(), 'permissoes' => ['public_profile']]);

        $this->artisan('meta:ads-sincronizar', ['--sync' => true])->assertSuccessful();

        $this->assertSame(0, MetaAdsCampanha::query()->count());
        $this->assertFalse($this->conta->fresh()->token_valido);
    }

    public function test_comando_enfileira_a_chain_sem_sync(): void
    {
        Bus::fake();
        $this->fakeMeta($this->estruturaFixture());

        $this->artisan('meta:ads-sincronizar')->assertSuccessful();

        Bus::assertChained([
            SincronizarEstruturaAnunciosMetaJob::class,
            SincronizarInsightsAnunciosMetaJob::class,
        ]);
    }

    public function test_conta_de_outro_tenant_nao_e_tocada(): void
    {
        $outro = $this->createTenant();
        $alheia = $this->asTenant($outro, fn () => MetaAdsConta::factory()->create(['ad_account_id' => '999888777']));

        $this->fakeMeta($this->estruturaFixture());
        $this->artisan('meta:ads-sincronizar', ['--sync' => true, '--conta' => '123456789'])->assertSuccessful();

        $this->assertSame(0, $this->asTenant($outro, fn () => MetaAdsCampanha::query()->count()));
        $this->assertNull($alheia->fresh()->estrutura_sincronizada_em);
    }

    public function test_token_fora_dos_logs(): void
    {
        $linhas = [];
        Log::listen(function ($e) use (&$linhas): void {
            $linhas[] = json_encode([$e->message, $e->context]) ?: '';
        });

        $this->fakeMeta($this->estruturaFixture());
        $token = (string) $this->conta->access_token;

        app(SincronizarEstruturaAnunciosMeta::class)($this->conta);

        foreach ($linhas as $linha) {
            $this->assertStringNotContainsString('EAA', $linha);
            $this->assertStringNotContainsString($token, $linha);
        }
    }
}
