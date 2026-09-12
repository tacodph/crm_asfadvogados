<?php

namespace Tests\Feature;

use App\Models\MetaAdsConta;
use App\Support\Meta\MarketingApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MarketingApiClientTest extends TestCase
{
    use RefreshDatabase;

    private MarketingApiClient $client;

    private MetaAdsConta $conta;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();

        $this->client = app(MarketingApiClient::class);
        $this->conta = MetaAdsConta::factory()->create(['ad_account_id' => '123456789']);
    }

    private function pagina(array $data, ?string $next = null): array
    {
        $json = ['data' => $data, 'paging' => ['cursors' => ['after' => $next === null ? null : 'cur_'.$next]]];

        if ($next !== null) {
            $json['paging']['next'] = 'https://graph.facebook.com/next?after=cur_'.$next;
        }

        return $json;
    }

    public function test_listar_segue_os_cursores_ate_o_fim(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push($this->pagina([['id' => '1'], ['id' => '2']], 'p2'))
                ->push($this->pagina([['id' => '3']], 'p3'))
                ->push($this->pagina([['id' => '4']])),
        ]);

        $resultado = $this->client->listar($this->conta, $this->conta->nodeId().'/campaigns');

        $this->assertTrue($resultado->ok);
        $this->assertFalse($resultado->throttled);
        $this->assertSame(['1', '2', '3', '4'], array_column($resultado->dados, 'id'));
        Http::assertSentCount(3);
    }

    public function test_listar_sem_next_faz_uma_pagina_so(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->pagina([['id' => '1']]))]);

        $resultado = $this->client->listar($this->conta, $this->conta->nodeId().'/ads');

        $this->assertCount(1, $resultado->dados);
        Http::assertSentCount(1);
    }

    public function test_listar_recua_quando_o_uso_passa_do_teto(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push($this->pagina([['id' => '1']], 'p2'))
                ->push($this->pagina([['id' => '2']], 'p3'), 200, [
                    'x-business-use-case-usage' => json_encode(['biz' => [['type' => 'ads_insights', 'call_count' => 95]]]),
                ]),
        ]);

        $resultado = $this->client->listar($this->conta, $this->conta->nodeId().'/adsets');

        $this->assertTrue($resultado->throttled);
        $this->assertTrue($resultado->retentavel());
        $this->assertSame(['1', '2'], array_column($resultado->dados, 'id'));
        Http::assertSentCount(2);
    }

    public function test_estimated_time_vira_esperar_segundos(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response($this->pagina([['id' => '1']]), 200, [
                'x-business-use-case-usage' => json_encode(['biz' => [['estimated_time_to_regain_access' => 120]]]),
            ]),
        ]);

        $resultado = $this->client->listar($this->conta, $this->conta->nodeId().'/ads');

        $this->assertTrue($resultado->throttled);
        $this->assertSame(120, $resultado->esperarSegundos);
        $this->assertTrue($resultado->retentavel());
    }

    public function test_erro_de_token_nao_e_retentavel(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token', 'code' => 190, 'fbtrace_id' => 'ab'],
            ], 400),
        ]);

        $resultado = $this->client->listar($this->conta, $this->conta->nodeId().'/campaigns');

        $this->assertFalse($resultado->ok);
        $this->assertFalse($resultado->retentavel());
        $this->assertSame('190', $resultado->errorCode);
        $this->assertStringContainsString('OAuth', (string) $resultado->errorMessage);
    }

    public function test_erro_de_throttle_e_retentavel(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'User request limit reached', 'code' => 80000],
            ], 400),
        ]);

        $resultado = $this->client->listar($this->conta, $this->conta->nodeId().'/campaigns');

        $this->assertFalse($resultado->ok);
        $this->assertTrue($resultado->throttled);
        $this->assertTrue($resultado->retentavel());
    }

    public function test_insights_janela_curta_usa_o_caminho_sincrono(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->pagina([['ad_id' => '1', 'spend' => '10.00']]))]);

        $resultado = $this->client->insightsAnuncios($this->conta, now()->subDays(4), now());

        $this->assertTrue($resultado->ok);
        $this->assertCount(1, $resultado->dados);
        Http::assertSentCount(1);
    }

    public function test_insights_janela_longa_usa_o_relatorio_assincrono(): void
    {
        Http::fake([
            'graph.facebook.com/*/run_777/insights*' => Http::response($this->pagina([
                ['ad_id' => '1', 'spend' => '5.00'],
                ['ad_id' => '2', 'spend' => '7.00'],
            ])),
            'graph.facebook.com/*/run_777*' => Http::response(['async_status' => 'Job Completed', 'async_percent_completion' => 100]),
            'graph.facebook.com/*/act_*/insights*' => Http::response(['report_run_id' => 'run_777']),
        ]);

        $resultado = $this->client->insightsAnuncios($this->conta, now()->subDays(45), now());

        $this->assertTrue($resultado->ok);
        $this->assertSame(['1', '2'], array_column($resultado->dados, 'ad_id'));
        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/act_123456789/insights'));
    }

    public function test_relatorio_assincrono_que_falha_devolve_erro(): void
    {
        Http::fake([
            'graph.facebook.com/*/run_bad*' => Http::response(['async_status' => 'Job Failed']),
            'graph.facebook.com/*/act_*/insights*' => Http::response(['report_run_id' => 'run_bad']),
        ]);

        $resultado = $this->client->insightsAnuncios($this->conta, now()->subDays(45), now());

        $this->assertFalse($resultado->ok);
        $this->assertStringContainsString('falhou', (string) $resultado->errorMessage);
    }

    public function test_verificar_conta_sem_ads_read_marca_token_invalido(): void
    {
        Http::fake([
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'public_profile', 'status' => 'granted'],
            ]]),
            'graph.facebook.com/*/act_*' => Http::response([
                'id' => 'act_123456789', 'name' => 'ASF Ads', 'currency' => 'BRL',
                'timezone_name' => 'America/Sao_Paulo', 'account_status' => 1,
            ]),
        ]);

        $resultado = $this->client->registrarVerificacao($this->conta);

        $this->assertFalse($resultado->ok);
        $this->assertStringContainsString('ads_read', (string) $resultado->errorMessage);

        $this->conta->refresh();
        $this->assertFalse($this->conta->token_valido);
        $this->assertNotNull($this->conta->token_verificado_em);
        $this->assertSame('BRL', $this->conta->moeda);
    }

    public function test_verificar_conta_com_ads_read_e_valido(): void
    {
        Http::fake([
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'ads_read', 'status' => 'granted'],
                ['permission' => 'ads_management', 'status' => 'granted'],
            ]]),
            'graph.facebook.com/*/act_*' => Http::response([
                'id' => 'act_123456789', 'name' => 'ASF Ads', 'currency' => 'BRL',
                'timezone_name' => 'America/Sao_Paulo', 'account_status' => 1,
            ]),
        ]);

        $resultado = $this->client->registrarVerificacao($this->conta);

        $this->assertTrue($resultado->ok);
        $this->conta->refresh();
        $this->assertTrue($this->conta->token_valido);
        $this->assertSame(['ads_read', 'ads_management'], $this->conta->token_scopes);
        $this->assertTrue($this->conta->temEscopo('ads_management'));
    }

    public function test_token_nunca_aparece_nos_logs(): void
    {
        $linhas = [];
        Log::listen(function ($event) use (&$linhas): void {
            $linhas[] = json_encode([$event->message, $event->context]) ?: '';
        });

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'boom', 'code' => 1, 'fbtrace_id' => 'z'],
            ], 500),
        ]);

        $token = (string) $this->conta->access_token;
        $this->client->listar($this->conta, $this->conta->nodeId().'/campaigns');

        $this->assertNotEmpty($linhas);
        foreach ($linhas as $linha) {
            $this->assertStringNotContainsString('EAA', $linha);
            $this->assertStringNotContainsString($token, $linha);
        }
    }
}
