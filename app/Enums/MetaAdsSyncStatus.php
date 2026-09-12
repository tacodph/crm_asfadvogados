<?php

namespace App\Enums;

enum MetaAdsSyncStatus: string
{
    case Ok = 'ok';
    case Parcial = 'parcial';
    case Erro = 'erro';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Parcial => 'Parcial',
            self::Erro => 'Erro',
        };
    }
}
