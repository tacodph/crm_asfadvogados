<?php

namespace App\Http\Controllers;

use App\Models\Funil;
use App\Models\Negociacao;
use Inertia\Inertia;
use Inertia\Response;

class CalendarioController extends Controller
{
    /**
     * Display the CRM calendar with negotiation tasks and close dates.
     */
    public function index(): Response
    {
        $funis = Funil::query()
            ->orderBy('ordem')
            ->get(['id', 'nome', 'slug']);

        $negociacoes = Negociacao::query()
            ->with([
                'funil:id,nome,slug',
                'etapaFunil:id,nome',
                'empresa:id,nome',
                'contato:id,nome',
                'responsavel:id,name',
            ])
            ->where(function ($query): void {
                $query->whereNotNull('proxima_tarefa_em')
                    ->orWhereNotNull('previsao_fechamento');
            })
            ->orderByDesc('id')
            ->get();

        $eventos = [];

        foreach ($negociacoes as $negociacao) {
            $contatoNome = $negociacao->contato->nome;
            $lead = $negociacao->empresa?->nome ?: $contatoNome;
            $funilNome = $negociacao->funil->slug === 'b2b'
                ? 'B2B consultivo'
                : $negociacao->funil->nome;
            $etapaNome = $negociacao->etapaFunil->nome;
            $responsavelNome = $negociacao->responsavel?->name ?? 'Sem responsável';

            if ($negociacao->proxima_tarefa_em !== null) {
                $eventos[] = [
                    'id' => 'tarefa-'.$negociacao->id,
                    'tipo' => 'tarefa',
                    'data' => $negociacao->proxima_tarefa_em->format('Y-m-d'),
                    'hora' => $negociacao->proxima_tarefa_hora?->format('H:i') ?? '—',
                    'titulo' => $negociacao->proxima_tarefa ?: 'Próxima tarefa',
                    'lead' => $lead,
                    'contatoNome' => $contatoNome,
                    'etapaNome' => $etapaNome,
                    'responsavelNome' => $responsavelNome,
                    'funilId' => $negociacao->funil_id,
                    'funilNome' => $funilNome,
                    'negociacaoId' => $negociacao->id,
                ];
            }

            if ($negociacao->previsao_fechamento !== null) {
                $eventos[] = [
                    'id' => 'previsao-'.$negociacao->id,
                    'tipo' => 'previsao',
                    'data' => $negociacao->previsao_fechamento->format('Y-m-d'),
                    'hora' => '—',
                    'titulo' => 'Previsão de fechamento',
                    'lead' => $lead,
                    'contatoNome' => $contatoNome,
                    'etapaNome' => $etapaNome,
                    'responsavelNome' => $responsavelNome,
                    'funilId' => $negociacao->funil_id,
                    'funilNome' => $funilNome,
                    'negociacaoId' => $negociacao->id,
                ];
            }
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
}
