<?php

namespace App\Actions\Crm;

use App\Models\Negociacao;

class SyncConclusaoNegociacao
{
    /**
     * Grava ou limpa a data efetiva de conclusão da lead no funil
     * (somente etapa de ganho / contrato fechado).
     */
    public function __invoke(Negociacao $negociacao): void
    {
        $negociacao->loadMissing(['etapaFunil:id,resultado']);

        $estaNoGanho = $negociacao->etapaFunil !== null
            && $negociacao->etapaFunil->isGanho();

        if ($estaNoGanho) {
            if ($negociacao->concluida_em === null) {
                $negociacao->forceFill(['concluida_em' => now()])->save();
            }

            return;
        }

        if ($negociacao->concluida_em !== null) {
            $negociacao->forceFill(['concluida_em' => null])->save();
        }
    }
}
