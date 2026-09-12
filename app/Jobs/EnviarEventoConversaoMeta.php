<?php

namespace App\Jobs;

use App\Enums\MetaConversaoEventoStatus;
use App\Models\MetaConversaoEvento;
use App\Support\Meta\ConversionsApiClient;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Envia um evento já registrado (`meta_conversao_eventos`) para a Meta e
 * grava o resultado na própria linha. Idempotente: um evento `enviado` ou
 * `descartado` é ignorado.
 */
class EnviarEventoConversaoMeta implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600, 10800];

    public function __construct(public MetaConversaoEvento $evento) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping('meta-capi-evento-'.$this->evento->id)];
    }

    public function handle(ConversionsApiClient $client): void
    {
        $this->evento->refresh();

        if (in_array($this->evento->status, [
            MetaConversaoEventoStatus::Enviado,
            MetaConversaoEventoStatus::Descartado,
        ], true)) {
            return;
        }

        $config = $this->evento->config;

        if ($config === null) {
            $this->evento->forceFill([
                'status' => MetaConversaoEventoStatus::Erro,
                'error_message' => 'Evento sem campanha associada.',
            ])->save();

            return;
        }

        $this->evento->increment('tentativas');

        $resultado = $client->enviar($config, $this->evento->request_payload);

        $dados = [
            'http_status' => $resultado->httpStatus,
            'events_received' => $resultado->eventsReceived,
            'fbtrace_id' => $resultado->fbtraceId,
            'error_code' => $resultado->errorCode,
            'error_message' => $resultado->errorMessage,
            'response_body' => $resultado->responseBody,
            'status' => $resultado->ok
                ? MetaConversaoEventoStatus::Enviado
                : MetaConversaoEventoStatus::Erro,
        ];

        if ($resultado->ok) {
            $dados['enviado_em'] = now();
        }

        $this->evento->forceFill($dados)->save();

        $config->forceFill([
            'ultimo_evento_em' => now(),
            'ultimo_status' => $resultado->ok ? 'ok' : 'erro',
        ])->saveQuietly();

        if ($resultado->ok) {
            Log::channel('meta-capi')->info('meta-capi: evento enviado', [
                'evento_id' => $this->evento->id,
                'event_name' => $this->evento->event_name->value,
                'campanha' => $config->slug,
                'events_received' => $resultado->eventsReceived,
                'fbtrace_id' => $resultado->fbtraceId,
            ]);

            return;
        }

        if ($resultado->retentavel()) {
            throw new RuntimeException(sprintf(
                'Envio CAPI falhou (HTTP %s) — reprogramando evento %d.',
                $resultado->httpStatus ?? 'conexão',
                $this->evento->id,
            ));
        }

        Log::channel('meta-capi')->warning('meta-capi: evento com erro permanente', [
            'evento_id' => $this->evento->id,
            'campanha' => $config->slug,
            'http_status' => $resultado->httpStatus,
            'error_code' => $resultado->errorCode,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->evento->forceFill([
            'status' => MetaConversaoEventoStatus::Erro,
            'error_message' => $this->evento->error_message ?? $e->getMessage(),
        ])->saveQuietly();
    }
}
