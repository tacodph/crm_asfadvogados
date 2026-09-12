<?php

namespace App\Enums;

/**
 * `status` de configuração de um objeto de anúncio (o que o anunciante definiu —
 * distinto de `effective_status`, que a Meta calcula e guardamos como string crua).
 */
enum MetaAdsStatus: string
{
    case Ativo = 'ACTIVE';
    case Pausado = 'PAUSED';
    case Deletado = 'DELETED';
    case Arquivado = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Pausado => 'Pausado',
            self::Deletado => 'Excluído',
            self::Arquivado => 'Arquivado',
        };
    }

    public static function fromMeta(?string $valor): self
    {
        return self::tryFrom(strtoupper(trim((string) $valor))) ?? self::Pausado;
    }
}
