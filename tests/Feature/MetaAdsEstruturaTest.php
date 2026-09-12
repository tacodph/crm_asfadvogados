<?php

namespace Tests\Feature;

use App\Enums\MetaAdsNivel;
use App\Enums\MetaAdsObjetivo;
use App\Enums\MetaAdsStatus;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use App\Models\MetaAdsSyncExecucao;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MetaAdsEstruturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_objetivo_from_meta_normaliza_legados(): void
    {
        $this->assertSame(MetaAdsObjetivo::OutcomeLeads, MetaAdsObjetivo::fromMeta('LEAD_GENERATION'));
        $this->assertSame(MetaAdsObjetivo::OutcomeLeads, MetaAdsObjetivo::fromMeta('outcome_leads'));
        $this->assertSame(MetaAdsObjetivo::OutcomeSales, MetaAdsObjetivo::fromMeta('CONVERSIONS'));
        $this->assertSame(MetaAdsObjetivo::Outro, MetaAdsObjetivo::fromMeta('xyz'));
        $this->assertSame(MetaAdsObjetivo::Outro, MetaAdsObjetivo::fromMeta(null));
    }

    public function test_status_from_meta_e_nivel_metalevel(): void
    {
        $this->assertSame(MetaAdsStatus::Ativo, MetaAdsStatus::fromMeta('ACTIVE'));
        $this->assertSame(MetaAdsStatus::Pausado, MetaAdsStatus::fromMeta('WHATEVER'));

        $this->assertSame('ad', MetaAdsNivel::Anuncio->metaLevel());
        $this->assertSame('adset', MetaAdsNivel::Conjunto->metaLevel());
        $this->assertSame('campaign', MetaAdsNivel::Campanha->metaLevel());
        $this->assertSame('account', MetaAdsNivel::Conta->metaLevel());
    }

    public function test_factories_criam_a_hierarquia_dentro_do_tenant(): void
    {
        $anuncio = MetaAdsAnuncio::factory()->create();

        $this->assertSame($this->tenant->id, $anuncio->tenant_id);
        $this->assertSame($this->tenant->id, $anuncio->conjunto->tenant_id);
        $this->assertSame($this->tenant->id, $anuncio->campanha->tenant_id);
        $this->assertSame($this->tenant->id, $anuncio->conta->tenant_id);

        // Conta coerente em toda a cadeia.
        $this->assertSame($anuncio->meta_ads_conta_id, $anuncio->conjunto->meta_ads_conta_id);
        $this->assertSame($anuncio->meta_ads_conta_id, $anuncio->campanha->meta_ads_conta_id);
        $this->assertSame($anuncio->meta_ads_campanha_id, $anuncio->conjunto->meta_ads_campanha_id);
    }

    public function test_conta_relations(): void
    {
        $conta = MetaAdsConta::factory()->create();
        MetaAdsCampanha::factory()->for($conta, 'conta')->count(2)->create();

        $this->assertCount(2, $conta->campanhas);
        $this->assertInstanceOf(MetaAdsCampanha::class, $conta->campanhas->first());
    }

    public function test_insight_unique_por_objeto_nivel_dia(): void
    {
        $anuncio = MetaAdsAnuncio::factory()->create();

        MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->noDia('2026-09-01')->create();

        $this->expectException(QueryException::class);
        MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->noDia('2026-09-01')->create();
    }

    public function test_insight_updateorcreate_atualiza_a_mesma_chave(): void
    {
        $anuncio = MetaAdsAnuncio::factory()->create();

        // `referencia` tem cast `date` ⇒ é gravada como `Y-m-d 00:00:00`. A chave do
        // updateOrCreate precisa ser um Carbon para o bind casar (ver prompt 04).
        $chave = [
            'objeto_id' => $anuncio->meta_ad_id,
            'nivel' => MetaAdsNivel::Anuncio->value,
            'referencia' => Carbon::parse('2026-09-01'),
        ];

        MetaAdsInsightDiario::query()->updateOrCreate($chave, [
            'meta_ads_conta_id' => $anuncio->meta_ads_conta_id,
            'investimento_centavos' => 1000,
            'bruto' => [],
        ]);
        MetaAdsInsightDiario::query()->updateOrCreate($chave, [
            'meta_ads_conta_id' => $anuncio->meta_ads_conta_id,
            'investimento_centavos' => 2500,
            'bruto' => [],
        ]);

        $this->assertSame(1, MetaAdsInsightDiario::query()->count());
        $this->assertSame(2500, MetaAdsInsightDiario::query()->first()->investimento_centavos);
    }

    public function test_entregando_e_investimento_reais(): void
    {
        $ativo = MetaAdsAnuncio::factory()->create(['effective_status' => 'ACTIVE']);
        $pausado = MetaAdsAnuncio::factory()->pausado()->create();

        $this->assertTrue($ativo->entregando());
        $this->assertFalse($pausado->entregando());

        $insight = MetaAdsInsightDiario::factory()->create(['investimento_centavos' => 12_345]);
        $this->assertSame(123.45, $insight->investimentoReais());
    }

    public function test_scopes_vigentes_e_intervalo(): void
    {
        $conta = MetaAdsConta::factory()->create();
        MetaAdsCampanha::factory()->for($conta, 'conta')->count(2)->create();
        MetaAdsCampanha::factory()->for($conta, 'conta')->arquivada()->create();

        $this->assertSame(3, MetaAdsCampanha::query()->count());
        $this->assertSame(2, MetaAdsCampanha::query()->vigentes()->count());

        $anuncio = MetaAdsAnuncio::factory()->create();
        MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->noDia('2026-08-01')->create();
        MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->noDia('2026-09-10')->create();

        $this->assertSame(
            1,
            MetaAdsInsightDiario::query()
                ->nivel(MetaAdsNivel::Anuncio)
                ->noIntervalo('2026-09-01', '2026-09-30')
                ->count(),
        );
    }

    public function test_anuncio_insights_diarios_relation(): void
    {
        $anuncio = MetaAdsAnuncio::factory()->create();
        MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->count(3)->create();
        // Insight de outro anúncio não deve aparecer.
        MetaAdsInsightDiario::factory()->create();

        $this->assertCount(3, $anuncio->insightsDiarios);
    }

    public function test_sync_execucao_casts(): void
    {
        $exec = MetaAdsSyncExecucao::factory()->insights()->parcial()->create();

        $this->assertSame('insights', $exec->tipo->value);
        $this->assertSame('parcial', $exec->status->value);
        $this->assertNotNull($exec->janela_inicio);
    }

    public function test_isolamento_por_tenant(): void
    {
        $outro = $this->createTenant();
        $alheia = $this->asTenant($outro, fn () => MetaAdsCampanha::factory()->create());

        $this->assertSame(0, MetaAdsCampanha::query()->whereKey($alheia->id)->count());
        $this->assertSame(1, $this->asTenant($outro, fn () => MetaAdsCampanha::query()->count()));
    }
}
