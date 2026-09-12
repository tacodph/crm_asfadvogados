<?php

namespace App\Actions\Meta;

use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEstatistica;
use App\Support\Meta\ConversionsApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Grava um snapshot diário (`meta_conversao_estatisticas`) do que a Graph API
 * consegue devolver sobre o Pixel de uma campanha. Best-effort: o campo
 * confiável é `pixel_last_fired_at`; o resto de `/stats` vai cru em
 * `payload_bruto`. Nunca lança — falha de API vira `warning` no log.
 */
class SincronizarEstatisticasPixelMeta
{
    public function __construct(private readonly ConversionsApiClient $client) {}

    public function __invoke(MetaConversaoConfig $config, ?CarbonImmutable $dia = null): MetaConversaoEstatistica
    {
        $dia ??= CarbonImmutable::now()->startOfDay();

        $lido = ['name' => null, 'last_fired_time' => null, 'pixel_ok' => false, 'stats' => []];

        try {
            $lido = $this->client->lerEstatisticas($config);
        } catch (\Throwable $e) {
            Log::channel('meta-capi')->warning('meta-capi: falha ao sincronizar estatísticas do Pixel', [
                'campanha' => $config->slug,
                'erro' => $e->getMessage(),
            ]);
        }

        $stats = $lido['stats'];

        return MetaConversaoEstatistica::query()->updateOrCreate(
            [
                'meta_conversao_config_id' => $config->id,
                'referencia' => $dia->startOfDay(),
            ],
            [
                'pixel_last_fired_at' => $this->parseData($lido['last_fired_time']) ?? $config->ultimo_evento_em,
                // ponytail: chaves do /stats variam e não são documentadas de forma estável —
                // tenta as mais comuns e cai pra null. Ajustar quando houver payload real.
                'eventos_servidor' => $this->inteiro($stats, ['server_events', 'server', 'count_server']),
                'eventos_navegador' => $this->inteiro($stats, ['browser_events', 'browser', 'count_browser']),
                'eventos_deduplicados' => $this->inteiro($stats, ['deduplicated_events', 'deduped', 'count_deduplicated']),
                'match_rate' => $this->numero($stats, ['match_rate', 'event_match_quality']),
                'payload_bruto' => $lido,
            ],
        );
    }

    private function parseData(?string $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  list<string>  $chaves
     */
    private function inteiro(array $stats, array $chaves): ?int
    {
        foreach ($chaves as $chave) {
            $valor = data_get($stats, $chave);

            if (is_numeric($valor)) {
                return (int) $valor;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  list<string>  $chaves
     */
    private function numero(array $stats, array $chaves): ?string
    {
        foreach ($chaves as $chave) {
            $valor = data_get($stats, $chave);

            if (is_numeric($valor)) {
                return (string) $valor;
            }
        }

        return null;
    }
}
