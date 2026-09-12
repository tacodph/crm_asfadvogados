<?php

namespace App\Support\Meta;

use App\Enums\MetaEventName;
use App\Models\Negociacao;
use DateTimeInterface;

/**
 * Monta o array de 1 evento (`data[]`) da API de Conversões. É usado no
 * momento em que a linha `meta_conversao_eventos` é criada (prompt 04):
 * o `user_data` já chega hasheado por `AdvancedMatching`, e o resultado é
 * gravado em `request_payload`. O Job apenas reenvia o que está gravado.
 */
class CapiPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $userData  saída de AdvancedMatching::userDataFrom
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     */
    public function build(
        MetaEventName $eventName,
        string $eventId,
        DateTimeInterface $eventTime,
        string $actionSource,
        array $userData,
        array $customData = [],
        ?string $eventSourceUrl = null,
    ): array {
        $payload = [
            'event_name' => $eventName->value,
            'event_time' => $eventTime->getTimestamp(),
            'event_id' => $eventId,
            'action_source' => $actionSource,
            'user_data' => $userData,
        ];

        if ($actionSource === 'website' && $eventSourceUrl !== null && $eventSourceUrl !== '') {
            $payload['event_source_url'] = $eventSourceUrl;
        }

        $customData = array_filter(
            $customData,
            static fn ($valor): bool => $valor !== null && $valor !== '',
        );

        if ($customData !== []) {
            $payload['custom_data'] = $customData;
        }

        return $payload;
    }

    /**
     * `custom_data` padrão a partir de uma negociação (valor/moeda) e da campanha.
     *
     * @return array<string, mixed>
     */
    public function customDataParaNegociacao(?Negociacao $negociacao, string $contentName): array
    {
        // Só manda value/currency quando há valor real (> 0). Purchase com
        // value 0 envenena a otimização por valor — melhor omitir.
        $valor = $negociacao !== null ? (float) $negociacao->valor : 0.0;
        $temValor = $valor > 0.0;

        return [
            'currency' => $temValor ? 'BRL' : null,
            'value' => $temValor ? $valor : null,
            'lead_event_source' => 'crm_asfadvogados',
            'content_name' => $contentName,
        ];
    }
}
