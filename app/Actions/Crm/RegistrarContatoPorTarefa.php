<?php

namespace App\Actions\Crm;

use App\Enums\StatusTarefaNegociacao;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;

/**
 * Concluir follow-up, ligação ou reunião conta como contato com o lead:
 * grava `negociacoes.ultimo_contato_em`.
 */
class RegistrarContatoPorTarefa
{
    public function __invoke(Negociacao $negociacao, TarefaNegociacao $tarefa): void
    {
        if ($tarefa->status !== StatusTarefaNegociacao::Concluida || ! $tarefa->tipo->registraContato()) {
            return;
        }

        $negociacao->forceFill([
            'ultimo_contato_em' => $tarefa->concluida_em ?? now(),
        ])->save();
    }
}
