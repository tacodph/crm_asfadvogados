<?php

namespace App\Actions\Crm;

use App\Enums\EtapaFunilResultado;
use App\Enums\StatusTarefaNegociacao;
use App\Models\TarefaNegociacao;
use App\Notifications\FollowUpLembreteNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Envia, uma única vez, o lembrete das tarefas abertas de hoje do tenant
 * atual: as com hora marcada até `antecedencia_minutos` antes do horário, as
 * sem hora a partir de `hora_sem_horario`. Tarefas adiadas voltam a ser
 * lembradas (o adiamento zera `lembrete_enviado_em`).
 */
class LembrarTarefasDoDia
{
    /**
     * @return int quantidade de lembretes enviados
     */
    public function __invoke(CarbonInterface $agora): int
    {
        $limiteMomento = $agora->copy()->addMinutes((int) config('crm.lembretes.antecedencia_minutos'));
        $limite = $limiteMomento->toDateString() > $agora->toDateString() ? '23:59' : $limiteMomento->format('H:i');
        $semHorarioLiberado = $agora->format('H:i') >= (string) config('crm.lembretes.hora_sem_horario');

        $tarefas = TarefaNegociacao::query()
            ->with([
                'responsavel',
                'negociacao:id,empresa_id,contato_id,assunto',
                'negociacao.empresa:id,nome',
                'negociacao.contato:id,nome',
            ])
            ->whereIn('status', StatusTarefaNegociacao::abertos())
            ->whereNull('lembrete_enviado_em')
            ->whereNotNull('responsavel_user_id')
            ->whereDate('data', $agora->toDateString())
            ->whereHas('negociacao.etapaFunil', fn (Builder $query) => $query->where('resultado', EtapaFunilResultado::Aberta->value))
            ->get()
            // A hora é comparada em PHP: a coluna `time` guarda o valor em formatos
            // diferentes conforme o banco (o cast `datetime:H:i` normaliza a leitura).
            ->filter(fn (TarefaNegociacao $tarefa): bool => $tarefa->hora === null
                ? $semHorarioLiberado
                : $tarefa->hora->format('H:i') <= $limite);

        foreach ($tarefas as $tarefa) {
            if ($tarefa->responsavel === null) {
                continue;
            }

            $tarefa->responsavel->notify(new FollowUpLembreteNotification($tarefa));
            $tarefa->forceFill(['lembrete_enviado_em' => now()])->save();
        }

        return $tarefas->count();
    }
}
