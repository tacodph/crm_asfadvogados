<?php

namespace App\Observers;

use App\Enums\StatusTarefaNegociacao;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;

/**
 * Mantém as tarefas abertas acompanhando o responsável da negociação: quando
 * a negociação troca de dono (edição manual ou redistribuição do funil), as
 * tarefas que eram do dono anterior (ou sem dono) passam para o novo.
 */
class NegociacaoTarefasObserver
{
    public function updated(Negociacao $negociacao): void
    {
        if (! $negociacao->wasChanged('responsavel_user_id')) {
            return;
        }

        $anterior = $negociacao->getOriginal('responsavel_user_id');

        TarefaNegociacao::query()
            ->where('negociacao_id', $negociacao->id)
            ->whereIn('status', StatusTarefaNegociacao::abertos())
            ->where(function ($query) use ($anterior): void {
                $query->whereNull('responsavel_user_id')
                    ->when($anterior !== null, fn ($query) => $query->orWhere('responsavel_user_id', $anterior));
            })
            ->update(['responsavel_user_id' => $negociacao->responsavel_user_id]);
    }
}
