<?php

namespace App\Enums;

/**
 * Nível na hierarquia do Meta Ads. `metaLevel()` devolve o valor que a Graph
 * API espera no parâmetro `level` do `/insights`.
 */
enum MetaAdsNivel: string
{
    case Conta = 'conta';
    case Campanha = 'campanha';
    case Conjunto = 'conjunto';
    case Anuncio = 'anuncio';

    public function metaLevel(): string
    {
        return match ($this) {
            self::Conta => 'account',
            self::Campanha => 'campaign',
            self::Conjunto => 'adset',
            self::Anuncio => 'ad',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Conta => 'Conta',
            self::Campanha => 'Campanha',
            self::Conjunto => 'Conjunto',
            self::Anuncio => 'Anúncio',
        };
    }
}
