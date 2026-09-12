<?php

namespace App\Support\Meta;

use App\Models\MetaAdsConta;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Cliente de LEITURA da API de Marketing da Meta (Meta Ads). Segue a paginação
 * por cursor, respeita o rate limit (`X-Business-Use-Case-Usage`) e sabe usar o
 * relatório assíncrono de insights para janelas grandes. Nunca registra o token
 * em log — ele trafega só na query string da requisição de saída.
 */
class MarketingApiClient
{
    /** Códigos de erro da Graph API que indicam throttling (retentável). */
    private const CODIGOS_THROTTLE = [4, 17, 32, 613, 80000];

    /**
     * Lista um edge paginado (`/campaigns`, `/adsets`, `/ads`, `/insights`, …)
     * seguindo os cursores `after` até o fim — ou até bater no teto de rate
     * limit, quando devolve o que já juntou com `throttled = true`.
     *
     * @param  array<string, mixed>  $params
     */
    public function listar(MetaAdsConta $conta, string $node, array $params = []): MapiResultado
    {
        $token = (string) $conta->access_token;
        $acumulado = [];
        $after = null;
        $ultimoBuc = [];

        do {
            $query = $params;
            $query['limit'] ??= 200;

            if ($after !== null) {
                $query['after'] = $after;
            }

            $pagina = $this->get($node, $query, $token);
            $ultimoBuc = $pagina['buc'];

            if ($pagina['conexao_falhou']) {
                return new MapiResultado(
                    ok: false,
                    dados: $acumulado,
                    errorMessage: 'Falha de conexão com a Graph API.',
                    throttled: true,
                );
            }

            $acumulado = [...$acumulado, ...$pagina['data']];

            Log::channel('meta-ads')->debug('meta-ads: página lida', [
                'node' => $node,
                'recebidos' => count($pagina['data']),
                'acumulado' => count($acumulado),
                'paginado' => $after !== null,
            ]);

            if ($pagina['erro'] !== null) {
                return $this->resultadoDeErro($pagina, $acumulado);
            }

            if ($this->bucRecuar($pagina['buc'])) {
                $esperar = (int) ($pagina['buc']['esperar_segundos'] ?? 0);
                Log::channel('meta-ads')->warning('meta-ads: recuo por rate limit', [
                    'node' => $node,
                    'uso_pct' => $pagina['buc']['uso_pct'] ?? null,
                    'esperar' => $esperar,
                ]);

                return new MapiResultado(
                    ok: true,
                    httpStatus: $pagina['status'],
                    dados: $acumulado,
                    throttled: true,
                    esperarSegundos: $esperar > 0 ? $esperar : null,
                    usoBuc: $pagina['buc'],
                );
            }

            $temProxima = $pagina['next'] !== null && $pagina['next'] !== '';
            $after = $temProxima ? $pagina['after'] : null;
        } while ($after !== null);

        return new MapiResultado(ok: true, httpStatus: 200, dados: $acumulado, usoBuc: $ultimoBuc);
    }

    /**
     * Insights nível anúncio (`time_increment = 1`) para a janela [$de, $ate].
     * Janela até `config('meta.ads.async_dias')` → GET direto; acima → relatório
     * assíncrono (POST + poll curto + download paginado).
     */
    public function insightsAnuncios(MetaAdsConta $conta, CarbonInterface $de, CarbonInterface $ate): MapiResultado
    {
        $dias = (int) round(abs($de->diffInDays($ate))) + 1;

        $params = [
            'level' => 'ad',
            'time_increment' => 1,
            'time_range' => (string) json_encode([
                'since' => $de->format('Y-m-d'),
                'until' => $ate->format('Y-m-d'),
            ]),
            'fields' => implode(',', array_merge(
                array_map('strval', (array) config('meta.ads.insights_fields', [])),
                ['ad_id', 'adset_id', 'campaign_id', 'date_start'],
            )),
            'limit' => 500,
        ];

        if ($dias <= (int) config('meta.ads.async_dias', 32)) {
            return $this->listar($conta, $conta->nodeId().'/insights', $params);
        }

        return $this->insightsAssincrono($conta, $params);
    }

