<?php

namespace Tests\Feature;

use App\Enums\MetaAdsNivel;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use App\Models\Negociacao;
use App\Support\Meta\MetricasInvestimentoMeta;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricasInvestimentoMetaTest extends TestCase
{
    use RefreshDatabase;

    private MetaAdsConta $conta;

    private MetaAdsCampanha $campA;

    private MetaAdsCampanha $campB;

    private Funil $funil;

    /** @var array<string, EtapaFunil> */
    private array $etapas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->conta = MetaAdsConta::factory()->create();
        $this->campA = MetaAdsCampanha::factory()->for($this->conta, 'conta')->create(['meta_campaign_id' => 'A', 'nome' => 'Campanha A']);
        $this->campB = MetaAdsCampanha::factory()->for($this->conta, 'conta')->create(['meta_campaign_id' => 'B', 'nome' => 'Campanha B']);

        $this->funil = Funil::factory()->create(['nome' => 'Comercial']);

        foreach ([['Novo lead', 1], ['Reunião agendada', 2], ['Proposta enviada', 3], ['Contrato fechado', 4]] as [$nome, $ordem]) {
            $this->etapas[$nome] = EtapaFunil::factory()->create([
                'funil_id' => $this->funil->id, 'nome' => $nome, 'ordem' => $ordem,
            ]);
        }
    }

    private function anuncioDe(MetaAdsCampanha $campanha, string $adId): MetaAdsAnuncio
    {
        $conjunto = MetaAdsConjunto::factory()->create([
            'meta_ads_conta_id' => $this->conta->id,
            'meta_ads_campanha_id' => $campanha->id,
        ]);

        return MetaAdsAnuncio::factory()->create([
            'meta_ads_conta_id' => $this->conta->id,
            'meta_ads_campanha_id' => $campanha->id,
            'meta_ads_conjunto_id' => $conjunto->id,
            'meta_ad_id' => $adId,
        ]);
    }

    private function negociacaoNa(string $etapa, string $metaCampaignId, float $valor = 1000): Negociacao
    {
        $negociacao = Negociacao::factory()->create([
            'funil_id' => $this->funil->id,
            'etapa_funil_id' => $this->etapas[$etapa]->id,
            'valor' => $valor,
        ]);

        $negociacao->forceFill([
            'meta_campaign_id' => $metaCampaignId,
            'meta_atribuicao_origem' => 'utm',
            'meta_atribuido_em' => now(),
        ])->saveQuietly();

        return $negociacao;
    }

    public function test_metricas_por_campanha_batem_com_os_calculos_manuais(): void
    {
        $adA = $this->anuncioDe($this->campA, 'ad_A');

        foreach (range(0, 9) as $i) {
            MetaAdsInsightDiario::factory()->paraAnuncio($adA)
                ->noDia(CarbonImmutable::parse('2026-09-01')->addDays($i))
                ->create([
                    'meta_ads_conta_id' => $this->conta->id,
                    'investimento_centavos' => 100_00,
                    'impressoes' => 1000,
                    'cliques' => 50,
                    'cliques_link' => 40,
                    'alcance' => 700,
                    'resultados' => 2,
                ]);
        }

        CarbonImmutable::setTestNow('2026-09-05 12:00:00');
        $this->negociacaoNa('Novo lead', 'A');
        $this->negociacaoNa('Reunião agendada', 'A');
        $this->negociacaoNa('Proposta enviada', 'A');
        $this->negociacaoNa('Contrato fechado', 'A', 5000);
        $this->negociacaoNa('Contrato fechado', 'A', 3000);
        CarbonImmutable::setTestNow();

        $metricas = app(MetricasInvestimentoMeta::class)->para(
            $this->conta,
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-30'),
            MetaAdsNivel::Campanha,
        );

        $a = $metricas->firstWhere('metaId', 'A');
        $this->assertNotNull($a);

        $this->assertSame(1000_00, $a->investidoCentavos);
        $this->assertSame(20, $a->leadsMeta);
        $this->assertSame(5, $a->leadsCrm);
        $this->assertSame(4, $a->reunioes);
        $this->assertSame(2, $a->contratos);
        $this->assertSame(8000_00, $a->receitaCentavos);
        $this->assertSame(50_00, $a->cplMetaCentavos());        // 1000_00 / 20
        $this->assertSame(200_00, $a->cplCrmCentavos());        // 1000_00 / 5
        $this->assertSame(250_00, $a->custoReuniaoCentavos());  // 1000_00 / 4
        $this->assertSame(500_00, $a->custoContratoCentavos()); // 1000_00 / 2
        $this->assertSame(8.0, $a->roas());                     // 8000_00 / 1000_00

        $b = $metricas->firstWhere('metaId', 'B');
        $this->assertSame(0, $b->investidoCentavos);
        $this->assertSame(0.0, $b->roas());
    }

    public function test_serie_diaria_tem_um_ponto_por_dia(): void
    {
        $adA = $this->anuncioDe($this->campA, 'ad_serie');
        MetaAdsInsightDiario::factory()->paraAnuncio($adA)->noDia('2026-09-02')->create([
            'meta_ads_conta_id' => $this->conta->id, 'investimento_centavos' => 4200,
        ]);

        $serie = app(MetricasInvestimentoMeta::class)->serieDiaria(
            $this->conta,
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-03'),
        );

        $this->assertCount(3, $serie);
        $this->assertSame(4200, $serie->firstWhere('referencia', '2026-09-02')['investido_centavos']);
        $this->assertSame(0, $serie->firstWhere('referencia', '2026-09-01')['investido_centavos']);
    }

    public function test_isolamento_por_tenant(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, function (): void {
            $conta = MetaAdsConta::factory()->create();
            $camp = MetaAdsCampanha::factory()->for($conta, 'conta')->create(['meta_campaign_id' => 'A']);
            $conjunto = MetaAdsConjunto::factory()->create([
                'meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $camp->id,
            ]);
            $ad = MetaAdsAnuncio::factory()->create([
                'meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $camp->id,
                'meta_ads_conjunto_id' => $conjunto->id, 'meta_ad_id' => 'x',
            ]);
            MetaAdsInsightDiario::factory()->paraAnuncio($ad)->create([
                'meta_ads_conta_id' => $conta->id, 'investimento_centavos' => 999_999,
            ]);
        });

        $metricas = app(MetricasInvestimentoMeta::class)->para(
            $this->conta,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-12-31'),
        );

        $this->assertSame(0, $metricas->sum(fn ($m) => $m->investidoCentavos));
    }
}
