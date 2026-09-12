<?php

namespace Database\Factories;

use App\Models\StatusQualificacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusQualificacao>
 */
class StatusQualificacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->words(2, true);

        return [
            'slug' => str($nome)->slug()->toString(),
            'nome' => ucfirst($nome),
            'cor_fundo' => '#E7F0EE',
            'cor_texto' => '#14574F',
            'ordem' => fake()->numberBetween(1, 20),
        ];
    }
}
