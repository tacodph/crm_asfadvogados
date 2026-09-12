<?php

namespace App\Support\Meta;

use App\Models\MetaConversaoConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cliente HTTP da API de Conversões da Meta. Nunca registra o access token
 * em log; o token trafega apenas na query string da requisição de saída.
 */
class ConversionsApiClient
{
    /**
     * Envia um evento para `{pixel_id}/events`.
     *
     * @param  array<string, mixed>  $eventoPayload  array de 1 evento (`data[]`)
     */
    public function enviar(
        MetaConversaoConfig $config,
        array $eventoPayload,
        ?string $tokenOverride = null,
    ): CapiResultado {
        $body = ['data' => [$eventoPayload]];

        $testCode = $config->test_event_code ?: config('meta.capi.test_event_code');

        if (is_string($testCode) && $testCode !== '') {
            $body['test_event_code'] = $testCode;
        }

        $url = $this->url($config, 'events');
        $token = $tokenOverride ?? (string) $config->access_token;

        try {
            $resposta = Http::asJson()
                ->timeout(10)
                ->retry(2, 200, when: fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                ->post($url.'?access_token='.urlencode($token), $body);
        } catch (ConnectionException) {
            Log::channel('meta-capi')->debug('meta-capi: falha de conexão ao enviar evento', ['pixel_id' => $config->pixel_id]);

            return new CapiResultado(ok: false, errorMessage: 'Falha de conexão com a Graph API.');
        }

        return $this->interpretarEnvio($resposta);
    }

    /**
     * Lê metadados do Pixel — usado pelo "testar conexão" e pela sincronização
     * de estatísticas. Aceita um token ainda não persistido.
     */
    public function verificarPixel(MetaConversaoConfig $config, ?string $tokenOverride = null): CapiResultado
    {
        $token = $tokenOverride ?? (string) $config->access_token;

        try {
            $resposta = Http::asJson()->timeout(10)->get($this->url($config), [
                'fields' => 'name,last_fired_time',
                'access_token' => $token,
            ]);
        } catch (ConnectionException) {
            return new CapiResultado(ok: false, errorMessage: 'Falha de conexão com a Graph API.');
        }

        /** @var array<string, mixed> $json */
        $json = (array) $resposta->json();
        $erro = is_array($json['error'] ?? null) ? $json['error'] : null;

        return new CapiResultado(
            ok: $resposta->successful() && isset($json['id']),
            httpStatus: $resposta->status(),
            fbtraceId: $this->stringOuNull($erro['fbtrace_id'] ?? null),
            errorCode: isset($erro['code']) ? (string) $erro['code'] : null,
            errorMessage: $erro !== null ? $this->mensagemErro($erro) : null,
            responseBody: $this->semSegredos($json),
        );
    }

    /**
     * Valida o token para uso na CAPI. Tokens gerados no Events Manager costumam
     * não ter permissão de leitura do Pixel (GET /{pixel_id}); nesse caso cai
     * no envio mínimo de evento (POST /events), que é o que o CRM de fato usa.
     */
    public function verificarConexao(MetaConversaoConfig $config, ?string $tokenOverride = null): CapiResultado
    {
        $leitura = $this->verificarPixel($config, $tokenOverride);

        if ($leitura->ok || ! $this->falhaPorPermissaoLeitura($leitura)) {
            return $leitura;
        }

        $envio = $this->verificarEnvio($config, $tokenOverride);

        if (! $envio->ok) {
            return $envio;
        }

        return new CapiResultado(
            ok: true,
            httpStatus: $envio->httpStatus,
            eventsReceived: $envio->eventsReceived,
            fbtraceId: $envio->fbtraceId,
            responseBody: [
                'verification' => 'events',
                'name' => $this->stringOuNull($leitura->responseBody['name'] ?? null),
                'last_fired_time' => $this->stringOuNull($leitura->responseBody['last_fired_time'] ?? null),
            ],
        );
    }

    /**
     * Leitura best-effort para a tela de diagnóstico e o snapshot diário:
     * metadados do Pixel (`name`, `last_fired_time`) + o corpo cru de
     * `/{pixel_id}/stats`. Nunca lança — devolve o que conseguiu.
     *
     * @return array{name: ?string, last_fired_time: ?string, pixel_ok: bool, leitura_pixel_ok: bool, stats: array<string, mixed>}
     */
    public function lerEstatisticas(MetaConversaoConfig $config): array
    {
        $pixel = $this->verificarPixel($config);

        $conexaoOk = $pixel->ok
            || ($this->falhaPorPermissaoLeitura($pixel) && $config->token_valido === true);

        $stats = [];

        try {
            $resposta = Http::asJson()->timeout(10)->get($this->url($config, 'stats'), [
                'access_token' => (string) $config->access_token,
            ]);

            if ($resposta->successful()) {
                $stats = $this->semSegredos((array) $resposta->json());
            }
        } catch (ConnectionException) {
            // best-effort: segue só com os metadados do Pixel.
        }

        return [
            'name' => $this->stringOuNull($pixel->responseBody['name'] ?? null),
            'last_fired_time' => $this->stringOuNull($pixel->responseBody['last_fired_time'] ?? null),
            'pixel_ok' => $conexaoOk,
            'leitura_pixel_ok' => $pixel->ok,
            'stats' => $stats,
        ];
    }

    private function interpretarEnvio(Response $resposta): CapiResultado
    {
        /** @var array<string, mixed> $json */
        $json = (array) $resposta->json();

        $recebidos = isset($json['events_received']) ? (int) $json['events_received'] : null;
        $erro = is_array($json['error'] ?? null) ? $json['error'] : null;

        return new CapiResultado(
            ok: $resposta->successful() && ($recebidos ?? 0) >= 1,
            httpStatus: $resposta->status(),
            eventsReceived: $recebidos,
            fbtraceId: $this->stringOuNull($json['fbtrace_id'] ?? ($erro['fbtrace_id'] ?? null)),
            errorCode: isset($erro['code']) ? (string) $erro['code'] : null,
            errorMessage: $erro !== null ? $this->mensagemErro($erro) : null,
            responseBody: $this->semSegredos($json),
        );
    }

    /**
     * Envia um evento mínimo para confirmar que o token consegue gravar na CAPI.
     */
    private function verificarEnvio(MetaConversaoConfig $config, ?string $tokenOverride = null): CapiResultado
    {
        return $this->enviar(
            $this->configComToken($config, $tokenOverride),
            [
                'event_name' => 'PageView',
                'event_time' => now()->getTimestamp(),
                'event_id' => 'verify_'.Str::uuid()->toString(),
                'action_source' => $config->action_source ?: 'system_generated',
                'user_data' => [
                    'external_id' => [AdvancedMatching::externalId('crm-capi-verify-'.$config->id)],
                ],
            ],
            $tokenOverride,
        );
    }

    private function falhaPorPermissaoLeitura(CapiResultado $resultado): bool
    {
        if ($resultado->ok) {
            return false;
        }

        if ($resultado->errorCode === '100') {
            return true;
        }

        $mensagem = strtolower($resultado->errorMessage ?? '');

        return str_contains($mensagem, 'permission') || str_contains($mensagem, 'permiss');
    }

    private function configComToken(MetaConversaoConfig $config, ?string $tokenOverride): MetaConversaoConfig
    {
        if ($tokenOverride === null) {
            return $config;
        }

        $clone = clone $config;
        $clone->access_token = $tokenOverride;

        return $clone;
    }

    private function url(MetaConversaoConfig $config, string $edge = ''): string
    {
        $base = sprintf(
            '%s/%s/%s',
            rtrim((string) config('meta.capi.base_url'), '/'),
            $config->api_version,
            $config->pixel_id,
        );

        return $edge === '' ? $base : $base.'/'.$edge;
    }

    /**
     * @param  array<string, mixed>  $erro
     */
    private function mensagemErro(array $erro): ?string
    {
        $mensagem = trim(implode(' — ', array_filter([
            $this->stringOuNull($erro['message'] ?? null),
            $this->stringOuNull($erro['error_user_msg'] ?? null),
        ])));

        return $mensagem === '' ? null : $mensagem;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function semSegredos(array $json): array
    {
        unset($json['access_token']);

        return $json;
    }

    private function stringOuNull(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
