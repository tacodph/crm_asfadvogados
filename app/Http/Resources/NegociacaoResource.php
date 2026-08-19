<?php

namespace App\Http\Resources;

use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
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
        $ultima = $this->funil->etapas->max('ordem') === $this->etapaFunil->ordem;
        $consentimento = $this->contato->statusConsentimento;
        $empresaNome = $this->empresa?->nome;
        $contatoNome = $this->contato->nome;

        return [
            'id' => $this->id,
            'funilId' => $this->funil_id,
            'etapaId' => $this->etapa_funil_id,
            'nome' => $empresaNome ?: $contatoNome,
            'assunto' => $this->assunto,
            'conta' => $empresaNome
                ? $empresaNome.($contatoNome ? ' · '.$contatoNome : '')
                : $contatoNome,
            'canal' => $this->canalContato->nome,
            'canalCor' => $this->canalContato->cor,
            'etapa' => $this->etapaFunil->nome,
            'funilNome' => $this->funil->slug === 'b2b' ? 'B2B consultivo' : $this->funil->nome,
            'responsavel' => $this->responsavel->name,
            'iniciais' => Str::of($this->responsavel->name)
                ->explode(' ')
                ->map(fn (string $parte): string => mb_substr($parte, 0, 1))
                ->take(2)
                ->implode(''),
            'valor' => (float) $this->valor,
            'valorFmt' => self::moeda((float) $this->valor),
            'previsao' => $this->previsao_fechamento?->format('d/m') ?? '—',
            'tarefa' => $this->proxima_tarefa ?: '—',
            'tarefaCor' => $tarefaAtrasada ? '#9B3B2F' : '#3C4450',
            'slaLabel' => $dias === 0 ? 'hoje' : $dias.'d na etapa',
            'slaCor' => $atrasado ? '#9B3B2F' : ($atencao ? '#8C6F3F' : '#77808E'),
            'slaBg' => $atrasado ? '#F8ECE9' : ($atencao ? '#FBF1DF' : '#F4F2EC'),
            'drawer' => [
                'nome' => $empresaNome ?: $contatoNome,
                'subtitulo' => $this->assunto.' · '.$this->etapaFunil->nome,
                'canal' => $this->canalContato->nome,
                'canalCor' => $this->canalContato->cor,
                'responsavel' => $this->responsavel->name,
                'consent' => 'LGPD: '.$consentimento->nome,
                'consentBg' => $consentimento->cor_fundo,
                'consentCor' => $consentimento->cor_texto,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => $empresaNome ?: '— (pessoa física)'],
                    ['label' => 'Contato', 'valor' => $contatoNome],
                    ['label' => 'Valor estimado', 'valor' => self::moeda((float) $this->valor)],
                    ['label' => 'Previsão de fechamento', 'valor' => $this->previsao_fechamento?->format('d/m') ?? '—'],
                    ['label' => 'Próxima tarefa', 'valor' => $this->proxima_tarefa ?: '—'],
                    [
                        'label' => 'Base legal',
                        'valor' => $consentimento->slug === 'revogado'
                            ? 'revogada — contato suspenso'
                            : 'consentimento (art. 7º, I)',
                    ],
                ],
                'historicos' => $this->historicos
                    ->map(fn (HistoricoNegociacao $item): array => [
                        'titulo' => $item->titulo,
                        'descricao' => $item->descricao,
                        'quando' => $this->quandoRelativo($item->ocorrido_em),
                        'autor' => $item->autor,
                        'cor' => match ($item->tipo) {
                            'wa' => '#14574F',
                            'call' => '#3F5E8C',
                            'mail' => '#8C6F3F',
                            'task' => '#8C4A6B',
                            default => '#77808E',
                        },
                    ])
                    ->values()
                    ->all(),
                'avancandoLabel' => $ultima ? 'Registrar contrato assinado' : 'Avançar etapa',
            ],
        ];
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

    private function quandoRelativo(DateTimeInterface $ocorridoEm): string
    {
        $ocorridoEm = Carbon::parse($ocorridoEm);

        if ($ocorridoEm->isToday()) {
            return 'hoje '.$ocorridoEm->format('H:i');
        }

        $dias = (int) $ocorridoEm->startOfDay()->diffInDays(now()->startOfDay());

        return 'há '.$dias.' '.($dias === 1 ? 'dia' : 'dias');
    }
}
