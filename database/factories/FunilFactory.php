<?php

namespace Database\Factories;

use App\Models\Funil;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Funil>
 */
class FunilFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($nome),
            'nome' => Str::headline($nome),
            'distribuicao' => 'round robin simples',
            'ordem' => fake()->numberBetween(1, 10),
        ];
    }
}
