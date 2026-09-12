<?php

namespace App\Actions\Meta;

use App\Enums\MetaAdsObjetivo;
use App\Enums\MetaAdsStatus;
use App\Enums\MetaAdsSyncStatus;
use App\Enums\MetaAdsSyncTipo;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsSyncExecucao;
use App\Support\Meta\MapiResultado;
use App\Support\Meta\MarketingApiClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Espelha a estrutura do Meta Ads (campanha → conjunto → anúncio) da conta nas
 * tabelas locais: `updateOrCreate` por id da Meta, órfãos (que não voltaram)
 * marcados `arquivado_em`. Idempotente. Nunca lança.
 */
class SincronizarEstruturaAnunciosMeta
{
    public function __construct(private readonly MarketingApiClient $client) {}

    public function __invoke(MetaAdsConta $conta): MetaAdsSyncExecucao
    {
        $inicio = now();

        $execucao = MetaAdsSyncExecucao::query()->create([
            'meta_ads_conta_id' => $conta->id,
            'tipo' => MetaAdsSyncTipo::Estrutura,
            'status' => MetaAdsSyncStatus::Ok,
            'iniciado_em' => $inicio,
        ]);

        $afetados = 0;
        $problemas = [];

        $campanhas = $this->sincronizarCampanhas($conta, $afetados, $problemas);
        $conjuntos = $this->sincronizarConjuntos($conta, $afetados, $problemas);
        $anuncios = $this->sincronizarAnuncios($conta, $afetados, $problemas);

        $parcial = $problemas !== [];

        // Só arquiva órfãos numa rodada completa — senão apagaria o que só não
        // veio por rate limit / erro transitório.
        if (! $parcial) {
            $this->arquivarOrfaos(MetaAdsCampanha::query()->where('meta_ads_conta_id', $conta->id), 'meta_campaign_id', $campanhas);
            $this->arquivarOrfaos(MetaAdsConjunto::query()->where('meta_ads_conta_id', $conta->id), 'meta_adset_id', $conjuntos);
            $this->arquivarOrfaos(MetaAdsAnuncio::query()->where('meta_ads_conta_id', $conta->id), 'meta_ad_id', $anuncios);
        }

        $execucao->forceFill([
            'status' => $parcial ? MetaAdsSyncStatus::Parcial : MetaAdsSyncStatus::Ok,
            'objetos_afetados' => $afetados,
            'duracao_ms' => (int) $inicio->diffInMilliseconds(now()),
            'erro' => $parcial ? implode(' | ', $problemas) : null,
            'concluido_em' => now(),
        ])->save();

        $conta->forceFill(['estrutura_sincronizada_em' => now()])->saveQuietly();

        Log::channel('meta-ads')->info('meta-ads: sync de estrutura', [
            'conta' => $conta->ad_account_id,
            'status' => $execucao->status->value,
            'objetos' => $afetados,
            'duracao_ms' => $execucao->duracao_ms,
        ]);

        return $execucao->refresh();
    }

    /**
     * @param  list<string>  $problemas
     * @return list<string> ids da Meta vistos nesta rodada
     */
    private function sincronizarCampanhas(MetaAdsConta $conta, int &$afetados, array &$problemas): array
    {
        $r = $this->client->listar($conta, $conta->nodeId().'/campaigns', [
            'fields' => 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,start_time,stop_time',
            'limit' => 200,
        ]);

        $vistos = [];

        foreach ($r->dados as $linha) {
            $id = $this->str($linha['id'] ?? null);
            if ($id === null) {
                continue;
            }

            MetaAdsCampanha::query()->updateOrCreate(['meta_campaign_id' => $id], [
                'meta_ads_conta_id' => $conta->id,
                'nome' => (string) ($linha['name'] ?? $id),
                'objetivo' => MetaAdsObjetivo::fromMeta($this->str($linha['objective'] ?? null)),
                'status' => MetaAdsStatus::fromMeta($this->str($linha['status'] ?? null)),
                'effective_status' => $this->str($linha['effective_status'] ?? null),
                'orcamento_diario_centavos' => $this->centavos($linha['daily_budget'] ?? null),
                'orcamento_total_centavos' => $this->centavos($linha['lifetime_budget'] ?? null),
                'inicio_em' => $this->parseData($this->str($linha['start_time'] ?? null)),
                'fim_em' => $this->parseData($this->str($linha['stop_time'] ?? null)),
                'bruto' => $linha,
                'sincronizado_em' => now(),
                'arquivado_em' => null,
            ]);

            $vistos[] = $id;
            $afetados++;
        }

        $this->registrarProblema($r, 'campanhas', $problemas);

        return $vistos;
    }

