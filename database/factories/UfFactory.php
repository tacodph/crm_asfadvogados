<?php

namespace Database\Factories;

use App\Models\Uf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Uf>
 */
class UfFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sigla' => fake()->unique()->regexify('[A-Z]{2}'),
            'nome' => fake()->unique()->state(),
            'ordem' => fake()->numberBetween(1, 27),
        ];
    }
}
