<?php

namespace Database\Factories;

use App\Models\StatusConsentimento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatusConsentimento>
 */
class StatusConsentimentoFactory extends Factory
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
            'cor_fundo' => '#E7F0EE',
            'cor_texto' => '#14574F',
            'visivel_cadastro' => true,
            'ordem' => 1,
        ];
    }
}
