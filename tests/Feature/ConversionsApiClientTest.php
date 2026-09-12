<?php

namespace Tests\Feature;

use App\Models\MetaConversaoConfig;
use App\Support\Meta\ConversionsApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversionsApiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_enviar_success_reports_events_received(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'events_received' => 1,
            'messages' => [],
            'fbtrace_id' => 'AbC123',
        ])]);

        $config = MetaConversaoConfig::factory()->bancario()->create();

        $resultado = (new ConversionsApiClient)->enviar($config, ['event_name' => 'Lead']);

        $this->assertTrue($resultado->ok);
        $this->assertSame(1, $resultado->eventsReceived);
        $this->assertSame('AbC123', $resultado->fbtraceId);
        $this->assertSame(200, $resultado->httpStatus);
        $this->assertFalse($resultado->retentavel());
    }

    public function test_enviar_permanent_token_error(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => 'Error validating access token',
                'code' => 190,
                'error_user_msg' => 'A sessão expirou.',
                'fbtrace_id' => 'Zzz',
            ],
        ], 400)]);

        $config = MetaConversaoConfig::factory()->create();

        $resultado = (new ConversionsApiClient)->enviar($config, []);

        $this->assertFalse($resultado->ok);
        $this->assertSame('190', $resultado->errorCode);
        $this->assertStringContainsString('A sessão expirou.', (string) $resultado->errorMessage);
        $this->assertFalse($resultado->retentavel());
    }

    public function test_enviar_server_error_is_retryable(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response('', 500)]);

        $config = MetaConversaoConfig::factory()->create();

        $resultado = (new ConversionsApiClient)->enviar($config, []);

        $this->assertFalse($resultado->ok);
        $this->assertSame(500, $resultado->httpStatus);
        $this->assertTrue($resultado->retentavel());
    }

    public function test_enviar_includes_test_event_code_when_configured(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        $config = MetaConversaoConfig::factory()->create(['test_event_code' => 'TEST42']);

        (new ConversionsApiClient)->enviar($config, ['event_name' => 'Lead']);

        Http::assertSent(fn ($request) => ($request->data()['test_event_code'] ?? null) === 'TEST42');
    }

    public function test_verificar_pixel_success(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'id' => '2050053805929053',
            'name' => 'Pixel ASF Bancário',
            'last_fired_time' => '2026-09-01T10:00:00+0000',
        ])]);

        $config = MetaConversaoConfig::factory()->bancario()->create();

        $resultado = (new ConversionsApiClient)->verificarPixel($config);

        $this->assertTrue($resultado->ok);
        $this->assertSame('Pixel ASF Bancário', $resultado->responseBody['name']);
    }

    public function test_verificar_conexao_falls_back_to_events_when_read_permission_missing(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => '(#100) Missing Permission',
                        'code' => 100,
                        'fbtrace_id' => 'read-fail',
                    ],
                ], 400)
                ->push([
                    'events_received' => 1,
                    'fbtrace_id' => 'post-ok',
                ], 200),
        ]);

        $config = MetaConversaoConfig::factory()->bancario()->create();

        $resultado = (new ConversionsApiClient)->verificarConexao($config);

        $this->assertTrue($resultado->ok);
        $this->assertSame(1, $resultado->eventsReceived);
        $this->assertSame('events', $resultado->responseBody['verification']);
        Http::assertSentCount(2);
    }

    public function test_verificar_conexao_keeps_real_token_errors(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => 'Error validating access token',
                'code' => 190,
                'error_user_msg' => 'A sessão expirou.',
            ],
        ], 400)]);

        $config = MetaConversaoConfig::factory()->create();

        $resultado = (new ConversionsApiClient)->verificarConexao($config);

        $this->assertFalse($resultado->ok);
        $this->assertSame('190', $resultado->errorCode);
        Http::assertSentCount(1);
    }

    public function test_ler_estatisticas_marca_conexao_ok_quando_token_valido_mas_leitura_falha(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => '(#100) Missing Permission',
                    'code' => 100,
                ],
            ], 400),
        ]);

        $config = MetaConversaoConfig::factory()->tokenVerificado()->create();

        $lido = (new ConversionsApiClient)->lerEstatisticas($config);

        $this->assertTrue($lido['pixel_ok']);
        $this->assertFalse($lido['leitura_pixel_ok']);
        $this->assertNull($lido['name']);
    }

    public function test_token_never_appears_in_response_body(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        $config = MetaConversaoConfig::factory()->create();

        $resultado = (new ConversionsApiClient)->enviar($config, []);

        $this->assertStringNotContainsString(
            (string) $config->access_token,
            json_encode($resultado->responseBody) ?: '',
        );
    }
}
