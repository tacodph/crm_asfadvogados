<?php

namespace App\Actions\Crm;

use App\Enums\StatusTarefaNegociacao;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;

class SyncProximaTarefaNegociacao
{
    public function __invoke(Negociacao $negociacao): void
    {
        $proxima = TarefaNegociacao::query()
            ->where('negociacao_id', $negociacao->id)
            ->whereIn('status', StatusTarefaNegociacao::abertos())
            ->orderBy('data')
            ->orderByRaw('hora is null')
            ->orderBy('hora')
            ->first();

        $negociacao->forceFill([
            'proxima_tarefa' => $proxima?->descricao,
            'proxima_tarefa_em' => $proxima?->data?->toDateString(),
            'proxima_tarefa_hora' => $proxima?->hora?->format('H:i'),
        ])->save();
    }
}