    /**
     * Metadados da conta (`name`, `currency`, `timezone_name`, `account_status`)
     * + checagem de escopo `ads_read`. Aceita um token ainda não persistido
     * (teste antes de salvar). Não grava nada — ver `registrarVerificacao()`.
     */
    public function verificarConta(MetaAdsConta $conta, ?string $tokenOverride = null): MapiResultado
    {
        $token = $tokenOverride ?? (string) $conta->access_token;

        try {
            $resposta = $this->http()->get($this->url($conta->nodeId()), [
                'fields' => 'name,currency,timezone_name,account_status',
                'access_token' => $token,
            ]);
        } catch (ConnectionException) {
            return new MapiResultado(ok: false, errorMessage: 'Falha de conexão com a Graph API.');
        }

        /** @var array<string, mixed> $json */
        $json = (array) $resposta->json();
        $erro = is_array($json['error'] ?? null) ? $json['error'] : null;
        $buc = $this->parseBuc($resposta);
        $escopos = $this->lerEscopos($token);
        $temAdsRead = in_array('ads_read', $escopos, true);

        if ($erro !== null) {
            return new MapiResultado(
                ok: false,
                httpStatus: $resposta->status(),
                dados: [['account' => [], 'scopes' => $escopos]],
                errorCode: isset($erro['code']) ? (string) $erro['code'] : null,
                errorMessage: $this->mensagemErro($erro),
                fbtraceId: $this->str($erro['fbtrace_id'] ?? null),
                throttled: $this->erroThrottle($erro),
                esperarSegundos: ((int) ($buc['esperar_segundos'] ?? 0)) ?: null,
                usoBuc: $buc,
            );
        }

        return new MapiResultado(
            ok: $resposta->successful() && $temAdsRead,
            httpStatus: $resposta->status(),
            dados: [['account' => $this->semSegredos($json), 'scopes' => $escopos]],
            errorMessage: $temAdsRead ? null : 'O token não tem a permissão ads_read.',
            usoBuc: $buc,
        );
    }

    /**
     * `verificarConta()` + persiste o resultado na conta (moeda, fuso, escopos,
     * status, verificação). Usado pela aba Investimento e por `meta:ads-sincronizar`.
     */
    public function registrarVerificacao(MetaAdsConta $conta): MapiResultado
    {
        $resultado = $this->verificarConta($conta);

        $accountRaw = data_get($resultado->dados, '0.account', []);
        $account = is_array($accountRaw) ? $accountRaw : [];

        $escoposRaw = data_get($resultado->dados, '0.scopes', []);
        $escopos = is_array($escoposRaw) ? array_values(array_map('strval', $escoposRaw)) : [];

        $status = $account['account_status'] ?? null;

        $conta->forceFill([
            'token_verificado_em' => now(),
            'token_valido' => $resultado->ok,
            'token_scopes' => $escopos !== [] ? $escopos : $conta->token_scopes,
            'moeda' => $this->str($account['currency'] ?? null) ?? $conta->moeda,
            'fuso_horario' => $this->str($account['timezone_name'] ?? null) ?? $conta->fuso_horario,
            'conta_status' => is_scalar($status) ? (string) $status : $conta->conta_status,
        ])->saveQuietly();

        return $resultado;
    }

    // ---------------------------------------------------------------------

    /**
     * Relatório assíncrono: dispara, faz poll curto (com `Sleep` fakeável) e
     * baixa. Se não concluir a tempo, devolve `throttled = true` p/ o Job re-agendar.
     *
     * @param  array<string, mixed>  $params
     */
    private function insightsAssincrono(MetaAdsConta $conta, array $params): MapiResultado
    {
        $token = (string) $conta->access_token;
        $node = $conta->nodeId().'/insights';

        try {
            $disparo = $this->http()->asForm()
                ->post($this->url($node).'?access_token='.urlencode($token), $params);
        } catch (ConnectionException) {
            return new MapiResultado(ok: false, errorMessage: 'Falha de conexão com a Graph API.', throttled: true);
        }

        /** @var array<string, mixed> $json */
        $json = (array) $disparo->json();
        $runId = $this->str($json['report_run_id'] ?? null);
        $erro = is_array($json['error'] ?? null) ? $json['error'] : null;

        if ($runId === null) {
            return new MapiResultado(
                ok: false,
                httpStatus: $disparo->status(),
                errorCode: $erro !== null && isset($erro['code']) ? (string) $erro['code'] : null,
                errorMessage: $erro !== null
                    ? $this->mensagemErro($erro)
                    : 'A Meta não devolveu report_run_id.',
                throttled: $erro !== null && $this->erroThrottle($erro),
            );
        }

        foreach ([1, 2, 3, 5, 8, 13] as $espera) {
            try {
                $poll = $this->http()->get($this->url($runId), ['access_token' => $token]);
            } catch (ConnectionException) {
                return new MapiResultado(ok: false, errorMessage: 'Falha de conexão ao consultar o relatório.', throttled: true);
            }

            $status = $this->str(data_get($poll->json(), 'async_status'));

            if ($status === 'Job Completed') {
                return $this->listar($conta, $runId.'/insights', ['limit' => 500]);
            }

            if (in_array($status, ['Job Failed', 'Job Skipped'], true)) {
                return new MapiResultado(
                    ok: false,
                    httpStatus: $poll->status(),
                    errorMessage: 'Relatório assíncrono da Meta falhou ('.$status.').',
                );
            }

            Sleep::for($espera)->seconds();
        }

        return new MapiResultado(
            ok: false,
            errorMessage: 'Relatório assíncrono não concluiu a tempo.',
            throttled: true,
        );
    }

