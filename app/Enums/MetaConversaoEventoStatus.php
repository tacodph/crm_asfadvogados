<?php

namespace App\Enums;

enum MetaConversaoEventoStatus: string
{
    case Pendente = 'pendente';
    case Enviado = 'enviado';
    case Erro = 'erro';
    case Descartado = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Enviado => 'Enviado',
            self::Erro => 'Erro',
            self::Descartado => 'Descartado',
        };
    }
}
