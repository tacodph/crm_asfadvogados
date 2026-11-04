<?php

namespace App\Notifications;

use App\Models\TarefaNegociacao;
use Illuminate\Notifications\Notification;

/**
 * Lembrete de uma tarefa do dia (follow-up, ligação, reunião ou tarefa).
 *
 * Hoje só vai para o sino do CRM (canal database). O canal fica isolado em
 * `via()` para que um canal de WhatsApp possa ser somado depois sem mexer
 * na regra de quando lembrar (ver LembrarTarefasDoDia).
 */
class FollowUpLembreteNotification extends Notification
{
    public function __construct(public TarefaNegociacao $tarefa) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{tarefaId: int, negociacaoId: int, tipo: string, tipoLabel: string, descricao: string, data: string, hora: string|null, lead: string|null, assunto: string}
     */
    public function toArray(object $notifiable): array
    {
        $negociacao = $this->tarefa->negociacao;

        return [
            'tarefaId' => $this->tarefa->id,
            'negociacaoId' => $negociacao->id,
            'tipo' => $this->tarefa->tipo->value,
            'tipoLabel' => $this->tarefa->tipo->label(),
            'descricao' => $this->tarefa->descricao,
            'data' => $this->tarefa->data->toDateString(),
            'hora' => $this->tarefa->hora?->format('H:i'),
            'lead' => $negociacao->empresa?->nome ?: $negociacao->contato?->nome,
            'assunto' => $negociacao->assunto,
        ];
    }
}
