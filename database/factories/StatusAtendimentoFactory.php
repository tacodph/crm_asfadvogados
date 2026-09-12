<?php

namespace Database\Factories;

use App\Models\StatusAtendimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusAtendimento>
 */
class StatusAtendimentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->words(3, true);

        return [
            'slug' => str($nome)->slug()->toString(),
            'nome' => ucfirst($nome),
            'cor_fundo' => '#E8EEF6',
            'cor_texto' => '#3F5E8C',
            'ordem' => fake()->numberBetween(1, 20),
        ];
    }
}