    /**
     * GET de uma página. Devolve as partes normalizadas — quem orquestra é `listar()`.
     *
     * @param  array<string, mixed>  $query
     * @return array{conexao_falhou: bool, status: int, data: list<array<string, mixed>>, after: ?string, next: ?string, erro: ?array<string, mixed>, buc: array<string, mixed>}
     */
    private function get(string $node, array $query, string $token): array
    {
        try {
            $resposta = $this->http()->get($this->url($node), [...$query, 'access_token' => $token]);
        } catch (ConnectionException) {
            return [
                'conexao_falhou' => true, 'status' => 0, 'data' => [],
                'after' => null, 'next' => null, 'erro' => null, 'buc' => [],
            ];
        }

        /** @var array<string, mixed> $json */
        $json = (array) $resposta->json();

        return [
            'conexao_falhou' => false,
            'status' => $resposta->status(),
            'data' => is_array($json['data'] ?? null)
                ? array_values(array_filter($json['data'], 'is_array'))
                : [],
            'after' => $this->str(data_get($json, 'paging.cursors.after')),
            'next' => $this->str(data_get($json, 'paging.next')),
            'erro' => is_array($json['error'] ?? null) ? $json['error'] : null,
            'buc' => $this->parseBuc($resposta),
        ];
    }

    /**
     * @param  array{status: int, erro: ?array<string, mixed>, buc: array<string, mixed>}  $pagina
     * @param  list<array<string, mixed>>  $acumulado
     */
    private function resultadoDeErro(array $pagina, array $acumulado): MapiResultado
    {
        /** @var array<string, mixed> $erro */
        $erro = $pagina['erro'];
        $throttle = $this->erroThrottle($erro);

        if (! $throttle) {
            Log::channel('meta-ads')->warning('meta-ads: erro não transitório', [
                'code' => $erro['code'] ?? null,
                'message' => $this->str($erro['message'] ?? null),
                'fbtrace_id' => $this->str($erro['fbtrace_id'] ?? null),
            ]);
        }

        return new MapiResultado(
            ok: false,
            httpStatus: $pagina['status'],
            dados: $acumulado,
            errorCode: isset($erro['code']) ? (string) $erro['code'] : null,
            errorMessage: $this->mensagemErro($erro),
            fbtraceId: $this->str($erro['fbtrace_id'] ?? null),
            throttled: $throttle,
            esperarSegundos: ((int) ($pagina['buc']['esperar_segundos'] ?? 0)) ?: null,
            usoBuc: $pagina['buc'],
        );
    }

    /**
     * @return list<string> escopos concedidos ao token
     */
    private function lerEscopos(string $token): array
    {
        try {
            $resposta = $this->http()->get($this->url('me/permissions'), ['access_token' => $token]);
        } catch (ConnectionException) {
            return [];
        }

        $escopos = [];

        foreach ((array) data_get($resposta->json(), 'data', []) as $permissao) {
            if (is_array($permissao)
                && ($permissao['status'] ?? null) === 'granted'
                && isset($permissao['permission'])) {
                $escopos[] = (string) $permissao['permission'];
            }
        }

        return $escopos;
    }

    /**
     * @param  array<string, mixed>  $buc
     */
    private function bucRecuar(array $buc): bool
    {
        return (int) ($buc['uso_pct'] ?? 0) >= (int) config('meta.ads.rate_limit_teto_pct', 85)
            || (int) ($buc['esperar_segundos'] ?? 0) > 0;
    }

    /**
     * @return array{uso_pct: int, esperar_segundos: int, bruto: array<mixed>}|array{}
     */
    private function parseBuc(Response $resposta): array
    {
        $raw = $resposta->header('x-business-use-case-usage');

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $usoPct = 0;
        $esperar = 0;

        foreach ($decoded as $entradas) {
            foreach ((array) $entradas as $entrada) {
                if (! is_array($entrada)) {
                    continue;
                }

                $usoPct = max(
                    $usoPct,
                    (int) ($entrada['call_count'] ?? 0),
                    (int) ($entrada['total_cputime'] ?? 0),
                    (int) ($entrada['total_time'] ?? 0),
                );
                $esperar = max($esperar, (int) ($entrada['estimated_time_to_regain_access'] ?? 0));
            }
        }

        return ['uso_pct' => $usoPct, 'esperar_segundos' => $esperar, 'bruto' => $decoded];
    }

    /**
     * @param  array<string, mixed>  $erro
     */
    private function erroThrottle(array $erro): bool
    {
        return in_array((int) ($erro['code'] ?? 0), self::CODIGOS_THROTTLE, true)
            || ($erro['is_transient'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $erro
     */
    private function mensagemErro(array $erro): ?string
    {
        $mensagem = trim(implode(' — ', array_filter([
            $this->str($erro['message'] ?? null),
            $this->str($erro['error_user_msg'] ?? null),
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

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('meta.ads.http_timeout', 15))
            ->retry(2, 300, when: fn (\Throwable $e): bool => $e instanceof ConnectionException, throw: false);
    }

    private function url(string $node): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim((string) config('meta.ads.base_url'), '/'),
            (string) config('meta.ads.api_version'),
            ltrim($node, '/'),
        );
    }

    private function str(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
