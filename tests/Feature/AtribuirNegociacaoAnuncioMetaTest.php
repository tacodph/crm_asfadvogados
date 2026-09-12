<?php

namespace Tests\Feature;

use App\Actions\Meta\AtribuirNegociacaoAnuncioMeta;
use App\Models\Funil;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use App\Support\Meta\ResolverCampanhaConversao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AtribuirNegociacaoAnuncioMetaTest extends TestCase
{
    use RefreshDatabase;

    private function negociacao(array $origemUtm = [], array $attrs = []): Negociacao
    {
        return Negociacao::factory()->create([
            'origem_utm' => $origemUtm !== [] ? $origemUtm : null,
            ...$attrs,
        ]);
    }

    public function test_atribui_pela_hierarquia_a_partir_do_meta_ad_id(): void
    {
        $anuncio = MetaAdsAnuncio::factory()->create(['meta_ad_id' => '778899']);

        $negociacao = $this->negociacao(['meta_ad_id' => '778899', 'fonte' => 'lead_ad']);

        $negociacao->refresh();
        $this->assertSame('778899', $negociacao->meta_ad_id);
        $this->assertSame($anuncio->conjunto->meta_adset_id, $negociacao->meta_adset_id);
        $this->assertSame($anuncio->campanha->meta_campaign_id, $negociacao->meta_campaign_id);
        $this->assertSame('lead_ad', $negociacao->meta_atribuicao_origem);
        $this->assertNotNull($negociacao->meta_atribuido_em);
    }

    public function test_utm_content_numerico_casa_com_anuncio(): void
    {
        MetaAdsAnuncio::factory()->create(['meta_ad_id' => '123456789']);

        $negociacao = $this->negociacao(['utm_source' => 'facebook', 'utm_content' => '123456789']);

        $this->assertSame('123456789', $negociacao->fresh()->meta_ad_id);
        $this->assertSame('utm', $negociacao->fresh()->meta_atribuicao_origem);
    }

    public function test_utm_campaign_por_nome_seta_so_a_campanha(): void
    {
        MetaAdsCampanha::factory()->create(['meta_campaign_id' => 'c_99', 'nome' => 'Bancário Setembro']);

        $negociacao = $this->negociacao(['utm_campaign' => 'bancário setembro']);

        $negociacao->refresh();
        $this->assertNull($negociacao->meta_ad_id);
        $this->assertSame('c_99', $negociacao->meta_campaign_id);
        $this->assertSame('utm', $negociacao->meta_atribuicao_origem);
    }

    public function test_so_fbclid_marca_origem_fbclid_sem_ids(): void
    {
        $negociacao = $this->negociacao(['fbclid' => 'IwAR-xyz'])->fresh();

        $this->assertSame('fbclid', $negociacao->meta_atribuicao_origem);
        $this->assertNull($negociacao->meta_ad_id);
        $this->assertNull($negociacao->meta_campaign_id);
    }

    public function test_mapa_manual_resolve_quando_nada_mais_casa(): void
    {
        config(['meta.ads.mapa_campanha' => ['bancario' => 'META_C_1']]);
        MetaConversaoConfig::factory()->bancario()->create();

        $funil = Funil::factory()->create(['nome' => 'Bancário']);
        $negociacao = Negociacao::factory()->create(['funil_id' => $funil->id, 'origem_utm' => null])->fresh();

        $this->assertSame('META_C_1', $negociacao->meta_campaign_id);
        $this->assertSame('manual', $negociacao->meta_atribuicao_origem);
    }

    public function test_atribuicao_nao_regride_sem_forcar(): void
    {
        MetaAdsCampanha::factory()->create(['meta_campaign_id' => 'c_novo', 'nome' => 'Nova']);
        $negociacao = $this->negociacao(['utm_campaign' => 'Nova'], [
            'meta_ad_id' => 'ja_atribuido',
            'meta_atribuicao_origem' => 'lead_ad',
        ]);

        app(AtribuirNegociacaoAnuncioMeta::class)($negociacao->fresh());
        $this->assertSame('ja_atribuido', $negociacao->fresh()->meta_ad_id);

        app(AtribuirNegociacaoAnuncioMeta::class)($negociacao->fresh(), forcar: true);
        $this->assertSame('c_novo', $negociacao->fresh()->meta_campaign_id);
    }

    public function test_excecao_na_atribuicao_nao_quebra_o_save(): void
    {
        $this->app->bind(AtribuirNegociacaoAnuncioMeta::class, fn () => new class(app(ResolverCampanhaConversao::class)) extends AtribuirNegociacaoAnuncioMeta
        {
            public function __invoke(Negociacao $negociacao, bool $forcar = false): void
            {
                throw new RuntimeException('boom');
            }
        });

        $negociacao = Negociacao::factory()->create();

        $this->assertModelExists($negociacao);
    }

    public function test_comando_reatribuir_preenche_lacunas(): void
    {
        MetaAdsCampanha::factory()->create(['meta_campaign_id' => 'c_x', 'nome' => 'Campanha X']);

        // criada sem anúncios sincronizados → sem atribuição
        $negociacao = $this->negociacao(['utm_campaign' => 'Campanha X'], []);
        // simula: no momento da criação a campanha ainda não existia
        $negociacao->forceFill(['meta_campaign_id' => null, 'meta_atribuicao_origem' => null, 'meta_atribuido_em' => null])->saveQuietly();

        $this->artisan('meta:ads-reatribuir --limite=10')->assertSuccessful();

        $this->assertSame('c_x', $negociacao->fresh()->meta_campaign_id);
    }
}
