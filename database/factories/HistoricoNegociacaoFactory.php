<?php

namespace Database\Factories;

use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoricoNegociacao>
 */
class HistoricoNegociacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negociacao_id' => Negociacao::factory(),
            'tipo' => fake()->randomElement(['wa', 'call', 'mail', 'sys', 'task']),
            'titulo' => fake()->sentence(3),
            'descricao' => fake()->sentence(8),
            'autor' => fake()->name(),
            'ocorrido_em' => now()->subDays(fake()->numberBetween(0, 8)),
        ];
    }
}
