<?php

namespace App\Enums;

enum StatusProposta: string
{
    case Rascunho = 'rascunho';
    case Enviada = 'enviada';
    case EmNegociacao = 'em_negociacao';
    case Aceita = 'aceita';
    case Recusada = 'recusada';
    case Expirada = 'expirada';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Enviada => 'Enviada',
            self::EmNegociacao => 'Em negociação',
            self::Aceita => 'Aceita',
            self::Recusada => 'Recusada',
            self::Expirada => 'Expirada',
        };
    }

    /**
     * @return array{fundo: string, texto: string}
     */
    public function cores(): array
    {
        return match ($this) {
            self::Rascunho => ['fundo' => '#F4F2EC', 'texto' => '#77808E'],
            self::Enviada => ['fundo' => '#EEF1F6', 'texto' => '#3F5E8C'],
            self::EmNegociacao => ['fundo' => '#FBF1DF', 'texto' => '#8C6F3F'],
            self::Aceita => ['fundo' => '#E7F0EE', 'texto' => '#14574F'],
            self::Recusada, self::Expirada => ['fundo' => '#F8ECE9', 'texto' => '#9B3B2F'],
        };
    }

    public function emAberto(): bool
    {
        return match ($this) {
            self::Rascunho, self::Enviada, self::EmNegociacao => true,
            self::Aceita, self::Recusada, self::Expirada => false,
        };
    }
}
