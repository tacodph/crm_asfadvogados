<?php

namespace Database\Factories;

use App\Enums\MetaConversaoEventoStatus;
use App\Enums\MetaEventName;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MetaConversaoEvento>
 */
class MetaConversaoEventoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventName = fake()->randomElement(MetaEventName::cases());

        return [
            'meta_conversao_config_id' => MetaConversaoConfig::factory(),
            'negociacao_id' => null,
            'contato_id' => null,
            'event_name' => $eventName,
            'event_id' => Str::lower($eventName->value).'_'.fake()->unique()->numberBetween(1, 9_999_999),
            'event_time' => now(),
            'action_source' => 'system_generated',
            'status' => MetaConversaoEventoStatus::Pendente,
            'tentativas' => 0,
            'request_payload' => [
                'event_name' => $eventName->value,
                'event_id' => 'placeholder',
                'action_source' => 'system_generated',
            ],
            'is_teste' => false,
        ];
    }

    public function pendente(): static
    {
        return $this->state(fn (): array => ['status' => MetaConversaoEventoStatus::Pendente]);
    }

    public function enviado(): static
    {
        return $this->state(fn (): array => [
            'status' => MetaConversaoEventoStatus::Enviado,
            'http_status' => 200,
            'events_received' => 1,
            'fbtrace_id' => Str::random(20),
            'tentativas' => 1,
            'enviado_em' => now(),
            'response_body' => ['events_received' => 1, 'messages' => []],
        ]);
    }

    public function comErro(): static
    {
        return $this->state(fn (): array => [
            'status' => MetaConversaoEventoStatus::Erro,
            'http_status' => 400,
            'error_code' => '190',
            'error_message' => 'Error validating access token: session has expired.',
            'tentativas' => 1,
        ]);
    }

    public function descartado(string $motivo = 'sem_consentimento'): static
    {
        return $this->state(fn (): array => [
            'status' => MetaConversaoEventoStatus::Descartado,
            'motivo_descarte' => $motivo,
        ]);
    }

    public function teste(): static
    {
        return $this->state(fn (): array => [
            'is_teste' => true,
            'event_name' => MetaEventName::Lead,
            'event_id' => 'teste_'.Str::uuid()->toString(),
        ]);
    }
}
