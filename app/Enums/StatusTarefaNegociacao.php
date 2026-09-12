<?php

namespace App\Enums;

enum StatusTarefaNegociacao: string
{
    case Pendente = 'pendente';
    case EmAndamento = 'em_andamento';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::EmAndamento => 'Em andamento',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    public function isAberta(): bool
    {
        return match ($this) {
            self::Pendente, self::EmAndamento => true,
            self::Concluida, self::Cancelada => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function abertos(): array
    {
        return [
            self::Pendente->value,
            self::EmAndamento->value,
        ];
    }
}
