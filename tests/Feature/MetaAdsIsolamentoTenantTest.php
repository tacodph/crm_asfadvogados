<?php

namespace Tests\Feature;

use App\Models\Funil;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use App\Models\Negociacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MetaAdsIsolamentoTenantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $outro;

    private MetaAdsConta $contaAlheia;

    private Negociacao $negociacaoAlheia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);

        if (is_file(public_path('hot'))) {
            @unlink(public_path('hot'));
        }

        Http::preventStrayRequests();

        $this->outro = $this->createTenant();

        [$this->contaAlheia, $this->negociacaoAlheia] = $this->asTenant($this->outro, function (): array {
            $conta = MetaAdsConta::factory()->create(['ad_account_id' => '999000']);
            $campanha = MetaAdsCampanha::factory()->for($conta, 'conta')->create(['meta_campaign_id' => 'A_MC']);
            $conjunto = MetaAdsConjunto::factory()->create([
                'meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $campanha->id,
            ]);
            $anuncio = MetaAdsAnuncio::factory()->create([
                'meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $campanha->id,
                'meta_ads_conjunto_id' => $conjunto->id, 'meta_ad_id' => 'A_AD',
            ]);
            MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->create([
                'meta_ads_conta_id' => $conta->id, 'investimento_centavos' => 500_00,
            ]);

            $funil = Funil::factory()->create();
            $negociacao = Negociacao::factory()->create(['funil_id' => $funil->id]);
            $negociacao->forceFill(['meta_campaign_id' => 'A_MC', 'meta_atribuicao_origem' => 'utm'])->saveQuietly();

            return [$conta, $negociacao];
        });
    }

    public function test_tenant_b_nao_ve_nada_do_tenant_a(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('trafego.investimento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('contas', [])
                ->where('linhas', [])
                ->where('serie', []));

        $this->patch(route('trafego.investimento.contas.update', $this->contaAlheia), [
            'nome' => 'x', 'ad_account_id' => $this->contaAlheia->ad_account_id,
        ])->assertNotFound();

        $this->delete(route('trafego.investimento.contas.destroy', $this->contaAlheia))->assertNotFound();
        $this->post(route('trafego.investimento.contas.testar-conexao', $this->contaAlheia))->assertNotFound();
    }

    public function test_comandos_nao_tocam_o_tenant_a(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

        $this->artisan('meta:ads-sincronizar --sync --conta=000001')->assertSuccessful();
        $this->artisan('meta:ads-reatribuir --limite=10')->assertSuccessful();

        $this->contaAlheia->refresh();
        $this->assertNull($this->contaAlheia->estrutura_sincronizada_em);
        $this->assertSame('A_MC', $this->negociacaoAlheia->fresh()->meta_campaign_id);

        // A sync do outro tenant tentaria falar com a Meta — não deve, pois filtramos.
        Http::assertNothingSent();
    }
}
