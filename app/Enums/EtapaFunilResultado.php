<?php

namespace App\Enums;

enum EtapaFunilResultado: string
{
    case Aberta = 'aberta';
    case Ganho = 'ganho';
    case Perdido = 'perdido';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Ganho => 'Ganho (contrato)',
            self::Perdido => 'Encerrado / perdido',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Ganho, self::Perdido => true,
            self::Aberta => false,
        };
    }
}
