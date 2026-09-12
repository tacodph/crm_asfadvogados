<?php

namespace Database\Factories;

use App\Models\StatusComercial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatusComercial>
 */
class StatusComercialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($nome),
            'nome' => $nome,
            'descricao' => fake()->optional()->sentence(),
            'cor_fundo' => '#F4F2EC',
            'cor_texto' => '#77808E',
            'ordem' => 1,
        ];
    }
}
