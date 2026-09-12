<?php

namespace App\Http\Controllers;

use App\Actions\Crm\RegistrarHistoricoNegociacao;
use App\Actions\Crm\SyncProximaTarefaNegociacao;
use App\Enums\StatusTarefaNegociacao;
use App\Http\Requests\StoreTarefaNegociacaoRequest;
use App\Http\Requests\UpdateTarefaNegociacaoRequest;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class NegociacaoTarefaController extends Controller
{
    /**
     * Store a new task for the negotiation.
     */
    public function store(
        StoreTarefaNegociacaoRequest $request,
        Negociacao $negociacao,
        SyncProximaTarefaNegociacao $syncProximaTarefa,
        RegistrarHistoricoNegociacao $registrarHistorico,
    ): RedirectResponse {
        $dados = $request->validated();
        $status = StatusTarefaNegociacao::from($dados['status']);

        $tarefa = TarefaNegociacao::query()->create([
            ...$dados,
            'negociacao_id' => $negociacao->id,
            'criado_por_user_id' => $request->user()?->id,
            'concluida_em' => $status === StatusTarefaNegociacao::Concluida ? now() : null,
        ]);

        $syncProximaTarefa($negociacao);
        $registrarHistorico->tarefaCriada($negociacao, $tarefa, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tarefa cadastrada.',
        ]);

        return redirect()->route('negociacoes.edit', $negociacao);
    }

    /**
     * Update an existing negotiation task.
     */
    public function update(
        UpdateTarefaNegociacaoRequest $request,
        Negociacao $negociacao,
        TarefaNegociacao $tarefa,
        SyncProximaTarefaNegociacao $syncProximaTarefa,
        RegistrarHistoricoNegociacao $registrarHistorico,
    ): RedirectResponse {
        abort_unless($tarefa->negociacao_id === $negociacao->id, 404);

        $dados = $request->validated();
        $status = StatusTarefaNegociacao::from($dados['status']);

        $tarefa->update([
            ...$dados,
            'concluida_em' => $status === StatusTarefaNegociacao::Concluida
                ? ($tarefa->concluida_em ?? now())
                : null,
        ]);

        $syncProximaTarefa($negociacao);
        $registrarHistorico->tarefaAtualizada($negociacao, $tarefa, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tarefa atualizada.',
        ]);

        return redirect()->route('negociacoes.edit', $negociacao);
    }

    /**
     * Delete a negotiation task.
     */
    public function destroy(
        Negociacao $negociacao,
        TarefaNegociacao $tarefa,
        SyncProximaTarefaNegociacao $syncProximaTarefa,
        RegistrarHistoricoNegociacao $registrarHistorico,
    ): RedirectResponse {
        abort_unless($tarefa->negociacao_id === $negociacao->id, 404);

        $descricao = $tarefa->descricao;
        $autor = auth()->user();
        $tarefa->delete();

        $syncProximaTarefa($negociacao);
        $registrarHistorico->tarefaRemovida($negociacao, $descricao, $autor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tarefa removida.',
        ]);

        return redirect()->route('negociacoes.edit', $negociacao);
    }
}
