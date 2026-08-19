<?php

namespace Database\Factories;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Negociacao>
 */
class NegociacaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funil_id' => Funil::factory(),
            'etapa_funil_id' => fn (array $attributes): int => EtapaFunil::factory()->create([
                'funil_id' => $attributes['funil_id'],
            ])->id,
            'empresa_id' => null,
            'contato_id' => Contato::factory()->pessoaFisica(),
            'canal_contato_id' => CanalContato::factory(),
            'responsavel_user_id' => User::factory(),
            'assunto' => fake()->sentence(3),
            'valor' => fake()->randomElement([2400, 3200, 48000, 96000]),
            'previsao_fechamento' => fake()->optional()->date(),
            'etapa_desde' => now()->subDays(fake()->numberBetween(0, 8)),
            'proxima_tarefa' => 'Primeiro contato — SLA da etapa',
            'proxima_tarefa_em' => now()->toDateString(),
            'proxima_tarefa_hora' => '09:00',
        ];
    }
}
