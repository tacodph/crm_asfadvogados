<?php

namespace App\Enums;

/**
 * Nomes de eventos padrão da Meta (Conversions API). O `value` é o nome exato
 * que a Meta espera no campo `event_name` — não traduzir.
 */
enum MetaEventName: string
{
    case Lead = 'Lead';
    case Contact = 'Contact';
    case Schedule = 'Schedule';
    case SubmitApplication = 'SubmitApplication';
    case CompleteRegistration = 'CompleteRegistration';
    case Purchase = 'Purchase';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Contact => 'Contato',
            self::Schedule => 'Agendamento',
            self::SubmitApplication => 'Proposta enviada',
            self::CompleteRegistration => 'Cadastro concluído',
            self::Purchase => 'Compra / contrato fechado',
        };
    }
}
