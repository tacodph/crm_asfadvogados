<?php

namespace App\Enums;

enum MetaAdsSyncTipo: string
{
    case Estrutura = 'estrutura';
    case Insights = 'insights';

    public function label(): string
    {
        return match ($this) {
            self::Estrutura => 'Estrutura',
            self::Insights => 'Insights',
        };
    }
}
