<?php

declare(strict_types=1);

namespace App\Actions\LeadsEmail;

use App\Models\Contato;
use App\Models\Negociacao;

final readonly class RegistrarLeadEmailResultado
{
    public function __construct(
        public Contato $contato,
        public Negociacao $negociacao,
    ) {}
}
