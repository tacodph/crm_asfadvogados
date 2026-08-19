<?php

namespace Database\Factories;

use App\Models\FinalidadeConsentimento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinalidadeConsentimento>
 */
class FinalidadeConsentimentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($nome),
            'nome' => $nome,
            'ordem' => 1,
        ];
    }
}
