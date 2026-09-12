<?php

namespace App\Actions\Meta;

use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\Negociacao;
use App\Support\Meta\ResolverCampanhaConversao;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolve a qual anúncio/conjunto/campanha do Meta Ads uma negociação pertence,
 * a partir dos parâmetros crus capturados na entrada (`origem_utm`). Ordem de
 * resolução em `documents/api_meta/arquitetura_ads.md` §5.2. Best-effort:
 * exceção é logada e engolida — nunca quebra o save da negociação. A atribuição
 * não regride (só é refeita com `$forcar`).
 */
class AtribuirNegociacaoAnuncioMeta
{
    public function __construct(private readonly ResolverCampanhaConversao $resolverCampanha) {}

    public function __invoke(Negociacao $negociacao, bool $forcar = false): void
    {
        if ($negociacao->meta_ad_id !== null && ! $forcar) {
            return;
        }

        try {
            $atribuicao = $this->resolver($negociacao);
        } catch (Throwable $e) {
            Log::channel('meta-ads')->warning('meta-ads: falha na atribuição da negociação', [
                'negociacao_id' => $negociacao->id,
                'erro' => $e->getMessage(),
            ]);

            return;
        }

        if ($atribuicao === null) {
            return;
        }

        $negociacao->forceFill([
            'meta_ad_id' => $atribuicao['ad_id'] ?? null,
            'meta_adset_id' => $atribuicao['adset_id'] ?? null,
            'meta_campaign_id' => $atribuicao['campaign_id'] ?? null,
            'meta_atribuicao_origem' => $atribuicao['origem'],
            'meta_atribuido_em' => now(),
        ])->saveQuietly();
    }

    /**
     * @return array{ad_id?: string, adset_id?: ?string, campaign_id?: ?string, origem: string}|null
     */
    private function resolver(Negociacao $negociacao): ?array
    {
        $utm = is_array($negociacao->origem_utm) ? $negociacao->origem_utm : [];

        // 1 + 2 — id de anúncio explícito (param dedicado) ou utm_content numérico.
        $adId = $this->str($utm['meta_ad_id'] ?? null) ?? $this->numerico($utm['utm_content'] ?? null);

        if ($adId !== null) {
            $anuncio = MetaAdsAnuncio::query()
                ->with(['conjunto:id,meta_adset_id', 'campanha:id,meta_campaign_id'])
                ->where('meta_ad_id', $adId)
                ->first();

            if ($anuncio !== null) {
                return [
                    'ad_id' => $anuncio->meta_ad_id,
                    'adset_id' => $anuncio->conjunto?->meta_adset_id,
                    'campaign_id' => $anuncio->campanha?->meta_campaign_id,
                    'origem' => ($this->str($utm['fonte'] ?? null) === 'lead_ad') ? 'lead_ad' : 'utm',
                ];
            }
        }

        // 3 — utm_campaign por meta_campaign_id ou por nome.
        $campanhaChave = $this->str($utm['utm_campaign'] ?? null);

        if ($campanhaChave !== null) {
            $campanha = MetaAdsCampanha::query()
                ->where(fn ($q) => $q
                    ->where('meta_campaign_id', $campanhaChave)
                    ->orWhereRaw('lower(nome) = ?', [mb_strtolower($campanhaChave)]))
                ->first();

            if ($campanha !== null) {
                return ['campaign_id' => $campanha->meta_campaign_id, 'origem' => 'utm'];
            }
        }

        // 4 — fbclid presente e nada casou: "veio do Meta, anúncio desconhecido".
        if ($this->str($utm['fbclid'] ?? null) !== null) {
            return ['origem' => 'fbclid'];
        }

        // 5 — mapa manual por slug da campanha do CRM (reusa o resolver da CAPI).
        $slug = ($this->resolverCampanha)($negociacao)?->slug;
        $mapa = config('meta.ads.mapa_campanha');
        $mapa = is_array($mapa) ? $mapa : [];

        if ($slug !== null && isset($mapa[$slug])) {
            return ['campaign_id' => (string) $mapa[$slug], 'origem' => 'manual'];
        }

        return null;
    }

    private function numerico(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^\d{5,}$/', $valor) === 1 ? $valor : null;
    }

    private function str(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
