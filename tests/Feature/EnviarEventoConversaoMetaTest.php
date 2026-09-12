<?php

namespace Tests\Feature;

use App\Enums\MetaConversaoEventoStatus;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\MetaConversaoEvento;
use App\Support\Meta\ConversionsApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class EnviarEventoConversaoMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function rodar(MetaConversaoEvento $evento): void
    {
        (new EnviarEventoConversaoMeta($evento))->handle(app(ConversionsApiClient::class));
    }

    public function test_success_marks_evento_enviado_and_updates_config(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'events_received' => 1,
            'fbtrace_id' => 'trace-1',
        ])]);

        $evento = MetaConversaoEvento::factory()->pendente()->create();

        $this->rodar($evento);

        $evento->refresh();
        $this->assertSame(MetaConversaoEventoStatus::Enviado, $evento->status);
        $this->assertSame('trace-1', $evento->fbtrace_id);
        $this->assertSame(1, $evento->events_received);
        $this->assertSame(1, $evento->tentativas);
        $this->assertNotNull($evento->enviado_em);
        $this->assertSame('ok', $evento->config->refresh()->ultimo_status);
    }

    public function test_permanent_error_marks_erro_without_rethrow(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'bad token', 'code' => 190],
        ], 400)]);

        $evento = MetaConversaoEvento::factory()->pendente()->create();

        $this->rodar($evento);

        $evento->refresh();
        $this->assertSame(MetaConversaoEventoStatus::Erro, $evento->status);
        $this->assertSame('190', $evento->error_code);
        $this->assertSame(1, $evento->tentativas);
    }

    public function test_transient_error_rethrows_for_retry(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response('', 503)]);

        $evento = MetaConversaoEvento::factory()->pendente()->create();

        try {
            $this->rodar($evento);
            $this->fail('esperava RuntimeException');
        } catch (RuntimeException) {
        }

        $evento->refresh();
        $this->assertSame(MetaConversaoEventoStatus::Erro, $evento->status);
        $this->assertSame(1, $evento->tentativas);
    }

    public function test_already_sent_evento_is_skipped(): void
    {
        Http::fake();

        $evento = MetaConversaoEvento::factory()->enviado()->create();

        $this->rodar($evento);

        Http::assertNothingSent();
        $this->assertSame(MetaConversaoEventoStatus::Enviado, $evento->refresh()->status);
    }

    public function test_failed_hook_marks_erro(): void
    {
        $evento = MetaConversaoEvento::factory()->pendente()->create();

        (new EnviarEventoConversaoMeta($evento))->failed(new RuntimeException('boom'));

        $this->assertSame(MetaConversaoEventoStatus::Erro, $evento->refresh()->status);
    }

    public function test_access_token_never_reaches_the_logs(): void
    {
        $linhas = [];
        Log::listen(function ($event) use (&$linhas): void {
            $linhas[] = json_encode([$event->message, $event->context]) ?: '';
        });

        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['events_received' => 1, 'fbtrace_id' => 't'])
                ->push(['error' => ['message' => 'x', 'code' => 190]], 400)
                ->whenEmpty(Http::response(['error' => ['message' => 'x', 'code' => 190]], 400)),
        ]);

        $ok = MetaConversaoEvento::factory()->pendente()->create();
        $erro = MetaConversaoEvento::factory()->pendente()->create();
        $token = (string) $ok->config->access_token;

        $this->rodar($ok);
        $this->rodar($erro);

        $this->assertNotEmpty($linhas);
        foreach ($linhas as $linha) {
            $this->assertStringNotContainsString('EAA', $linha);
            $this->assertStringNotContainsString($token, $linha);
        }
    }
}
