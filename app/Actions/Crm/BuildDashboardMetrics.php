<?php

namespace App\Actions\Crm;

use App\Enums\EtapaFunilResultado;
use App\Http\Resources\NegociacaoResource;
use App\Models\CanalContato;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildDashboardMetrics
{
    /**
     * @return array{
     *     periodo: string,
     *     periodos: list<string>,
     *     kpis: list<array{label: string, value: string, delta: string, deltaColor: string}>,
     *     conversao: array{funil: string, etapas: list<array{etapa: string, pct: int, cor: string, info: string}>},
     *     conversaoFunis: list<array{nome: string, leads: int, fechados: int, pct: int, cor: string, info: string}>,
     *     conversaoNegociacoes: list<array{rotulo: string, qtd: int, pct: int, cor: string, info: string, valorFmt: string}>,
     *     canais: list<array{nome: string, qtd: int, pct: int, cor: string}>,
     *     perdas: list<array{motivo: string, qtd: int, pct: int}>,
     *     equipe: list<array{nome: string, leads: int, fechados: int, conv: string, sla: string, slaCor: string}>,
     *     atendimentosEncerrados: array{
     *         total: int,
     *         taxaEncerrados: int,
     *         totalQualificados: int,
     *         totalDesqualificados: int,
     *         totalSemResposta: int,
     *         motivos: list<array{motivo: string, qtd: int, pct: int, cor: string}>,
     *         continuidade: list<array{nome: string, qtd: int, pct: int, cor: string}>,
     *         qualificacao: list<array{nome: string, qtd: int, pct: int, cor: string}>,
     *         leads: list<array{
     *             id: int,
     *             assunto: string,
     *             contatoNome: string,
     *             contatoTelefone: ?string,
     *             responsavelNome: string,
     *             motivo: string,
     *             continuidade: string,
     *             qualificacao: string,
     *             qualificacaoCorFundo: string,
     *             qualificacaoCorTexto: string,
     *             observacoes: ?string,
     *             data: string
     *         }>
     *     }
     * }
     */
    public function __invoke(string $periodo = '30 dias'): array
    {
        $periodo = $this->normalizarPeriodo($periodo);
        $dias = $this->diasDoPeriodo($periodo);
        $inicio = now()->subDays($dias)->startOfDay();
        $inicioAnterior = now()->subDays($dias * 2)->startOfDay();
        $fimAnterior = (clone $inicio)->subSecond();

        $funis = Funil::query()
            ->with(['etapas' => fn ($query) => $query->orderBy('ordem')])
            ->orderBy('ordem')
            ->get();

        $ultimaEtapaIds = $funis
            ->flatMap(fn (Funil $funil) => $funil->etapas->filter(
                fn ($etapa): bool => $etapa->isGanho(),
            ))
            ->map(fn ($etapa) => $etapa->id)
            ->values()
            ->all();

        $leads = Negociacao::query()->where('created_at', '>=', $inicio)->count();
        $leadsAnterior = Negociacao::query()
            ->whereBetween('created_at', [$inicioAnterior, $fimAnterior])
            ->count();

        $fechados = Negociacao::query()
            ->where('created_at', '>=', $inicio)
            ->when(
                $ultimaEtapaIds !== [],
                fn ($query) => $query->whereIn('etapa_funil_id', $ultimaEtapaIds),
                fn ($query) => $query->whereRaw('0 = 1'),
            )
            ->count();

        $honorarios = (float) Negociacao::query()
            ->where('created_at', '>=', $inicio)
            ->when(
                $ultimaEtapaIds !== [],
                fn ($query) => $query->whereIn('etapa_funil_id', $ultimaEtapaIds),
                fn ($query) => $query->whereRaw('0 = 1'),
            )
            ->sum('valor');

        $cicloMedio = $this->cicloMedioDias($inicio, $ultimaEtapaIds);
        $taxaConversao = $leads > 0 ? (int) round(($fechados / $leads) * 100) : 0;

        $funilPrincipal = $funis->first();
        $analiseEncerrados = $this->analiseAtendimentosEncerrados();
        $perfilLeadsMeta = $this->perfilLeadsMeta();
        $perdas = array_slice(
            array_map(fn (array $m) => [
                'motivo' => $m['motivo'],
                'qtd' => $m['qtd'],
                'pct' => $m['pct'],
            ], $analiseEncerrados['motivos']),
            0,
            5
        );

        return [
            'periodo' => $periodo,
            'periodos' => ['7 dias', '30 dias', 'trimestre'],
            'kpis' => [
                [
                    'label' => 'Leads no período',
                    'value' => (string) $leads,
                    'delta' => $this->deltaPercentual($leads, $leadsAnterior, 'vs. período anterior'),
                    'deltaColor' => $leads >= $leadsAnterior ? '#14574F' : '#9B3B2F',
                ],
                [
                    'label' => 'Taxa de conversão',
                    'value' => $taxaConversao.'%',
                    'delta' => $fechados.' contrato'.($fechados === 1 ? '' : 's').' no período',
                    'deltaColor' => '#77808E',
                ],
                [
                    'label' => 'Ciclo médio',
                    'value' => $cicloMedio === null ? '—' : $cicloMedio.'d',
                    'delta' => $cicloMedio === null
                        ? 'Sem fechamentos no período'
                        : 'criação → etapa final',
                    'deltaColor' => '#77808E',
                ],
                [
                    'label' => 'Honorários contratados',
                    'value' => NegociacaoResource::moedaCompacta($honorarios),
                    'delta' => $fechados.' contrato'.($fechados === 1 ? '' : 's').' na etapa final',
                    'deltaColor' => '#77808E',
                ],
            ],
            'conversao' => [
                'funil' => $funilPrincipal === null
                    ? 'Sem funil'
                    : ($funilPrincipal->slug === 'b2b' ? 'B2B consultivo' : $funilPrincipal->nome),
                'etapas' => $this->conversaoPorEtapa($funilPrincipal, $inicio),
            ],
            'conversaoFunis' => $this->conversaoPorFunil($funis, $inicio),
            'conversaoNegociacoes' => $this->conversaoPorNegociacao($inicio, $ultimaEtapaIds),
            'canais' => $this->origemCanais($inicio),
            'perdas' => $perdas,
            'equipe' => $this->produtividadeEquipe($inicio, $ultimaEtapaIds),
            'atendimentosEncerrados' => $analiseEncerrados,
            'perfilLeadsMeta' => $perfilLeadsMeta,
        ];
    }

    private function normalizarPeriodo(string $periodo): string
    {
        return match ($periodo) {
            '7 dias', 'trimestre' => $periodo,
            default => '30 dias',
        };
    }

    private function diasDoPeriodo(string $periodo): int
    {
        return match ($periodo) {
            '7 dias' => 7,
            'trimestre' => 90,
            default => 30,
        };
    }

    /**
     * @param  list<int>  $ultimaEtapaIds
     */
    private function cicloMedioDias(CarbonInterface $inicio, array $ultimaEtapaIds): ?int
    {
        if ($ultimaEtapaIds === []) {
            return null;
        }

        $fechadas = Negociacao::query()
            ->where('created_at', '>=', $inicio)
            ->whereIn('etapa_funil_id', $ultimaEtapaIds)
            ->get(['created_at', 'etapa_desde']);

        if ($fechadas->isEmpty()) {
            return null;
        }

        $media = $fechadas->avg(
            fn (Negociacao $negociacao): float => max(
                0,
                (float) $negociacao->created_at->copy()->startOfDay()->diffInDays(
                    $negociacao->etapa_desde->copy()->startOfDay(),
                ),
            ),
        );

        return (int) round((float) $media);
    }

    /**
     * @return list<array{etapa: string, pct: int, cor: string, info: string}>
     */
    private function conversaoPorEtapa(?Funil $funil, CarbonInterface $inicio): array
    {
        if ($funil === null || $funil->etapas->isEmpty()) {
            return [];
        }

        $contagens = Negociacao::query()
            ->where('funil_id', $funil->id)
            ->where('created_at', '>=', $inicio)
            ->selectRaw('etapa_funil_id, count(*) as total')
            ->groupBy('etapa_funil_id')
            ->pluck('total', 'etapa_funil_id');

        $totalFunil = (int) $contagens->sum();
        $base = max($totalFunil, 1);
        $cores = ['#3C4450', '#4F5A69', '#6B6247', '#8C6F3F', '#14574F', '#3F5E8C'];

        return $funil->etapas->values()->map(function ($etapa, int $index) use ($contagens, $base, $cores): array {
            $abertos = (int) ($contagens[$etapa->id] ?? 0);
            $pct = (int) round(($abertos / $base) * 100);

            return [
                'etapa' => $etapa->nome,
                'pct' => $pct,
                'cor' => $cores[$index % count($cores)],
                'info' => $pct.'% · '.$abertos.' aberto'.($abertos === 1 ? '' : 's'),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Funil>  $funis
     * @return list<array{nome: string, leads: int, fechados: int, pct: int, cor: string, info: string}>
     */
    private function conversaoPorFunil(Collection $funis, CarbonInterface $inicio): array
    {
        if ($funis->isEmpty()) {
            return [];
        }

        $cores = ['#3F5E8C', '#14574F', '#8C6F3F', '#8C4A6B', '#7A6E3F'];

        return $funis->values()->map(function (Funil $funil, int $index) use ($inicio, $cores): array {
            $etapaGanhoId = $funil->etapas->first(fn ($etapa): bool => $etapa->isGanho())?->id;
            $leads = Negociacao::query()
                ->where('funil_id', $funil->id)
                ->where('created_at', '>=', $inicio)
                ->count();
            $fechados = $etapaGanhoId === null
                ? 0
                : Negociacao::query()
                    ->where('funil_id', $funil->id)
                    ->where('etapa_funil_id', $etapaGanhoId)
                    ->where('created_at', '>=', $inicio)
                    ->count();
            $pct = $leads > 0 ? (int) round(($fechados / $leads) * 100) : 0;
            $nome = $funil->slug === 'b2b' ? 'B2B consultivo' : $funil->nome;

            return [
                'nome' => $nome,
                'leads' => $leads,
                'fechados' => $fechados,
                'pct' => $pct,
                'cor' => $cores[$index % count($cores)],
                'info' => $pct.'% · '.$fechados.'/'.$leads.' fechados',
            ];
        })->all();
    }

    /**
     * @param  list<int>  $ultimaEtapaIds
     * @return list<array{rotulo: string, qtd: int, pct: int, cor: string, info: string, valorFmt: string}>
     */
    private function conversaoPorNegociacao(CarbonInterface $inicio, array $ultimaEtapaIds): array
    {
        $negociacoes = Negociacao::query()
            ->where('created_at', '>=', $inicio)
            ->get(['id', 'etapa_funil_id', 'valor']);

        $total = $negociacoes->count();

        if ($total === 0) {
            return [];
        }

        $fechadas = $negociacoes->filter(
            fn (Negociacao $item): bool => in_array($item->etapa_funil_id, $ultimaEtapaIds, true),
        );
        $emAndamento = $negociacoes->reject(
            fn (Negociacao $item): bool => in_array($item->etapa_funil_id, $ultimaEtapaIds, true),
        );

        $fechadasQtd = $fechadas->count();
        $andamentoQtd = $emAndamento->count();
        $fechadasPct = (int) round(($fechadasQtd / $total) * 100);
        $andamentoPct = (int) round(($andamentoQtd / $total) * 100);

        return [
            [
                'rotulo' => 'Em andamento',
                'qtd' => $andamentoQtd,
                'pct' => $andamentoPct,
                'cor' => '#3F5E8C',
                'info' => $andamentoPct.'% · '.$andamentoQtd.' negociaç'.($andamentoQtd === 1 ? 'ão' : 'ões'),
                'valorFmt' => NegociacaoResource::moedaCompacta((float) $emAndamento->sum('valor')),
            ],
            [
                'rotulo' => 'Fechadas',
                'qtd' => $fechadasQtd,
                'pct' => $fechadasPct,
                'cor' => '#14574F',
                'info' => $fechadasPct.'% · '.$fechadasQtd.' negociaç'.($fechadasQtd === 1 ? 'ão' : 'ões'),
                'valorFmt' => NegociacaoResource::moedaCompacta((float) $fechadas->sum('valor')),
            ],
        ];
    }

    /**
     * @return list<array{nome: string, qtd: int, pct: int, cor: string}>
     */
    private function origemCanais(CarbonInterface $inicio): array
    {
        $contagens = Negociacao::query()
            ->where('created_at', '>=', $inicio)
            ->selectRaw('canal_contato_id, count(*) as total')
            ->groupBy('canal_contato_id')
            ->pluck('total', 'canal_contato_id');

        if ($contagens->isEmpty()) {
            return [];
        }

        $canais = CanalContato::query()
            ->whereIn('id', $contagens->keys())
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get(['id', 'nome', 'cor']);

        $max = max(1, (int) $contagens->max());

        return $canais
            ->map(function (CanalContato $canal) use ($contagens, $max): array {
                $qtd = (int) ($contagens[$canal->id] ?? 0);

                return [
                    'nome' => $canal->nome,
                    'qtd' => $qtd,
                    'pct' => (int) round(($qtd / $max) * 100),
                    'cor' => $canal->cor,
                ];
            })
            ->sortByDesc('qtd')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ultimaEtapaIds
     * @return list<array{nome: string, leads: int, fechados: int, conv: string, sla: string, slaCor: string}>
     */
    private function produtividadeEquipe(CarbonInterface $inicio, array $ultimaEtapaIds): array
    {
        $negociacoes = Negociacao::query()
            ->where('created_at', '>=', $inicio)
            ->get(['id', 'responsavel_user_id', 'etapa_funil_id', 'created_at']);

        if ($negociacoes->isEmpty()) {
            return [];
        }

        $primeiroContato = HistoricoNegociacao::query()
            ->whereIn('negociacao_id', $negociacoes->pluck('id'))
            ->whereIn('tipo', ['wa', 'call', 'mail', 'task'])
            ->orderBy('ocorrido_em')
            ->get(['negociacao_id', 'ocorrido_em'])
            ->unique('negociacao_id')
            ->keyBy('negociacao_id');

        $porResponsavel = $negociacoes->groupBy('responsavel_user_id');
        $usuarios = User::query()
            ->whereIn('id', $porResponsavel->keys()->filter())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

        return $porResponsavel
            ->map(function (Collection $itens, mixed $responsavelId) use ($usuarios, $ultimaEtapaIds, $primeiroContato): ?array {
                $usuario = $usuarios->get($responsavelId);

                if ($usuario === null) {
                    return null;
                }

                $leads = $itens->count();
                $fechados = $itens
                    ->filter(fn (Negociacao $item): bool => in_array($item->etapa_funil_id, $ultimaEtapaIds, true))
                    ->count();
                $conv = $leads > 0 ? (int) round(($fechados / $leads) * 100) : 0;

                $horas = $itens
                    ->map(function (Negociacao $item) use ($primeiroContato): ?float {
                        $primeiro = $primeiroContato->get($item->id);

                        if ($primeiro === null) {
                            return null;
                        }

                        return max(0, $item->created_at->floatDiffInHours($primeiro->ocorrido_em));
                    })
                    ->filter(fn (?float $valor): bool => $valor !== null);

                $slaMedio = $horas->isEmpty() ? null : (float) $horas->avg();
                $slaCor = $slaMedio === null
                    ? '#77808E'
                    : ($slaMedio <= 4 ? '#14574F' : ($slaMedio <= 8 ? '#8C6F3F' : '#9B3B2F'));

                return [
                    'nome' => $usuario->name,
                    'leads' => $leads,
                    'fechados' => $fechados,
                    'conv' => $conv.'%',
                    'sla' => $slaMedio === null ? '—' : $this->formatarHoras($slaMedio),
                    'slaCor' => $slaCor,
                ];
            })
            ->filter()
            ->sortByDesc('leads')
            ->values()
            ->all();
    }

    private function deltaPercentual(int $atual, int $anterior, string $sufixo): string
    {
        if ($anterior === 0) {
            return $atual === 0 ? 'Sem movimento no período' : 'Novo no período';
        }

        $pct = (int) round((($atual - $anterior) / $anterior) * 100);
        $sinal = $pct > 0 ? '+' : '';

        return $sinal.$pct.'% '.$sufixo;
    }

    private function formatarHoras(float $horas): string
    {
        $totalMinutos = (int) round($horas * 60);
        $h = intdiv($totalMinutos, 60);
        $m = $totalMinutos % 60;

        return $h.'h'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{
     *     total: int,
     *     taxaEncerrados: int,
     *     totalQualificados: int,
     *     totalDesqualificados: int,
     *     totalSemResposta: int,
     *     motivos: list<array{motivo: string, qtd: int, pct: int, cor: string}>,
     *     continuidade: list<array{nome: string, qtd: int, pct: int, cor: string}>,
     *     qualificacao: list<array{nome: string, qtd: int, pct: int, cor: string}>,
     *     leads: list<array{
     *         id: int,
     *         assunto: string,
     *         contatoNome: string,
     *         contatoTelefone: ?string,
     *         responsavelNome: string,
     *         motivo: string,
     *         continuidade: string,
     *         qualificacao: string,
     *         qualificacaoCorFundo: string,
     *         qualificacaoCorTexto: string,
     *         observacoes: ?string,
     *         data: string
     *     }>
     * }
     */
    private function analiseAtendimentosEncerrados(): array
    {
        $etapasPerdidasIds = EtapaFunil::query()
            ->where(function ($query) {
                $query->where('resultado', EtapaFunilResultado::Perdido)
                    ->orWhereRaw('LOWER(nome) LIKE ?', ['%encerrad%']);
            })
            ->pluck('id')
            ->all();

        if ($etapasPerdidasIds === []) {
            return [
                'total' => 0,
                'taxaEncerrados' => 0,
                'totalQualificados' => 0,
                'totalDesqualificados' => 0,
                'totalSemResposta' => 0,
                'motivos' => [],
                'continuidade' => [],
                'qualificacao' => [],
                'leads' => [],
            ];
        }

        $encerradas = Negociacao::query()
            ->whereIn('etapa_funil_id', $etapasPerdidasIds)
            ->with([
                'contato:id,nome,telefone',
                'responsavel:id,name',
                'statusQualificacao:id,nome,cor_fundo,cor_texto',
            ])
            ->orderByDesc('etapa_desde')
            ->orderByDesc('id')
            ->get();

        $total = $encerradas->count();
        $totalNegociacoes = Negociacao::query()->count();
        $taxaEncerrados = $totalNegociacoes > 0 ? (int) round(($total / $totalNegociacoes) * 100) : 0;

        $coresMotivos = ['#9B3B2F', '#8C6F3F', '#31496E', '#4F5A69', '#6B5330', '#41503A', '#7A6E3F', '#5A6578', '#78350F', '#831843'];

        $motivos = $encerradas
            ->groupBy(fn (Negociacao $n) => $n->motivo_desqualificacao ?: 'Não informado')
            ->map(function (Collection $itens, string $motivo) use ($total): array {
                $qtd = $itens->count();

                return [
                    'motivo' => $motivo,
                    'qtd' => $qtd,
                    'pct' => $total > 0 ? (int) round(($qtd / $total) * 100) : 0,
                    'cor' => '#9B3B2F',
                ];
            })
            ->sortByDesc('qtd')
            ->values()
            ->map(function (array $item, int $index) use ($coresMotivos): array {
                $item['cor'] = $coresMotivos[$index % count($coresMotivos)];

                return $item;
            })
            ->all();

        $coresContinuidade = ['#31496E', '#9B3B2F', '#8C6F3F', '#41503A', '#4F5A69'];

        $continuidade = $encerradas
            ->groupBy(fn (Negociacao $n) => $n->continuidade_atendimento ?: 'Não informada')
            ->map(function (Collection $itens, string $nome) use ($total): array {
                $qtd = $itens->count();

                return [
                    'nome' => $nome,
                    'qtd' => $qtd,
                    'pct' => $total > 0 ? (int) round(($qtd / $total) * 100) : 0,
                    'cor' => '#31496E',
                ];
            })
            ->sortByDesc('qtd')
            ->values()
            ->map(function (array $item, int $index) use ($coresContinuidade): array {
                $item['cor'] = $coresContinuidade[$index % count($coresContinuidade)];

                return $item;
            })
            ->all();

        $qualificacao = $encerradas
            ->groupBy(fn (Negociacao $n) => $n->statusQualificacao?->nome ?: 'Sem status')
            ->map(function (Collection $itens, string $nome) use ($total): array {
                $qtd = $itens->count();
                $cor = match (mb_strtolower($nome)) {
                    'qualificado' => '#14574F',
                    'desqualificado' => '#9B3B2F',
                    default => '#77808E',
                };

                return [
                    'nome' => $nome,
                    'qtd' => $qtd,
                    'pct' => $total > 0 ? (int) round(($qtd / $total) * 100) : 0,
                    'cor' => $cor,
                ];
            })
            ->sortByDesc('qtd')
            ->values()
            ->all();

        $totalQualificados = $encerradas
            ->filter(fn (Negociacao $n) => mb_strtolower((string) $n->statusQualificacao?->nome) === 'qualificado')
            ->count();

        $totalDesqualificados = $encerradas
            ->filter(fn (Negociacao $n) => mb_strtolower((string) $n->statusQualificacao?->nome) === 'desqualificado')
            ->count();

        $totalSemResposta = $encerradas
            ->filter(fn (Negociacao $n) => str_contains(mb_strtolower((string) $n->motivo_desqualificacao), 'não respondeu')
                || str_contains(mb_strtolower((string) $n->motivo_desqualificacao), 'follow-up'))
            ->count();

        $leads = $encerradas->map(function (Negociacao $n): array {
            return [
                'id' => $n->id,
                'assunto' => $n->assunto,
                'contatoNome' => $n->contato?->nome ?? 'Contato não informado',
                'contatoTelefone' => $n->contato?->telefone,
                'responsavelNome' => $n->responsavel?->name ?? 'Não atribuído',
                'motivo' => $n->motivo_desqualificacao ?: 'Não informado',
                'continuidade' => $n->continuidade_atendimento ?: 'Não informada',
                'qualificacao' => $n->statusQualificacao?->nome ?: 'Sem status',
                'qualificacaoCorFundo' => $n->statusQualificacao?->cor_fundo ?? '#EAE6DF',
                'qualificacaoCorTexto' => $n->statusQualificacao?->cor_texto ?? '#333A45',
                'observacoes' => $n->observacoes_complementares,
                'data' => $n->etapa_desde?->format('d/m/Y') ?? $n->created_at->format('d/m/Y'),
            ];
        })->values()->all();

        return [
            'total' => $total,
            'taxaEncerrados' => $taxaEncerrados,
            'totalQualificados' => $totalQualificados,
            'totalDesqualificados' => $totalDesqualificados,
            'totalSemResposta' => $totalSemResposta,
            'motivos' => $motivos,
            'continuidade' => $continuidade,
            'qualificacao' => $qualificacao,
            'leads' => $leads,
        ];
    }

    /**
     * Gera inteligência de audiência, geolocalização, perfil de dores e recomendações
     * táticas para criação de campanhas de tráfego pago nas plataformas da Meta (Facebook/Instagram Ads).
     *
     * @return array{
     *     total: int,
     *     totalQualificados: int,
     *     taxaQualificacao: int,
     *     totalComTelefone: int,
     *     taxaComTelefone: int,
     *     topDdds: list<array{ddd: string, regiao: string, qtd: int, pct: int, cor: string}>,
     *     clustersConcurso: list<array{nome: string, total: int, qualificados: int, taxaQualificacao: int, fasePrincipal: string, cor: string}>,
     *     fasesDemanda: list<array{fase: string, qtd: int, pct: int, cor: string}>,
     *     diasPico: list<array{dia: string, qtd: int, pct: int}>,
     *     recomendacoesMeta: array{
     *         objetivo: string,
     *         canal: string,
     *         faixaEtaria: string,
     *         publicosSugeridos: list<array{nome: string, tipo: string, descricao: string}>,
     *         geotargeting: list<array{conjunto: string, localizacao: string, ddds: string, justificativa: string}>,
     *         interesses: list<string>,
     *         ganchosCriativos: list<array{titulo: string, concursoOuFase: string, gancho: string, dor: string, cta: string}>
     *     }
     * }
     */
    private function perfilLeadsMeta(): array
    {
        $negociacoes = Negociacao::query()
            ->with([
                'contato:id,telefone,cidade,uf_id',
                'statusQualificacao:id,nome',
            ])
            ->get();

        $total = $negociacoes->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'totalQualificados' => 0,
                'taxaQualificacao' => 0,
                'totalComTelefone' => 0,
                'taxaComTelefone' => 0,
                'topDdds' => [],
                'clustersConcurso' => [],
                'fasesDemanda' => [],
                'diasPico' => [],
                'recomendacoesMeta' => [
                    'objetivo' => 'Geração de Leads (Cadastro / WhatsApp)',
                    'canal' => 'WhatsApp Business integrado ao CRM',
                    'faixaEtaria' => '21 a 45 anos',
                    'publicosSugeridos' => [],
                    'geotargeting' => [],
                    'interesses' => [],
                    'ganchosCriativos' => [],
                ],
            ];
        }

        $qualificados = $negociacoes
            ->filter(fn (Negociacao $n) => mb_strtolower((string) $n->statusQualificacao?->nome) === 'qualificado')
            ->count();
        $taxaQualificacao = (int) round(($qualificados / $total) * 100);

        $comTelefone = $negociacoes
            ->filter(fn (Negociacao $n) => ! empty($n->contato?->telefone))
            ->count();
        $taxaComTelefone = (int) round(($comTelefone / $total) * 100);

        // 1. Extração e mapeamento de DDDs
        $regioesDdd = [
            '61' => 'Distrito Federal e Entorno (Brasília/DF)',
            '51' => 'Rio Grande do Sul (Porto Alegre e Metropolitana)',
            '53' => 'Rio Grande do Sul (Pelotas e Região Sul)',
            '55' => 'Rio Grande do Sul (Santa Maria e Oeste)',
            '54' => 'Rio Grande do Sul (Caxias do Sul e Serra)',
            '62' => 'Goiás (Goiânia e Região Central)',
            '31' => 'Minas Gerais (Belo Horizonte e Central)',
            '34' => 'Minas Gerais (Triângulo Mineiro)',
            '35' => 'Minas Gerais (Sul de Minas)',
            '21' => 'Rio de Janeiro (Capital e Metropolitana)',
            '99' => 'Maranhão (Imperatriz e Região)',
            '71' => 'Bahia (Salvador e Metropolitana)',
            '81' => 'Pernambuco (Recife e Metropolitana)',
        ];

        $coresDdd = ['#31496E', '#14574F', '#8C6F3F', '#41503A', '#9B3B2F', '#6B5330', '#4F5A69'];

        $dddsContagem = [];
        foreach ($negociacoes as $n) {
            $raw = preg_replace('/\D/', '', (string) $n->contato?->telefone);
            if (strlen((string) $raw) >= 10) {
                $ddd = substr((string) $raw, 0, 2);
                $dddsContagem[$ddd] = ($dddsContagem[$ddd] ?? 0) + 1;
            }
        }
        arsort($dddsContagem);

        $topDdds = [];
        $idxDdd = 0;
        foreach (array_slice($dddsContagem, 0, 7, true) as $ddd => $qtd) {
            $dddStr = (string) $ddd;
            $topDdds[] = [
                'ddd' => $dddStr,
                'regiao' => $regioesDdd[$dddStr] ?? 'Demais Regiões e Estados',
                'qtd' => $qtd,
                'pct' => (int) round(($qtd / $total) * 100),
                'cor' => $coresDdd[$idxDdd % count($coresDdd)],
            ];
            $idxDdd++;
        }

        // 2. Clusters por Concurso / Órgão
        $coresConcursos = ['#31496E', '#14574F', '#8C6F3F', '#9B3B2F', '#6B5330', '#41503A'];
        $concursosMap = [];

        foreach ($negociacoes as $n) {
            $assunto = mb_strtoupper((string) $n->assunto);
            $concurso = 'Outros Concursos';
            if (str_contains($assunto, 'PMDF')) {
                $concurso = 'PMDF (Polícia Militar DF)';
            } elseif (str_contains($assunto, 'PCRS')) {
                $concurso = 'PCRS (Polícia Civil RS)';
            } elseif (str_contains($assunto, 'CNU')) {
                $concurso = 'CNU (Concurso Nacional Unificado)';
            } elseif (str_contains($assunto, 'PPMG')) {
                $concurso = 'PPMG (Polícia Penal MG)';
            } elseif (str_contains($assunto, 'CÂMARA') || str_contains($assunto, 'CAMARA')) {
                $concurso = 'Câmara dos Deputados';
            }

            $isQualificado = mb_strtolower((string) $n->statusQualificacao?->nome) === 'qualificado';

            if (! isset($concursosMap[$concurso])) {
                $concursosMap[$concurso] = [
                    'nome' => $concurso,
                    'total' => 0,
                    'qualificados' => 0,
                    'fases' => [],
                ];
            }
            $concursosMap[$concurso]['total']++;
            if ($isQualificado) {
                $concursosMap[$concurso]['qualificados']++;
            }

            $fase = 'Geral';
            if (str_contains($assunto, 'QUEST')) {
                $fase = 'Anulação de Questões';
            } elseif (str_contains($assunto, 'TAF')) {
                $fase = 'Teste Físico (TAF)';
            } elseif (str_contains($assunto, 'MÉDIC') || str_contains($assunto, 'MEDIC')) {
                $fase = 'Avaliação Médica';
            } elseif (str_contains($assunto, 'DISCURSIV') || str_contains($assunto, 'REDAC')) {
                $fase = 'Discursiva / Redação';
            }
            $concursosMap[$concurso]['fases'][$fase] = ($concursosMap[$concurso]['fases'][$fase] ?? 0) + 1;
        }

        uasort($concursosMap, fn ($a, $b) => $b['total'] <=> $a['total']);

        $clustersConcurso = [];
        $idxConcurso = 0;
        foreach ($concursosMap as $c) {
            arsort($c['fases']);
            $fasePrincipal = array_key_first($c['fases']) ?? 'Geral';
            $clustersConcurso[] = [
                'nome' => $c['nome'],
                'total' => $c['total'],
                'qualificados' => $c['qualificados'],
                'taxaQualificacao' => (int) round(($c['qualificados'] / $c['total']) * 100),
                'fasePrincipal' => $fasePrincipal,
                'cor' => $coresConcursos[$idxConcurso % count($coresConcursos)],
            ];
            $idxConcurso++;
        }

        // 3. Fases da Demanda
        $coresFases = ['#31496E', '#8C6F3F', '#9B3B2F', '#14574F', '#4F5A69'];
        $fasesMap = [];
        foreach ($negociacoes as $n) {
            $assunto = mb_strtoupper((string) $n->assunto);
            $fase = 'Outras Fases do Concurso';
            if (str_contains($assunto, 'QUEST') || str_contains($assunto, 'GABARITO')) {
                $fase = 'Anulação de Questões (Prova Objetiva)';
            } elseif (str_contains($assunto, 'TAF') || str_contains($assunto, 'FISIC')) {
                $fase = 'Teste de Aptidão Física (TAF)';
            } elseif (str_contains($assunto, 'MÉDIC') || str_contains($assunto, 'MEDIC') || str_contains($assunto, 'SAUDE')) {
                $fase = 'Avaliação Médica / Psicotécnico';
            } elseif (str_contains($assunto, 'DISCURSIV') || str_contains($assunto, 'REDAC')) {
                $fase = 'Prova Discursiva / Redação';
            }
            $fasesMap[$fase] = ($fasesMap[$fase] ?? 0) + 1;
        }
        arsort($fasesMap);

        $fasesDemanda = [];
        $idxFase = 0;
        foreach ($fasesMap as $fase => $qtd) {
            $fasesDemanda[] = [
                'fase' => $fase,
                'qtd' => $qtd,
                'pct' => (int) round(($qtd / $total) * 100),
                'cor' => $coresFases[$idxFase % count($coresFases)],
            ];
            $idxFase++;
        }

        // 4. Dias de Pico
        $diasMap = [
            'segunda-feira' => 'Segunda-feira',
            'terça-feira' => 'Terça-feira',
            'quarta-feira' => 'Quarta-feira',
            'quinta-feira' => 'Quinta-feira',
            'sexta-feira' => 'Sexta-feira',
            'sábado' => 'Sábado',
            'domingo' => 'Domingo',
        ];
        $diasContagem = [];
        foreach ($negociacoes as $n) {
            $diaSlug = $n->created_at->locale('pt_BR')->translatedFormat('l');
            $nomeDia = $diasMap[$diaSlug] ?? ucfirst($diaSlug);
            $diasContagem[$nomeDia] = ($diasContagem[$nomeDia] ?? 0) + 1;
        }
        $diasPico = [];
        foreach ($diasContagem as $dia => $qtd) {
            $diasPico[] = [
                'dia' => $dia,
                'qtd' => $qtd,
                'pct' => (int) round(($qtd / $total) * 100),
            ];
        }
        usort($diasPico, fn ($a, $b) => $b['qtd'] <=> $a['qtd']);

        // 5. Recomendações Estratégicas para Meta Ads
        $recomendacoesMeta = [
            'objetivo' => 'Geração de Cadastros (Leads) via Mensagem no WhatsApp & Formulário Instantâneo com CAPI',
            'canal' => 'WhatsApp Business API integrado ao CRM com disparos de eventos Lead e Schedule via Meta CAPI',
            'faixaEtaria' => '21 a 40 anos (foco 21-35 para Carreiras Policiais e 24-45 para CNU/Câmara)',
            'publicosSugeridos' => [
                [
                    'nome' => 'Lookalike 1% e 2% (Semelhante de Leads Qualificados)',
                    'tipo' => 'Público Semelhante',
                    'descricao' => 'Criado a partir da base de '.$qualificados.' leads qualificados do CRM (telefones higienizados com DDI 55 e SHA-256 via Meta Conversions API).',
                ],
                [
                    'nome' => 'Interesses Específicos em Concursos & Carreiras',
                    'tipo' => 'Segmentação por Interesses',
                    'descricao' => 'Concursos Públicos, Cebraspe, FGV, Carreiras Policiais, Direito Administrativo e Polícia Militar.',
                ],
                [
                    'nome' => 'Remarketing de Engajamento 30d',
                    'tipo' => 'Público Personalizado',
                    'descricao' => 'Usuários que interagiram com as páginas do escritório no Instagram/Facebook ou visitaram a landing page nos últimos 30 dias.',
                ],
            ],
            'geotargeting' => [
                [
                    'conjunto' => 'AdSet 1 · PMDF & Câmara (Distrito Federal e Entorno)',
                    'localizacao' => 'Distrito Federal + raio de 80km (Luziânia, Valparaíso, Águas Lindas/GO)',
                    'ddds' => 'DDDs 61 e 62 (34% da base atual)',
                    'justificativa' => 'Principal polo do escritório: 64 leads da PMDF e 11 da Câmara, com 56% de taxa de qualificação.',
                ],
                [
                    'conjunto' => 'AdSet 2 · PCRS (Rio Grande do Sul)',
                    'localizacao' => 'Estado do Rio Grande do Sul (Porto Alegre, Pelotas, Santa Maria, Caxias do Sul)',
                    'ddds' => 'DDDs 51, 53, 54 e 55 (22% da base atual)',
                    'justificativa' => 'Segundo maior polo regional: 29 leads com taxa recorde de qualificação de 59%, fortemente concentrado em TAF.',
                ],
                [
                    'conjunto' => 'AdSet 3 · CNU (Nacional / Principais Capitais)',
                    'localizacao' => 'Brasil (Capitais e polos com mais de 250 mil habitantes)',
                    'ddds' => 'Brasil Amplo',
                    'justificativa' => 'Concurso Unificado com demanda pulverizada em recursos e anulação de questões objetivas.',
                ],
                [
                    'conjunto' => 'AdSet 4 · PPMG (Minas Gerais)',
                    'localizacao' => 'Estado de Minas Gerais (Belo Horizonte, Triângulo Mineiro, Juiz de Fora)',
                    'ddds' => 'DDDs 31, 34, 35 e 38 (10% da base atual)',
                    'justificativa' => 'Demanda focada em fase de avaliação médica e laudos complementares.',
                ],
            ],
            'interesses' => [
                'Concurso público',
                'Direito administrativo',
                'Cebraspe / Cespe',
                'Fundação Getulio Vargas (FGV)',
                'Polícia Militar',
                'Polícia Civil',
                'Carreiras Policiais',
                'Estratégia Concursos',
                'Gran Cursos Online',
            ],
            'ganchosCriativos' => [
                [
                    'titulo' => 'Anulação de Questões (PMDF / CNU)',
                    'concursoOuFase' => 'Prova Objetiva',
                    'gancho' => '“Sua nota na PMDF ou no CNU ficou a poucos pontos do corte? Erros materiais e desvios das diretrizes do edital podem ser questionados formalmente.”',
                    'dor' => 'Candidato reprovado por 1 ou 2 questões com evidente duplicidade de resposta ou tema não previsto no edital.',
                    'cta' => '“Converse com a equipe jurídica e solicite uma avaliação da viabilidade de recurso das questões.”',
                ],
                [
                    'titulo' => 'Inaptidão no TAF (PCRS / Câmara)',
                    'concursoOuFase' => 'Teste Físico (TAF)',
                    'gancho' => '“Foi considerado inapto no teste físico da PCRS por discordância de postura, cronometragem ou pista? O prazo legal para impugnação é imediato.”',
                    'dor' => 'Falta de filmagem individual ou critérios subjetivos do avaliador na contagem de flexões/corrida.',
                    'cta' => '“Consulte um advogado especializado para analisar o vídeo e as fichas de avaliação do teste.”',
                ],
                [
                    'titulo' => 'Avaliação Médica / Psicotécnico (PPMG)',
                    'concursoOuFase' => 'Exames Médicos & Psico',
                    'gancho' => '“Considerado inapto na fase médica da Polícia Penal? A jurisprudência veda exclusões sumárias por exames complementares perfeitamente sanáveis.”',
                    'dor' => 'Inaptidão por laudos médicos complementares não aceitos ou prazos insuficientes dados pela banca.',
                    'cta' => '“Entenda como a jurisprudência protege o direito do candidato com nossa equipe.”',
                ],
                [
                    'titulo' => 'Espelho e Nota da Prova Discursiva',
                    'concursoOuFase' => 'Fase Discursiva',
                    'gancho' => '“Sua nota na redação veio sem justificativa clara dos critérios de correção? O Supremo Tribunal Federal exige motivação explícita das bancas.”',
                    'dor' => 'Atribuição arbitrária de nota na prova discursiva sem espelho analítico fundamentado.',
                    'cta' => '“Fale conosco e verifique se cabe recurso para reavaliação da nota atribuída.”',
                ],
            ],
        ];

        return [
            'total' => $total,
            'totalQualificados' => $qualificados,
            'taxaQualificacao' => $taxaQualificacao,
            'totalComTelefone' => $comTelefone,
            'taxaComTelefone' => $taxaComTelefone,
            'topDdds' => $topDdds,
            'clustersConcurso' => $clustersConcurso,
            'fasesDemanda' => $fasesDemanda,
            'diasPico' => $diasPico,
            'recomendacoesMeta' => $recomendacoesMeta,
        ];
    }
}
