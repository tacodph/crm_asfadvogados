<?php

namespace App\Http\Controllers;

use App\Actions\Crm\RegistrarHistoricoNegociacao;
use App\Actions\Crm\SyncProximaTarefaNegociacao;
use App\Enums\StatusTarefaNegociacao;
use App\Enums\TipoTarefaNegociacao;
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
            'tipo' => $dados['tipo'] ?? TipoTarefaNegociacao::FollowUp->value,
            'responsavel_user_id' => $dados['responsavel_user_id'] ?? $negociacao->responsavel_user_id,
            'negociacao_id' => $negociacao->id,
            'criado_por_user_id' => $request->user()?->id,
            'concluida_em' => $status === StatusTarefaNegociacao::Concluida ? now() : null,
        ]);

        $this->registrarContatoSeConcluida($negociacao, $tarefa);
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
        $estavaConcluida = $tarefa->status === StatusTarefaNegociacao::Concluida;
        $proximoFollowUp = [
            'data' => $dados['proximo_follow_up_data'] ?? null,
            'hora' => $dados['proximo_follow_up_hora'] ?? null,
            'descricao' => $dados['proximo_follow_up_descricao'] ?? null,
        ];

        unset($dados['proximo_follow_up_data'], $dados['proximo_follow_up_hora'], $dados['proximo_follow_up_descricao']);

        $tarefa->update([
            ...$dados,
            'tipo' => $dados['tipo'] ?? $tarefa->tipo->value,
            'responsavel_user_id' => $dados['responsavel_user_id'] ?? $tarefa->responsavel_user_id ?? $negociacao->responsavel_user_id,
            'concluida_em' => $status === StatusTarefaNegociacao::Concluida
                ? ($tarefa->concluida_em ?? now())
                : null,
        ]);

        if (! $estavaConcluida) {
            $this->registrarContatoSeConcluida($negociacao, $tarefa);
        }

        $proxima = null;

        if ($status === StatusTarefaNegociacao::Concluida && filled($proximoFollowUp['data'])) {
            $proxima = TarefaNegociacao::query()->create([
                'negociacao_id' => $negociacao->id,
                'descricao' => filled($proximoFollowUp['descricao']) ? $proximoFollowUp['descricao'] : 'Follow-up',
                'tipo' => TipoTarefaNegociacao::FollowUp,
                'data' => $proximoFollowUp['data'],
                'hora' => $proximoFollowUp['hora'],
                'status' => StatusTarefaNegociacao::Pendente,
                'responsavel_user_id' => $tarefa->responsavel_user_id ?? $negociacao->responsavel_user_id,
                'criado_por_user_id' => $request->user()?->id,
            ]);
        }

        $syncProximaTarefa($negociacao);
        $registrarHistorico->tarefaAtualizada($negociacao, $tarefa, $request->user());

        if ($proxima !== null) {
            $registrarHistorico->tarefaCriada($negociacao, $proxima, $request->user());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $proxima === null ? 'Tarefa atualizada.' : 'Tarefa concluída e próximo follow-up agendado.',
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

    /**
     * Concluir follow-up, ligação ou reunião conta como contato com o lead.
     */
    private function registrarContatoSeConcluida(Negociacao $negociacao, TarefaNegociacao $tarefa): void
    {
        if ($tarefa->status !== StatusTarefaNegociacao::Concluida || ! $tarefa->tipo->registraContato()) {
            return;
        }

        $negociacao->forceFill([
            'ultimo_contato_em' => $tarefa->concluida_em ?? now(),
        ])->save();
    }
}
