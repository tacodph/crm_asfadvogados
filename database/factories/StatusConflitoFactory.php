<?php

namespace Database\Factories;

use App\Models\StatusConflito;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatusConflito>
 */
class StatusConflitoFactory extends Factory
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
            'cor_fundo_detalhe' => '#F1F6F4',
            'cor_borda_detalhe' => '#CCE0DA',
            'ordem' => 1,
        ];
    }
}
