<?php

namespace App\Http\Controllers;

use App\Enums\StatusTarefaNegociacao;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use Inertia\Inertia;
use Inertia\Response;

class CalendarioController extends Controller
{
    /**
     * Display the CRM calendar with open negotiation tasks and close dates.
     */
    public function index(): Response
    {
        $funis = Funil::query()
            ->orderBy('ordem')
            ->get(['id', 'nome', 'slug']);

        $relacoes = [
            'funil:id,nome,slug',
            'etapaFunil:id,nome',
            'empresa:id,nome',
            'contato:id,nome',
            'responsavel:id,name',
        ];

        $eventos = [];

        // Todas as tarefas abertas (não só a próxima de cada negociação).
        $tarefas = TarefaNegociacao::query()
            ->with([
                'responsavel:id,name',
                ...array_map(fn (string $relacao): string => 'negociacao.'.$relacao, $relacoes),
            ])
            ->whereIn('status', StatusTarefaNegociacao::abertos())
            ->orderBy('data')
            ->orderByRaw('hora is null')
            ->orderBy('hora')
            ->get();

        foreach ($tarefas as $tarefa) {
            $eventos[] = [
                ...$this->dadosNegociacao($tarefa->negociacao),
                'id' => 'tarefa-'.$tarefa->id,
                'tipo' => 'tarefa',
                'tipoTarefa' => $tarefa->tipo->value,
                'tipoTarefaLabel' => $tarefa->tipo->label(),
                'data' => $tarefa->data->format('Y-m-d'),
                'hora' => $tarefa->hora?->format('H:i') ?? '—',
                'titulo' => $tarefa->descricao,
                'responsavelNome' => $tarefa->responsavel?->name
                    ?? $tarefa->negociacao->responsavel?->name
                    ?? 'Sem responsável',
            ];
        }

        $negociacoes = Negociacao::query()
            ->with($relacoes)
            ->whereNotNull('previsao_fechamento')
            ->orderByDesc('id')
            ->get();

        foreach ($negociacoes as $negociacao) {
            if ($negociacao->previsao_fechamento === null) {
                continue;
            }

            $eventos[] = [
                ...$this->dadosNegociacao($negociacao),
                'id' => 'previsao-'.$negociacao->id,
                'tipo' => 'previsao',
                'data' => $negociacao->previsao_fechamento->format('Y-m-d'),
                'hora' => '—',
                'titulo' => 'Previsão de fechamento',
            ];
        }

        return Inertia::render('crm/Calendario', [
            'hoje' => now()->toDateString(),
            'funis' => $funis->map(fn (Funil $funil): array => [
                'id' => $funil->id,
                'slug' => $funil->slug,
                'nome' => $funil->slug === 'b2b' ? 'B2B consultivo' : $funil->nome,
            ])->values()->all(),
            'eventos' => $eventos,
        ]);
    }

    /**
     * @return array{lead: string, contatoNome: string, etapaNome: string, responsavelNome: string, funilId: int, funilNome: string, negociacaoId: int}
     */
    private function dadosNegociacao(Negociacao $negociacao): array
    {
        $contatoNome = $negociacao->contato->nome;

        return [
            'lead' => $negociacao->empresa?->nome ?: $contatoNome,
            'contatoNome' => $contatoNome,
            'etapaNome' => $negociacao->etapaFunil->nome,
            'responsavelNome' => $negociacao->responsavel?->name ?? 'Sem responsável',
            'funilId' => $negociacao->funil_id,
            'funilNome' => $negociacao->funil->slug === 'b2b'
                ? 'B2B consultivo'
                : $negociacao->funil->nome,
            'negociacaoId' => $negociacao->id,
        ];
    }
}
