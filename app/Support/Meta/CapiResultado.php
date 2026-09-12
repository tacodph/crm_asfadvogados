<?php

namespace App\Support\Meta;

/**
 * Resultado normalizado de uma chamada à Graph API da Meta.
 */
final readonly class CapiResultado
{
    /**
     * @param  array<string, mixed>  $responseBody
     */
    public function __construct(
        public bool $ok,
        public ?int $httpStatus = null,
        public ?int $eventsReceived = null,
        public ?string $fbtraceId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $responseBody = [],
    ) {}

    /**
     * A falha vale nova tentativa na fila? (rede, throttling, erro do servidor)
     */
    public function retentavel(): bool
    {
        if ($this->httpStatus === null) {
            return true;
        }

        return $this->httpStatus === 408
            || $this->httpStatus === 429
            || $this->httpStatus >= 500;
    }
}
