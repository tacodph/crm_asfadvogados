<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\SincronizarEstruturaAnunciosMetaJob;
use App\Jobs\SincronizarInsightsAnunciosMetaJob;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrafegoInvestimentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function contaFake(): void
    {
        Http::fake([
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'ads_read', 'status' => 'granted'],
            ]]),
            'graph.facebook.com/*/act_*' => Http::response([
                'id' => 'act_1', 'name' => 'ASF Ads', 'currency' => 'BRL',
                'timezone_name' => 'America/Sao_Paulo', 'account_status' => 1,
            ]),
        ]);
    }

    public function test_index_renderiza_sem_vazar_o_token(): void
    {
        MetaAdsConta::factory()->create(['access_token' => 'EAA'.str_repeat('z', 90)]);

        $response = $this->actingAs($this->user())
            ->get(route('trafego.investimento.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/TrafegoInvestimento')
                ->has('contas', 1)
                ->has('kpis', 4)
                ->has('linhas')
                ->has('serie')
                ->missing('contas.0.access_token')
                ->missing('saude'));

        $this->assertStringNotContainsString('EAA', $response->getContent());
    }

    public function test_saude_e_deferred_e_resolve_na_segunda_request(): void
    {
        MetaAdsConta::factory()->create();

        $this->actingAs($this->user())
            ->get(route('trafego.investimento.index'))
            ->assertInertia(fn (Assert $page) => $page->missing('saude'));

        $this->contaFake();
        $version = (string) app(HandleInertiaRequests::class)->version(request());

        $this->actingAs($this->user())
            ->get(route('trafego.investimento.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Component' => 'crm/TrafegoInvestimento',
                'X-Inertia-Partial-Data' => 'saude',
            ])
            ->assertOk()
            ->assertJsonPath('props.saude.'.MetaAdsConta::query()->value('id').'.ok', true);
    }

    public function test_store_conta_exige_token_e_cifra(): void
    {
        $this->contaFake();

        $this->actingAs($this->user())
            ->post(route('trafego.investimento.contas.store'), [
                'nome' => 'ASF Bancário', 'ad_account_id' => '123456789',
            ])
            ->assertSessionHasErrors('access_token');

        $this->actingAs($this->user())
            ->post(route('trafego.investimento.contas.store'), [
                'nome' => 'ASF Bancário',
                'ad_account_id' => 'act_123456789',
                'access_token' => 'EAA'.str_repeat('t', 40),
            ])
            ->assertRedirect(route('trafego.investimento.index'));

        $conta = MetaAdsConta::query()->firstOrFail();
        $this->assertSame('123456789', $conta->ad_account_id);
        $this->assertSame('tttt', $conta->token_ultimos4);
        $this->assertTrue($conta->token_valido);
        $this->assertStringNotContainsString('EAA', (string) DB::table('meta_ads_contas')->where('id', $conta->id)->value('access_token'));
    }

    public function test_update_conta_sem_token_mantem_o_atual(): void
    {
        $conta = MetaAdsConta::factory()->create(['access_token' => 'EAA'.str_repeat('k', 40)]);
        $cru = DB::table('meta_ads_contas')->where('id', $conta->id)->value('access_token');

        $this->actingAs($this->user())
            ->patch(route('trafego.investimento.contas.update', $conta), [
                'nome' => 'Novo nome', 'ad_account_id' => $conta->ad_account_id, 'access_token' => '',
            ])
            ->assertRedirect();

        $this->assertSame('Novo nome', $conta->fresh()->nome);
        $this->assertSame($cru, DB::table('meta_ads_contas')->where('id', $conta->id)->value('access_token'));
    }

    public function test_sincronizar_despacha_a_chain(): void
    {
        Bus::fake();
        MetaAdsConta::factory()->create();

        $this->actingAs($this->user())
            ->post(route('trafego.investimento.sincronizar'))
            ->assertRedirect();

        Bus::assertChained([
            SincronizarEstruturaAnunciosMetaJob::class,
            SincronizarInsightsAnunciosMetaJob::class,
        ]);
    }

    public function test_drill_por_campanha_filtra_as_linhas(): void
    {
        $conta = MetaAdsConta::factory()->create();
        $campA = MetaAdsCampanha::factory()->for($conta, 'conta')->create(['meta_campaign_id' => 'A']);
        $campB = MetaAdsCampanha::factory()->for($conta, 'conta')->create(['meta_campaign_id' => 'B']);
        MetaAdsConjunto::factory()->create(['meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $campA->id, 'nome' => 'Conj A1']);
        MetaAdsConjunto::factory()->create(['meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $campA->id, 'nome' => 'Conj A2']);
        MetaAdsConjunto::factory()->create(['meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $campB->id, 'nome' => 'Conj B1']);

        $this->actingAs($this->user())
            ->get(route('trafego.investimento.index', ['nivel' => 'conjunto', 'campanha' => $campA->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtros.nivel', 'conjunto')
                ->has('linhas', 2));
    }

    public function test_metricas_na_prop_batem(): void
    {
        $conta = MetaAdsConta::factory()->create();
        $campanha = MetaAdsCampanha::factory()->for($conta, 'conta')->create(['meta_campaign_id' => 'C1', 'nome' => 'C1']);
        $conjunto = MetaAdsConjunto::factory()->create(['meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $campanha->id]);
        $anuncio = MetaAdsAnuncio::factory()->create([
            'meta_ads_conta_id' => $conta->id, 'meta_ads_campanha_id' => $campanha->id,
            'meta_ads_conjunto_id' => $conjunto->id, 'meta_ad_id' => 'ad1',
        ]);
        MetaAdsInsightDiario::factory()->paraAnuncio($anuncio)->noDia(CarbonImmutable::now()->subDays(2))->create([
            'meta_ads_conta_id' => $conta->id, 'investimento_centavos' => 300_00, 'resultados' => 6,
        ]);

        $this->actingAs($this->user())
            ->get(route('trafego.investimento.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('linhas.0.investido_centavos', 300_00)
                ->where('linhas.0.leads_meta', 6)
                ->where('linhas.0.cpl_meta_centavos', 50_00));
    }

    public function test_conta_de_outro_tenant_da_404(): void
    {
        $outro = $this->createTenant();
        $alheia = $this->asTenant($outro, fn () => MetaAdsConta::factory()->create());

        $this->actingAs($this->user())
            ->patch(route('trafego.investimento.contas.update', $alheia), ['nome' => 'x', 'ad_account_id' => $alheia->ad_account_id])
            ->assertNotFound();

        $this->actingAs($this->user())
            ->delete(route('trafego.investimento.contas.destroy', $alheia))
            ->assertNotFound();
    }
}
