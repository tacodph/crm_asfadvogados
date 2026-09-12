<?php

namespace App\Http\Resources;

use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Support\Meta\ElegibilidadeEventoMeta;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @mixin Negociacao
 */
class NegociacaoResource extends JsonResource
{
    /**
     * @return array{id: int, titulo: string, etapa: string, responsavel: string, valorFmt: string}
     */
    public static function resumo(Negociacao $negociacao): array
    {
        return [
            'id' => $negociacao->id,
            'titulo' => $negociacao->assunto,
            'etapa' => $negociacao->etapaFunil->nome,
            'responsavel' => $negociacao->responsavel->name,
            'valorFmt' => self::moeda((float) $negociacao->valor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dias = (int) $this->etapa_desde->startOfDay()->diffInDays(now()->startOfDay());
        $atrasado = $dias >= 7;
        $atencao = $dias >= 4 && $dias < 7;
        $tarefaAtrasada = $this->proxima_tarefa_em !== null && $this->proxima_tarefa_em->isBefore(now()->startOfDay());
        $ganho = $this->etapaFunil->isGanho();
        $perdido = $this->etapaFunil->isPerdido();
        $empresaNome = $this->empresa?->nome;
        $contatoNome = $this->contato->nome;
        $resumo = self::resumoComercial($this->resource);
        $capi = self::capi($this->resource);
        $cardFundo = $this->fundoUrgenciaPrevisao();

        if ($ganho) {
            $dataLimiteEm = $this->concluida_em ?? $this->etapa_desde;
            $dataLimiteLabel = 'Concluída em';
            $dataLimiteCor = '#14574F';
        } elseif ($perdido) {
            $dataLimiteEm = $this->etapa_desde;
            $dataLimiteLabel = 'Encerrada em';
            $dataLimiteCor = '#9B3B2F';
        } elseif ($this->previsao_fechamento !== null) {
            $dataLimiteEm = $this->previsao_fechamento;
            $dataLimiteLabel = 'Previsão de fechamento';
            $dataLimiteAtrasada = $dataLimiteEm->isBefore(now()->startOfDay());
            $dataLimiteCor = $dataLimiteAtrasada ? '#9B3B2F' : '#3C4450';
        } elseif ($this->proxima_tarefa_em !== null) {
            $dataLimiteEm = $this->proxima_tarefa_em;
            $dataLimiteLabel = 'Próxima tarefa em';
            $dataLimiteAtrasada = $dataLimiteEm->isBefore(now()->startOfDay());
            $dataLimiteCor = $dataLimiteAtrasada ? '#9B3B2F' : '#3C4450';
        } else {
            $dataLimiteEm = null;
            $dataLimiteLabel = 'Previsão de fechamento';
            $dataLimiteCor = '#3C4450';
        }

        return [
            'id' => $this->id,
            'capi' => $capi,
            'funilId' => $this->funil_id,
            'etapaId' => $this->etapa_funil_id,
            'nome' => $resumo['nome'],
            'assunto' => $this->assunto,
            'conta' => $empresaNome
                ? $empresaNome.($contatoNome ? ' · '.$contatoNome : '')
                : $contatoNome,
            'canal' => $resumo['canal'],
            'canalCor' => $resumo['canalCor'],
            'statusAtendimento' => $this->statusAtendimento?->nome,
            'statusAtendimentoCor' => $this->statusAtendimento?->cor_texto ?? '#77808E',
            'statusAtendimentoBg' => $this->statusAtendimento?->cor_fundo ?? '#F4F2EC',
            'statusQualificacao' => $this->statusQualificacao?->nome,
            'statusQualificacaoCor' => $this->statusQualificacao?->cor_texto ?? '#77808E',
            'statusQualificacaoBg' => $this->statusQualificacao?->cor_fundo ?? '#F4F2EC',
            'etapa' => $this->etapaFunil->nome,
            'funilNome' => $this->funil->slug === 'b2b' ? 'B2B consultivo' : $this->funil->nome,
            'responsavel' => $resumo['responsavel'],
            'iniciais' => Str::of($this->responsavel->name)
                ->explode(' ')
                ->map(fn (string $parte): string => mb_substr($parte, 0, 1))
                ->take(2)
                ->implode(''),
            'valor' => (float) $this->valor,
            'valorFmt' => self::moeda((float) $this->valor),
            'previsao' => $this->previsao_fechamento?->format('d/m') ?? '—',
            'cardFundo' => $cardFundo,
            'contratoAssinado' => $ganho,
            'atendimentoEncerrado' => $perdido,
            'dataLimiteLabel' => $dataLimiteLabel,
            'dataLimite' => $dataLimiteEm?->format('d/m/Y') ?? '—',
            'dataLimiteCor' => $dataLimiteCor,
            'tarefa' => $this->proxima_tarefa ?: '—',
            'tarefaCor' => $tarefaAtrasada ? '#9B3B2F' : '#3C4450',
            'slaLabel' => $dias === 0 ? 'hoje' : $dias.'d na etapa',
            'slaCor' => $atrasado ? '#9B3B2F' : ($atencao ? '#8C6F3F' : '#77808E'),
            'slaBg' => $atrasado ? '#F8ECE9' : ($atencao ? '#FBF1DF' : '#F4F2EC'),
            'drawer' => [
                ...$resumo,
                'capi' => $capi,
                'historicos' => self::historicosTimeline($this->historicos),
                'avancandoLabel' => match (true) {
                    $ganho => 'Registrar contrato assinado',
                    $perdido => 'Atendimento encerrado',
                    default => 'Avançar etapa',
                },
            ],
        ];
    }

    /**
     * "A CAPI enviaria um evento desta negociação agora?" — para o funil ver a
     * mesma verdade que o gate de RegistrarEventoConversaoMeta aplica.
     *
     * @return array{estado: string, label: string, cor: string, bg: string}
     */
    private static function capi(Negociacao $negociacao): array
    {
        $estado = app(ElegibilidadeEventoMeta::class)->estado($negociacao);

        return [
            'estado' => $estado,
            'label' => match ($estado) {
                'enviavel' => 'CAPI ✓',
                default => '',
            },
            'cor' => match ($estado) {
                'enviavel' => '#14574F',
                default => '#77808E',
            },
            'bg' => match ($estado) {
                'enviavel' => '#E7F0EE',
                default => '#F4F2EC',
            },
        ];
    }

    /**
     * Fundo do card do funil conforme a proximidade da previsão de fechamento.
     */
    private function fundoUrgenciaPrevisao(): ?string
    {
        if ($this->previsao_fechamento === null) {
            return null;
        }

        if ($this->etapaFunil->isGanho() || $this->etapaFunil->isPerdido()) {
            return null;
        }

        $hoje = now()->startOfDay();
        $previsao = $this->previsao_fechamento->copy()->startOfDay();

        if ($previsao->lessThanOrEqualTo($hoje)) {
            return '#FDF2F0';
        }

        if ($previsao->lessThanOrEqualTo($hoje->copy()->addDays(2))) {
            return '#FFFBEB';
        }

        return null;
    }

    /**
     * @return array{
     *     nome: string,
     *     subtitulo: string,
     *     canal: string,
     *     canalCor: string,
     *     responsavel: string,
     *     consent: string,
     *     consentBg: string,
     *     consentCor: string,
     *     campos: list<array{label: string, valor: string}>
     * }
     */
    public static function resumoComercial(Negociacao $negociacao): array
    {
        $negociacao->loadMissing([
            'funil:id,nome,slug',
            'etapaFunil:id,nome',
            'empresa:id,nome',
            'contato.statusConsentimento:id,slug,nome,cor_fundo,cor_texto',
            'contato.consentimentos:id,contato_id,finalidade_consentimento_id,status_consentimento_id,revogado_em',
            'contato.consentimentos.finalidade:id,slug',
            'contato.consentimentos.statusConsentimento:id,slug',
            'canalContato:id,nome,cor',
            'responsavel:id,name',
        ]);

        $consentimento = $negociacao->contato->statusConsentimento;
        $empresaNome = $negociacao->empresa?->nome;
        $contatoNome = $negociacao->contato->nome;

        return [
            'nome' => $empresaNome ?: $contatoNome,
            'subtitulo' => $negociacao->assunto.' · '.$negociacao->etapaFunil->nome,
            'canal' => $negociacao->canalContato->nome,
            'canalCor' => $negociacao->canalContato->cor,
            'responsavel' => $negociacao->responsavel->name,
            'consent' => 'LGPD: '.$consentimento->nome,
            'consentBg' => $consentimento->cor_fundo,
            'consentCor' => $consentimento->cor_texto,
            'campos' => [
                ['label' => 'Empresa', 'valor' => $empresaNome ?: '— (pessoa física)'],
                ['label' => 'Contato', 'valor' => $contatoNome],
                ['label' => 'Valor estimado', 'valor' => self::moeda((float) $negociacao->valor)],
                ['label' => 'Previsão de fechamento', 'valor' => $negociacao->previsao_fechamento?->format('d/m') ?? '—'],
                ['label' => 'Próxima tarefa', 'valor' => $negociacao->proxima_tarefa ?: '—'],
            ],
        ];
    }

    /**
     * @param  iterable<int, HistoricoNegociacao>  $historicos
     * @return list<array{id: int, titulo: string, descricao: string, quando: string, autor: string, cor: string}>
     */
    public static function historicosTimeline(iterable $historicos): array
    {
        return collect($historicos)
            ->sortBy([
                ['ocorrido_em', 'desc'],
                ['id', 'desc'],
            ])
            ->values()
            ->map(fn (HistoricoNegociacao $item): array => [
                'id' => $item->id,
                'titulo' => $item->titulo,
                'descricao' => $item->descricao,
                'quando' => self::quandoRelativo($item->ocorrido_em),
                'autor' => $item->autor,
                'cor' => match ($item->tipo) {
                    'wa' => '#14574F',
                    'call' => '#3F5E8C',
                    'mail' => '#8C6F3F',
                    'task' => '#8C4A6B',
                    default => '#77808E',
                },
            ])
            ->all();
    }

    public static function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 0, ',', '.');
    }

    public static function moedaCompacta(float $valor): string
    {
        if ($valor >= 1000) {
            $casas = $valor >= 100000 ? 0 : 1;

            return 'R$ '.number_format($valor / 1000, $casas, ',', '.').'k';
        }

        return self::moeda($valor);
    }

    public static function quandoRelativo(DateTimeInterface $ocorridoEm): string
    {
        $ocorridoEm = Carbon::parse($ocorridoEm);

        if ($ocorridoEm->isToday()) {
            return 'hoje '.$ocorridoEm->format('H:i');
        }

        $dias = (int) $ocorridoEm->startOfDay()->diffInDays(now()->startOfDay());

        return 'há '.$dias.' '.($dias === 1 ? 'dia' : 'dias');
    }
}
