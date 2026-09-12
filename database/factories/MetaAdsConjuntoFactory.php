<?php

namespace Database\Factories;

use App\Enums\MetaAdsStatus;
use App\Models\MetaAdsCampanha;
use App\Models\MetaAdsConjunto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaAdsConjunto>
 */
class MetaAdsConjuntoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_ads_campanha_id' => MetaAdsCampanha::factory(),
            // Herda a conta da campanha (o valor de meta_ads_campanha_id já vem resolvido).
            'meta_ads_conta_id' => fn (array $attributes): int => MetaAdsCampanha::query()
                ->whereKey($attributes['meta_ads_campanha_id'])->firstOrFail()->meta_ads_conta_id,
            'meta_adset_id' => (string) fake()->unique()->numerify('############'),
            'nome' => 'Conjunto '.fake()->randomElement(['Interesses', 'Lookalike', 'Retargeting', 'Ampla']),
            'optimization_goal' => fake()->randomElement(['LEAD_GENERATION', 'LINK_CLICKS', 'OFFSITE_CONVERSIONS']),
            'status' => MetaAdsStatus::Ativo,
            'effective_status' => 'ACTIVE',
            'orcamento_diario_centavos' => fake()->numberBetween(5_00, 200_00),
            'orcamento_total_centavos' => null,
            'bruto' => [],
            'sincronizado_em' => now(),
            'arquivado_em' => null,
        ];
    }

    public function pausado(): static
    {
        return $this->state(fn (): array => [
            'status' => MetaAdsStatus::Pausado,
            'effective_status' => 'PAUSED',
        ]);
    }

    public function arquivado(): static
    {
        return $this->state(fn (): array => ['arquivado_em' => now()]);
    }
}
