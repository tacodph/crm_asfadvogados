<?php

namespace Database\Factories;

use App\Models\CanalContato;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CanalContato>
 */
class CanalContatoFactory extends Factory
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
            'cor' => fake()->hexColor(),
            'ordem' => 1,
        ];
    }
}
