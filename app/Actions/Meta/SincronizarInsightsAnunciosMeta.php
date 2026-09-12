<?php

namespace App\Actions\Meta;

use App\Enums\MetaAdsNivel;
use App\Enums\MetaAdsSyncStatus;
use App\Enums\MetaAdsSyncTipo;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use App\Models\MetaAdsSyncExecucao;
use App\Support\Meta\MarketingApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Puxa os insights diários (nível anúncio) de uma conta para uma janela e faz
 * `updateOrCreate` por `(objeto_id, nivel, referencia)`. Só o nível anúncio é
 * gravado — campanha/conjunto/conta são agregação na leitura. Idempotente.
 */
class SincronizarInsightsAnunciosMeta
{
    public function __construct(private readonly MarketingApiClient $client) {}

    public function __invoke(MetaAdsConta $conta, CarbonImmutable $de, CarbonImmutable $ate): MetaAdsSyncExecucao
    {
        $inicio = now();

        $execucao = MetaAdsSyncExecucao::query()->create([
            'meta_ads_conta_id' => $conta->id,
            'tipo' => MetaAdsSyncTipo::Insights,
            'status' => MetaAdsSyncStatus::Ok,
            'janela_inicio' => $de,
            'janela_fim' => $ate,
            'iniciado_em' => $inicio,
        ]);

        $resultado = $this->client->insightsAnuncios($conta, $de, $ate);
        $afetados = 0;

        foreach ($resultado->dados as $linha) {
            $adId = $this->str($linha['ad_id'] ?? null);
            $data = $this->str($linha['date_start'] ?? null);

            if ($adId === null || $data === null) {
                continue;
            }

            $resultados = $this->contarResultados($linha['actions'] ?? null);
            $investimento = (int) round(((float) ($linha['spend'] ?? 0)) * 100);

            MetaAdsInsightDiario::query()->updateOrCreate(
                [
                    'objeto_id' => $adId,
                    'nivel' => MetaAdsNivel::Anuncio->value,
                    'referencia' => CarbonImmutable::parse($data)->startOfDay(),
                ],
                [
                    'meta_ads_conta_id' => $conta->id,
                    'investimento_centavos' => $investimento,
                    'impressoes' => (int) ($linha['impressions'] ?? 0),
                    'cliques' => (int) ($linha['clicks'] ?? 0),
                    'cliques_link' => (int) ($linha['inline_link_clicks'] ?? 0),
                    'alcance' => (int) ($linha['reach'] ?? 0),
                    'cpc_centavos' => isset($linha['cpc']) ? (int) round(((float) $linha['cpc']) * 100) : null,
                    'cpm_centavos' => isset($linha['cpm']) ? (int) round(((float) $linha['cpm']) * 100) : null,
                    'custo_por_resultado_centavos' => $this->custoPorResultado($linha, $investimento, $resultados),
                    'ctr' => isset($linha['ctr']) && is_numeric($linha['ctr']) ? (string) $linha['ctr'] : null,
                    'frequencia' => isset($linha['frequency']) && is_numeric($linha['frequency']) ? (string) $linha['frequency'] : null,
                    'resultados' => $resultados,
                    'acoes' => is_array($linha['actions'] ?? null) ? $linha['actions'] : null,
                    'bruto' => $linha,
                ],
            );

            $afetados++;
        }

        if ($resultado->throttled) {
            Log::channel('meta-ads')->warning('meta-ads: insights parciais (rate limit)', [
                'conta' => $conta->ad_account_id,
                'janela' => $de->toDateString().' → '.$ate->toDateString(),
                'esperar' => $resultado->esperarSegundos,
            ]);
        }

        $execucao->forceFill([
            'status' => $resultado->throttled
                ? MetaAdsSyncStatus::Parcial
                : ($resultado->ok || $afetados > 0 ? MetaAdsSyncStatus::Ok : MetaAdsSyncStatus::Erro),
            'objetos_afetados' => $afetados,
            'duracao_ms' => (int) $inicio->diffInMilliseconds(now()),
            'erro' => $resultado->throttled || ! $resultado->ok ? $resultado->errorMessage : null,
            'concluido_em' => now(),
        ])->save();

        if (! $resultado->throttled) {
            $conta->forceFill(['insights_sincronizados_em' => now()])->saveQuietly();
        }

        Log::channel('meta-ads')->info('meta-ads: sync de insights', [
            'conta' => $conta->ad_account_id,
            'status' => $execucao->status->value,
            'objetos' => $afetados,
            'janela' => $de->toDateString().' → '.$ate->toDateString(),
            'duracao_ms' => $execucao->duracao_ms,
        ]);

        return $execucao->refresh();
    }

    /**
     * Nº de leads do dia. Os `action_type` de lead contam a mesma conversão em
     * janelas de atribuição diferentes → usa o MAIOR, não soma.
     */
    private function contarResultados(mixed $actions): int
    {
        if (! is_array($actions)) {
            return 0;
        }

        $tipos = array_map('strval', (array) config('meta.ads.acoes_resultado', []));
        $maior = 0;

        foreach ($actions as $acao) {
            if (is_array($acao)
                && in_array($this->str($acao['action_type'] ?? null), $tipos, true)
                && is_numeric($acao['value'] ?? null)) {
                $maior = max($maior, (int) $acao['value']);
            }
        }

        return $maior;
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    private function custoPorResultado(array $linha, int $investimento, int $resultados): ?int
    {
        $tipos = array_map('strval', (array) config('meta.ads.acoes_resultado', []));

        foreach ((array) ($linha['cost_per_action_type'] ?? []) as $custo) {
            if (is_array($custo)
                && in_array($this->str($custo['action_type'] ?? null), $tipos, true)
                && is_numeric($custo['value'] ?? null)) {
                return (int) round(((float) $custo['value']) * 100);
            }
        }

        return $resultados > 0 ? (int) round($investimento / $resultados) : null;
    }

    private function str(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
