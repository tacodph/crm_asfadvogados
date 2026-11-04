<?php

namespace App\Enums;

enum TipoTarefaNegociacao: string
{
    case FollowUp = 'follow_up';
    case Reuniao = 'reuniao';
    case Ligacao = 'ligacao';
    case Tarefa = 'tarefa';

    public function label(): string
    {
        return match ($this) {
            self::FollowUp => 'Follow-up',
            self::Reuniao => 'Reunião',
            self::Ligacao => 'Ligação',
            self::Tarefa => 'Tarefa',
        };
    }

    /**
     * Concluir uma tarefa deste tipo significa que houve contato com o lead.
     */
    public function registraContato(): bool
    {
        return match ($this) {
            self::FollowUp, self::Reuniao, self::Ligacao => true,
            self::Tarefa => false,
        };
    }
}
