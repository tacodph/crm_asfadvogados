<?php

namespace Database\Factories;

use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEstatistica;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaConversaoEstatistica>
 */
class MetaConversaoEstatisticaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_conversao_config_id' => MetaConversaoConfig::factory(),
            'referencia' => today(),
            'pixel_last_fired_at' => now(),
            'eventos_servidor' => fake()->numberBetween(0, 500),
            'eventos_navegador' => fake()->numberBetween(0, 500),
            'eventos_deduplicados' => fake()->numberBetween(0, 100),
            'match_rate' => fake()->randomFloat(2, 0, 100),
            'payload_bruto' => ['ok' => true],
        ];
    }
}
