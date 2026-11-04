<?php

namespace App\Http\Controllers;

use App\Actions\Crm\RegistrarContatoPorTarefa;
use App\Actions\Crm\RegistrarHistoricoNegociacao;
use App\Actions\Crm\SyncProximaTarefaNegociacao;
use App\Enums\StatusTarefaNegociacao;
use App\Http\Requests\AdiarTarefaNegociacaoRequest;
use App\Models\TarefaNegociacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Ações rápidas sobre uma tarefa a partir do "Meu dia" e de Atividades
 * (concluir e adiar), sem abrir a negociação.
 */
class AgendaTarefaController extends Controller
{
    public function concluir(
        Request $request,
        TarefaNegociacao $tarefa,
        SyncProximaTarefaNegociacao $syncProximaTarefa,
        RegistrarHistoricoNegociacao $registrarHistorico,
        RegistrarContatoPorTarefa $registrarContato,
    ): RedirectResponse {
        $negociacao = $tarefa->negociacao;

        if ($tarefa->status->isAberta()) {
            $tarefa->update([
                'status' => StatusTarefaNegociacao::Concluida,
                'concluida_em' => now(),
            ]);

            $registrarContato($negociacao, $tarefa);
            $syncProximaTarefa($negociacao);
            $registrarHistorico->tarefaAtualizada($negociacao, $tarefa, $request->user());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tarefa concluída.',
        ]);

        return back();
    }

    public function adiar(
        AdiarTarefaNegociacaoRequest $request,
        TarefaNegociacao $tarefa,
        SyncProximaTarefaNegociacao $syncProximaTarefa,
        RegistrarHistoricoNegociacao $registrarHistorico,
    ): RedirectResponse {
        $negociacao = $tarefa->negociacao;
        $dados = $request->validated();

        $tarefa->update([
            'data' => $dados['data'],
            'hora' => array_key_exists('hora', $dados) ? $dados['hora'] : $tarefa->hora?->format('H:i'),
            'lembrete_enviado_em' => null,
        ]);

        $syncProximaTarefa($negociacao);
        $registrarHistorico->tarefaAtualizada($negociacao, $tarefa, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tarefa adiada para '.$tarefa->data->format('d/m/Y').'.',
        ]);

        return back();
    }
}
