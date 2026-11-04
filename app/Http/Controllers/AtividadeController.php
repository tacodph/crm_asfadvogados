<?php

namespace App\Http\Controllers;

use App\Enums\StatusTarefaNegociacao;
use App\Models\TarefaNegociacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AtividadeController extends Controller
{
    private const FILTROS = ['atuais', 'atrasadas', 'concluidas', 'todas'];

    private const LIMITE = 300;

    /**
     * Lista completa das tarefas (minhas ou da equipe), com filtros.
     */
    public function index(Request $request): Response
    {
        $escopo = $request->query('escopo') === 'equipe' ? 'equipe' : 'minhas';
        $filtro = in_array($request->query('filtro'), self::FILTROS, true) ? (string) $request->query('filtro') : 'atuais';

        if ($escopo === 'equipe') {
            Gate::authorize('ver-agenda-equipe');
        }

        $hoje = now(config('crm.timezone'))->toDateString();
        $doEscopo = fn (): Builder => TarefaNegociacao::query()
            ->when($escopo === 'minhas', fn (Builder $query) => $query->where('responsavel_user_id', $request->user()->id));

        $tarefas = $doEscopo()
            ->with([
                'responsavel:id,name',
                'negociacao:id,empresa_id,contato_id,assunto',
                'negociacao.empresa:id,nome',
                'negociacao.contato:id,nome',
            ])
            ->when($filtro === 'atuais', fn (Builder $query) => $query->whereIn('status', StatusTarefaNegociacao::abertos()))
            ->when($filtro === 'atrasadas', fn (Builder $query) => $query
                ->whereIn('status', StatusTarefaNegociacao::abertos())
                ->whereDate('data', '<', $hoje))
            ->when($filtro === 'concluidas', fn (Builder $query) => $query->where('status', StatusTarefaNegociacao::Concluida->value))
            ->when(
                $filtro === 'concluidas',
                fn (Builder $query) => $query->orderByDesc('concluida_em'),
                fn (Builder $query) => $query->orderBy('data')->orderByRaw('hora is null')->orderBy('hora'),
            )
            ->limit(self::LIMITE)
            ->get();

        return Inertia::render('crm/Atividades', [
            'escopo' => $escopo,
            'filtro' => $filtro,
            'hoje' => $hoje,
            'podeVerEquipe' => Gate::allows('ver-agenda-equipe'),
            'kpis' => [
                'pendentes' => $doEscopo()->whereIn('status', StatusTarefaNegociacao::abertos())->count(),
                'atrasadas' => $doEscopo()->whereIn('status', StatusTarefaNegociacao::abertos())->whereDate('data', '<', $hoje)->count(),
                'hoje' => $doEscopo()->whereIn('status', StatusTarefaNegociacao::abertos())->whereDate('data', $hoje)->count(),
                'concluidas' => $doEscopo()->where('status', StatusTarefaNegociacao::Concluida->value)->count(),
            ],
            'atividades' => $tarefas->map(fn (TarefaNegociacao $tarefa): array => [
                'id' => $tarefa->id,
                'negociacaoId' => $tarefa->negociacao_id,
                'descricao' => $tarefa->descricao,
                'tipo' => $tarefa->tipo->value,
                'tipoLabel' => $tarefa->tipo->label(),
                'status' => $tarefa->status->value,
                'statusLabel' => $tarefa->status->label(),
                'aberta' => $tarefa->status->isAberta(),
                'data' => $tarefa->data->toDateString(),
                'hora' => $tarefa->hora?->format('H:i'),
                'assunto' => $tarefa->negociacao->assunto,
                'cliente' => $tarefa->negociacao->empresa?->nome ?: $tarefa->negociacao->contato?->nome,
                'responsavelNome' => $tarefa->responsavel?->name,
            ])->values()->all(),
            'limite' => self::LIMITE,
        ]);
    }
}
