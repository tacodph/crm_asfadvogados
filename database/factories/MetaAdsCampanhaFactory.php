<?php

namespace Database\Factories;

use App\Enums\MetaAdsObjetivo;
use App\Enums\MetaAdsStatus;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaAdsCampanha>
 */
class MetaAdsCampanhaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_ads_conta_id' => MetaAdsConta::factory(),
            'meta_campaign_id' => (string) fake()->unique()->numerify('############'),
            'nome' => 'Campanha '.fake()->randomElement(['Bancário', 'Concurso', 'Institucional']),
            'objetivo' => MetaAdsObjetivo::OutcomeLeads,
            'status' => MetaAdsStatus::Ativo,
            'effective_status' => 'ACTIVE',
            'orcamento_diario_centavos' => fake()->numberBetween(5_00, 500_00),
            'orcamento_total_centavos' => null,
            'inicio_em' => now()->subDays(30),
            'fim_em' => null,
            'bruto' => [],
            'sincronizado_em' => now(),
            'arquivado_em' => null,
        ];
    }

    public function pausada(): static
    {
        return $this->state(fn (): array => [
            'status' => MetaAdsStatus::Pausado,
            'effective_status' => 'PAUSED',
        ]);
    }

    public function arquivada(): static
    {
        return $this->state(fn (): array => ['arquivado_em' => now()]);
    }
}
