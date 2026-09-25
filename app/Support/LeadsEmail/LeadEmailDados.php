<?php

declare(strict_types=1);

namespace App\Support\LeadsEmail;

use Carbon\CarbonImmutable;

final readonly class LeadEmailDados
{
    public function __construct(
        public CarbonImmutable $dataCadastro,
        public string $nome,
        public string $telefone,
        public string $email,
        public float $valorDivida,
        public string $tipoPessoa, // 'pf' | 'pj'
        public string $campanha,
        public string $conjunto,
        public string $anuncio,
    ) {}
}