    /**
     * @param  list<string>  $problemas
     * @return list<string>
     */
    private function sincronizarConjuntos(MetaAdsConta $conta, int &$afetados, array &$problemas): array
    {
        $r = $this->client->listar($conta, $conta->nodeId().'/adsets', [
            'fields' => 'id,name,campaign_id,status,effective_status,optimization_goal,daily_budget,lifetime_budget',
            'limit' => 200,
        ]);

        $campanhaPorMetaId = MetaAdsCampanha::query()
            ->where('meta_ads_conta_id', $conta->id)
            ->pluck('id', 'meta_campaign_id');

        $vistos = [];

        foreach ($r->dados as $linha) {
            $id = $this->str($linha['id'] ?? null);
            $campanhaId = $campanhaPorMetaId[$this->str($linha['campaign_id'] ?? null)] ?? null;

            if ($id === null || $campanhaId === null) {
                $problemas[] = 'conjunto sem campanha ainda sincronizada';

                continue;
            }

            MetaAdsConjunto::query()->updateOrCreate(['meta_adset_id' => $id], [
                'meta_ads_conta_id' => $conta->id,
                'meta_ads_campanha_id' => $campanhaId,
                'nome' => (string) ($linha['name'] ?? $id),
                'optimization_goal' => $this->str($linha['optimization_goal'] ?? null),
                'status' => MetaAdsStatus::fromMeta($this->str($linha['status'] ?? null)),
                'effective_status' => $this->str($linha['effective_status'] ?? null),
                'orcamento_diario_centavos' => $this->centavos($linha['daily_budget'] ?? null),
                'orcamento_total_centavos' => $this->centavos($linha['lifetime_budget'] ?? null),
                'bruto' => $linha,
                'sincronizado_em' => now(),
                'arquivado_em' => null,
            ]);

            $vistos[] = $id;
            $afetados++;
        }

        $this->registrarProblema($r, 'conjuntos', $problemas);

        return $vistos;
    }

    /**
     * @param  list<string>  $problemas
     * @return list<string>
     */
    private function sincronizarAnuncios(MetaAdsConta $conta, int &$afetados, array &$problemas): array
    {
        $r = $this->client->listar($conta, $conta->nodeId().'/ads', [
            'fields' => 'id,name,adset_id,campaign_id,status,effective_status,creative{title,body,thumbnail_url,object_story_spec}',
            'limit' => 200,
        ]);

        $conjuntoPorMetaId = MetaAdsConjunto::query()
            ->where('meta_ads_conta_id', $conta->id)
            ->get(['id', 'meta_adset_id', 'meta_ads_campanha_id'])
            ->keyBy('meta_adset_id');

        $vistos = [];

        foreach ($r->dados as $linha) {
            $id = $this->str($linha['id'] ?? null);
            $conjunto = $conjuntoPorMetaId[$this->str($linha['adset_id'] ?? null)] ?? null;

            if ($id === null || $conjunto === null) {
                $problemas[] = 'anúncio sem conjunto ainda sincronizado';

                continue;
            }

            MetaAdsAnuncio::query()->updateOrCreate(['meta_ad_id' => $id], [
                'meta_ads_conta_id' => $conta->id,
                'meta_ads_campanha_id' => $conjunto->meta_ads_campanha_id,
                'meta_ads_conjunto_id' => $conjunto->id,
                'nome' => (string) ($linha['name'] ?? $id),
                'status' => MetaAdsStatus::fromMeta($this->str($linha['status'] ?? null)),
                'effective_status' => $this->str($linha['effective_status'] ?? null),
                'criativo_resumo' => $this->criativoResumo($linha['creative'] ?? null),
                'bruto' => $linha,
                'sincronizado_em' => now(),
                'arquivado_em' => null,
            ]);

            $vistos[] = $id;
            $afetados++;
        }

        $this->registrarProblema($r, 'anúncios', $problemas);

        return $vistos;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $vistos
     */
    private function arquivarOrfaos(Builder $query, string $coluna, array $vistos): void
    {
        $query->whereNull('arquivado_em')
            ->whereNotIn($coluna, $vistos !== [] ? $vistos : ['__nenhum__'])
            ->update(['arquivado_em' => now()]);
    }

    /**
     * @param  list<string>  $problemas
     */
    private function registrarProblema(MapiResultado $r, string $edge, array &$problemas): void
    {
        if ($r->throttled) {
            $problemas[] = "{$edge}: rate limit".($r->esperarSegundos !== null ? " (aguardar {$r->esperarSegundos}s)" : '');
        } elseif (! $r->ok && $r->errorMessage !== null) {
            $problemas[] = "{$edge}: {$r->errorMessage}";
            Log::channel('meta-ads')->warning('meta-ads: falha ao sincronizar estrutura', [
                'edge' => $edge, 'code' => $r->errorCode,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $creative
     * @return array<string, mixed>|null
     */
    private function criativoResumo(mixed $creative): ?array
    {
        if (! is_array($creative)) {
            return null;
        }

        return array_filter([
            'titulo' => $this->str($creative['title'] ?? null)
                ?? $this->str(data_get($creative, 'object_story_spec.link_data.name')),
            'corpo' => $this->str($creative['body'] ?? null)
                ?? $this->str(data_get($creative, 'object_story_spec.link_data.message')),
            'thumb_url' => $this->str($creative['thumbnail_url'] ?? null),
        ], static fn ($v): bool => $v !== null);
    }

    private function centavos(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }

    private function parseData(?string $valor): ?Carbon
    {
        if ($valor === null) {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    private function str(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
