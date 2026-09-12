<?php

namespace App\Support\Meta;

use App\Enums\MetaAdsNivel;
use App\Models\EtapaFunil;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use App\Models\Negociacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Serviço de leitura: junta o investimento (`meta_ads_insights_diarios`, nível
 * anúncio, agregado pela hierarquia) com as negociações atribuídas do CRM
 * (leads, reuniões, contratos, receita) e produz CPL real, custo por reunião,
 * custo por contrato e ROAS por objeto e por dia. Dinheiro sempre em centavos.
 */
class MetricasInvestimentoMeta
{
    /**
     * @return Collection<int, MetricaInvestimento>
     */
    public function para(
        MetaAdsConta $conta,
        \DateTimeInterface $de,
        \DateTimeInterface $ate,
        MetaAdsNivel $nivel = MetaAdsNivel::Campanha,
    ): Collection {
        $objetos = $this->objetos($conta, $nivel);
        $insights = $this->investimentoPorObjeto($conta, $de, $ate, $nivel);
        $negociacoes = $this->negociacoesPorChaveMeta($de, $ate, $nivel);

        $idsReuniao = $this->etapasAtingidas(['Schedule'], ['reuni']);
        $idsContrato = $this->etapasAtingidas(['Purchase'], ['ganho', 'fechado']);

        $metricas = [];

        foreach ($objetos as $obj) {
            $inv = $insights[$obj['id']] ?? [];
            $grupo = $negociacoes[$obj['meta_id']] ?? [];

            $contratos = array_filter($grupo, fn (array $n): bool => in_array($n['etapa_funil_id'], $idsContrato, true));

            $metricas[] = new MetricaInvestimento(
                nivel: $nivel->value,
                objetoId: $obj['id'],
                metaId: $obj['meta_id'],
                nome: $obj['nome'],
                effectiveStatus: $obj['effective_status'],
                investidoCentavos: (int) ($inv['investido'] ?? 0),
                impressoes: (int) ($inv['impressoes'] ?? 0),
                cliques: (int) ($inv['cliques'] ?? 0),
                cliquesLink: (int) ($inv['cliques_link'] ?? 0),
                alcance: (int) ($inv['alcance'] ?? 0),
                leadsMeta: (int) ($inv['leads_meta'] ?? 0),
                leadsCrm: count($grupo),
                reunioes: count(array_filter($grupo, fn (array $n): bool => in_array($n['etapa_funil_id'], $idsReuniao, true))),
                contratos: count($contratos),
                receitaCentavos: (int) round(array_sum(array_column($contratos, 'valor')) * 100),
            );
        }

        return collect($metricas);
    }

    /**
     * Investido × leads (CRM) por dia, para o gráfico da tela.
     *
     * @return Collection<int, array{referencia: string, investido_centavos: int, leads_crm: int}>
     */
    public function serieDiaria(MetaAdsConta $conta, \DateTimeInterface $de, \DateTimeInterface $ate): Collection
    {
        $investPorDia = MetaAdsInsightDiario::query()
            ->join('meta_ads_anuncios as a', 'a.meta_ad_id', '=', 'meta_ads_insights_diarios.objeto_id')
            ->where('a.meta_ads_conta_id', $conta->id)
            ->where('meta_ads_insights_diarios.nivel', MetaAdsNivel::Anuncio->value)
            ->whereDate('meta_ads_insights_diarios.referencia', '>=', $de->format('Y-m-d'))
            ->whereDate('meta_ads_insights_diarios.referencia', '<=', $ate->format('Y-m-d'))
            ->groupByRaw('date(meta_ads_insights_diarios.referencia)')
            ->selectRaw('date(meta_ads_insights_diarios.referencia) as dia, SUM(meta_ads_insights_diarios.investimento_centavos) as investido')
            ->pluck('investido', 'dia');

        $campanhaIds = $conta->campanhas()->pluck('meta_campaign_id')->all();

        $leadsPorDia = Negociacao::query()
            ->whereIn('meta_campaign_id', $campanhaIds !== [] ? $campanhaIds : ['__nenhum__'])
            ->whereBetween('created_at', [$de, $ate])
            ->get(['created_at'])
            ->groupBy(fn (Negociacao $n): string => $n->created_at->toDateString())
            ->map->count();

        $serie = collect();
        $dia = CarbonImmutable::parse($de->format('Y-m-d'));
        $fim = CarbonImmutable::parse($ate->format('Y-m-d'));

        while ($dia->lessThanOrEqualTo($fim)) {
            $chave = $dia->toDateString();
            $serie->push([
                'referencia' => $chave,
                'investido_centavos' => (int) ($investPorDia[$chave] ?? 0),
                'leads_crm' => (int) ($leadsPorDia[$chave] ?? 0),
            ]);
            $dia = $dia->addDay();
        }

        return $serie;
    }

    /**
     * @return list<array{id: int, meta_id: string, nome: string, effective_status: string|null}>
     */
    private function objetos(MetaAdsConta $conta, MetaAdsNivel $nivel): array
    {
        $metaCol = match ($nivel) {
            MetaAdsNivel::Anuncio => 'meta_ad_id',
            MetaAdsNivel::Conjunto => 'meta_adset_id',
            default => 'meta_campaign_id',
        };

        $query = match ($nivel) {
            MetaAdsNivel::Anuncio => MetaAdsAnuncio::query(),
            MetaAdsNivel::Conjunto => MetaAdsConjunto::query(),
            default => MetaAdsCampanha::query(),
        };

        $objetos = [];

        foreach ($query->where('meta_ads_conta_id', $conta->id)->vigentes()->orderBy('nome')->get() as $o) {
            $objetos[] = [
                'id' => (int) $o->id,
                'meta_id' => (string) $o->getAttribute($metaCol),
                'nome' => (string) $o->nome,
                'effective_status' => $o->effective_status !== null ? (string) $o->effective_status : null,
            ];
        }

        return $objetos;
    }

