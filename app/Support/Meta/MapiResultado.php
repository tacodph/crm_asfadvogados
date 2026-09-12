<?php

namespace App\Support\Meta;

/**
 * Resultado normalizado de uma operação de leitura na API de Marketing da Meta
 * (uma ou várias páginas). `dados` já traz as linhas acumuladas de todas as
 * páginas percorridas; `throttled` indica que a paginação parou por rate limit.
 */
final readonly class MapiResultado
{
    /**
     * @param  list<array<string, mixed>>  $dados
     * @param  array<string, mixed>  $usoBuc  X-Business-Use-Case-Usage já parseado
     */
    public function __construct(
        public bool $ok,
        public ?int $httpStatus = null,
        public array $dados = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?string $fbtraceId = null,
        public bool $throttled = false,
        public ?int $esperarSegundos = null,
        public array $usoBuc = [],
    ) {}

    /**
     * A falha/interrupção vale nova tentativa na fila? (rate limit, rede, 5xx)
     */
    public function retentavel(): bool
    {
        return $this->throttled
            || $this->httpStatus === null
            || $this->httpStatus === 408
            || $this->httpStatus === 429
            || $this->httpStatus >= 500;
    }
}
