<?php

namespace Database\Factories;

use App\Models\Setor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Setor>
 */
class SetorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($nome),
            'nome' => Str::headline($nome),
            'ordem' => fake()->numberBetween(1, 20),
        ];
    }
}