    /**
     * Investimento agregado (do nível anúncio, somado pela hierarquia) por id
     * local do objeto do nível pedido.
     *
     * @return array<int|string, array{investido: int, impressoes: int, cliques: int, cliques_link: int, alcance: int, leads_meta: int}>
     */
    private function investimentoPorObjeto(
        MetaAdsConta $conta,
        \DateTimeInterface $de,
        \DateTimeInterface $ate,
        MetaAdsNivel $nivel,
    ): array {
        $grupo = match ($nivel) {
            MetaAdsNivel::Anuncio => 'a.id',
            MetaAdsNivel::Conjunto => 'a.meta_ads_conjunto_id',
            default => 'a.meta_ads_campanha_id',
        };

        $linhas = MetaAdsInsightDiario::query()
            ->join('meta_ads_anuncios as a', 'a.meta_ad_id', '=', 'meta_ads_insights_diarios.objeto_id')
            ->where('a.meta_ads_conta_id', $conta->id)
            ->where('meta_ads_insights_diarios.nivel', MetaAdsNivel::Anuncio->value)
            ->whereDate('meta_ads_insights_diarios.referencia', '>=', $de->format('Y-m-d'))
            ->whereDate('meta_ads_insights_diarios.referencia', '<=', $ate->format('Y-m-d'))
            ->groupBy($grupo)
            ->selectRaw("{$grupo} as chave,
                SUM(meta_ads_insights_diarios.investimento_centavos) as investido,
                SUM(meta_ads_insights_diarios.impressoes) as impressoes,
                SUM(meta_ads_insights_diarios.cliques) as cliques,
                SUM(meta_ads_insights_diarios.cliques_link) as cliques_link,
                SUM(meta_ads_insights_diarios.alcance) as alcance,
                SUM(meta_ads_insights_diarios.resultados) as leads_meta")
            ->get();

        $mapa = [];

        foreach ($linhas as $linha) {
            $mapa[$linha->getAttribute('chave')] = [
                'investido' => (int) $linha->getAttribute('investido'),
                'impressoes' => (int) $linha->getAttribute('impressoes'),
                'cliques' => (int) $linha->getAttribute('cliques'),
                'cliques_link' => (int) $linha->getAttribute('cliques_link'),
                'alcance' => (int) $linha->getAttribute('alcance'),
                'leads_meta' => (int) $linha->getAttribute('leads_meta'),
            ];
        }

        return $mapa;
    }

    /**
     * Negociações atribuídas do intervalo, agrupadas pelo id da Meta do nível.
     *
     * @return array<string, list<array{etapa_funil_id: int, valor: float}>>
     */
    private function negociacoesPorChaveMeta(\DateTimeInterface $de, \DateTimeInterface $ate, MetaAdsNivel $nivel): array
    {
        $col = match ($nivel) {
            MetaAdsNivel::Anuncio => 'meta_ad_id',
            MetaAdsNivel::Conjunto => 'meta_adset_id',
            default => 'meta_campaign_id',
        };

        $mapa = [];

        Negociacao::query()
            ->whereNotNull($col)
            ->whereBetween('created_at', [$de, $ate])
            ->get([$col, 'etapa_funil_id', 'valor'])
            ->each(function (Negociacao $n) use (&$mapa, $col): void {
                $mapa[(string) $n->getAttribute($col)][] = [
                    'etapa_funil_id' => (int) $n->etapa_funil_id,
                    'valor' => (float) $n->valor,
                ];
            });

        return $mapa;
    }

    /**
     * Ids de `etapas_funil` que representam "atingiu esta fase ou depois", por
     * funil. `$eventos` = valores do mapa `meta.capi.mapa_etapa_evento`;
     * `$fallback` = trechos usados se o mapa estiver vazio.
     *
     * @param  list<string>  $eventos
     * @param  list<string>  $fallback
     * @return list<int>
     */
    private function etapasAtingidas(array $eventos, array $fallback): array
    {
        $mapa = config('meta.capi.mapa_etapa_evento');
        $mapa = is_array($mapa) ? $mapa : [];

        $trechos = array_keys(array_filter($mapa, static fn ($v): bool => in_array((string) $v, $eventos, true)));
        $trechos = array_map('strval', $trechos !== [] ? $trechos : $fallback);

        $ids = [];

        foreach (EtapaFunil::query()->get(['id', 'funil_id', 'nome', 'ordem'])->groupBy('funil_id') as $etapas) {
            $ordemAlvo = $etapas
                ->filter(fn (EtapaFunil $e): bool => $this->casaTrecho($e->nome, $trechos))
                ->min('ordem');

            if ($ordemAlvo === null) {
                continue;
            }

            foreach ($etapas->where('ordem', '>=', $ordemAlvo) as $e) {
                $ids[] = $e->id;
            }
        }

        return $ids;
    }

    /**
     * @param  list<string>  $trechos
     */
    private function casaTrecho(string $nome, array $trechos): bool
    {
        $alvo = mb_strtolower($nome);

        foreach ($trechos as $trecho) {
            if ($trecho !== '' && str_contains($alvo, mb_strtolower($trecho))) {
                return true;
            }
        }

        return false;
    }
}
