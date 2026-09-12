<?php

namespace App\Enums;

/**
 * Objetivo ("outcome") de uma campanha do Meta Ads. `fromMeta()` normaliza os
 * valores atuais (ODAX) e os legados; qualquer coisa fora do mapa vira `Outro`.
 */
enum MetaAdsObjetivo: string
{
    case OutcomeLeads = 'OUTCOME_LEADS';
    case OutcomeTraffic = 'OUTCOME_TRAFFIC';
    case OutcomeEngagement = 'OUTCOME_ENGAGEMENT';
    case OutcomeSales = 'OUTCOME_SALES';
    case OutcomeAwareness = 'OUTCOME_AWARENESS';
    case OutcomeAppPromotion = 'OUTCOME_APP_PROMOTION';
    case Outro = 'OUTRO';

    public function label(): string
    {
        return match ($this) {
            self::OutcomeLeads => 'Leads',
            self::OutcomeTraffic => 'Tráfego',
            self::OutcomeEngagement => 'Engajamento',
            self::OutcomeSales => 'Vendas',
            self::OutcomeAwareness => 'Reconhecimento',
            self::OutcomeAppPromotion => 'Promoção de app',
            self::Outro => 'Outro',
        };
    }

    public static function fromMeta(?string $valor): self
    {
        return match (strtoupper(trim((string) $valor))) {
            'OUTCOME_LEADS', 'LEAD_GENERATION' => self::OutcomeLeads,
            'OUTCOME_TRAFFIC', 'LINK_CLICKS' => self::OutcomeTraffic,
            'OUTCOME_ENGAGEMENT', 'POST_ENGAGEMENT', 'PAGE_LIKES', 'EVENT_RESPONSES' => self::OutcomeEngagement,
            'OUTCOME_SALES', 'CONVERSIONS', 'CATALOG_SALES', 'PRODUCT_CATALOG_SALES' => self::OutcomeSales,
            'OUTCOME_AWARENESS', 'BRAND_AWARENESS', 'REACH' => self::OutcomeAwareness,
            'OUTCOME_APP_PROMOTION', 'APP_INSTALLS', 'MOBILE_APP_INSTALLS' => self::OutcomeAppPromotion,
            default => self::Outro,
        };
    }
}
