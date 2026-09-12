<?php

namespace App\Http\Resources;

use App\Enums\MetaConversaoEventoStatus;
use App\Models\MetaConversaoEvento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MetaConversaoEvento
 */
class MetaConversaoEventoResource extends JsonResource
{
    /**
     * Linha da listagem `/trafego/eventos`. Nunca inclui `request_payload`
     * completo — só o `show` acrescenta o payload (já com PII hasheada).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `config` é opcional (eventos descartados por campanha indefinida) e a
        // relação não segue a convenção de nome do FK — `optional()` mantém o
        // acesso seguro sem o larastan achar que nunca é nulo.
        $campanha = optional($this->config);

        return [
            'id' => $this->id,
            'campanha' => $campanha->nome_campanha ?? '—',
            'campanha_slug' => $campanha->slug,
            'event_name' => $this->event_name->value,
            'event_name_label' => $this->event_name->label(),
            'event_id' => $this->event_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_cor' => self::cor($this->status),
            'event_time' => $this->event_time->toIso8601String(),
            'action_source' => $this->action_source,
            'negociacao_id' => $this->negociacao_id,
            'contato' => $this->contato?->nome,
            'tentativas' => $this->tentativas,
            'http_status' => $this->http_status,
            'events_received' => $this->events_received,
            'fbtrace_id' => $this->fbtrace_id,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'motivo_descarte' => $this->motivo_descarte,
            'is_teste' => $this->is_teste,
            'enviado_em' => $this->enviado_em?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public static function cor(MetaConversaoEventoStatus $status): string
    {
        return match ($status) {
            MetaConversaoEventoStatus::Enviado => '#14574F',
            MetaConversaoEventoStatus::Erro => '#9B3B2F',
            MetaConversaoEventoStatus::Descartado => '#8C6F3F',
            MetaConversaoEventoStatus::Pendente => '#77808E',
        };
    }
}
