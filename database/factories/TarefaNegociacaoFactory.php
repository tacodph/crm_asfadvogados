<?php

namespace Database\Factories;

use App\Enums\StatusTarefaNegociacao;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TarefaNegociacao>
 */
class TarefaNegociacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negociacao_id' => Negociacao::factory(),
            'descricao' => fake()->sentence(4),
            'data' => now()->toDateString(),
            'hora' => '09:00',
            'status' => StatusTarefaNegociacao::Pendente,
            'concluida_em' => null,
            'criado_por_user_id' => User::factory(),
        ];
    }

    public function concluida(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusTarefaNegociacao::Concluida,
            'concluida_em' => now(),
        ]);
    }
}
