<?php

namespace Database\Factories;

use App\Models\EtapaFunil;
use App\Models\Funil;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtapaFunil>
 */
class EtapaFunilFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funil_id' => Funil::factory(),
            'nome' => fake()->words(2, true),
            'sla' => fake()->randomElement(['15min', '4h', '1d', '5d']),
            'campos' => ['origem', 'consentimento'],
            'exige_motivo' => false,
            'ordem' => 1,
            'cor_fundo' => '#333A45',
            'cor_texto' => '#FBF9F4',
            'cor_suave' => 'rgba(251,249,244,0.9)',
        ];
    }
}
