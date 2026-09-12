<?php

namespace Database\Factories;

use App\Enums\StatusProposta;
use App\Models\Negociacao;
use App\Models\Proposta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposta>
 */
class PropostaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'negociacao_id' => Negociacao::factory(),
            'contato_id' => fn (array $attributes): int => Negociacao::query()->findOrFail($attributes['negociacao_id'])->contato_id,
            'empresa_id' => fn (array $attributes): ?int => Negociacao::query()->findOrFail($attributes['negociacao_id'])->empresa_id,
            'autor_user_id' => User::factory(),
            'codigo' => 'PR-'.now()->format('Y').'-'.fake()->unique()->numberBetween(100, 999),
            'versao' => 1,
            'titulo' => fake()->sentence(3),
            'escopo' => fake()->paragraph(),
            'honorarios' => fake()->randomElement([6500, 25000, 48000, 96000, 120000, 210000]),
            'parcelamento' => '3 parcelas mensais',
            'indice_reajuste' => 'IPCA',
            'modelo_origem' => 'Consultivo padrão OAB/RS',
            'status' => StatusProposta::Enviada,
            'valido_ate' => now()->addDays(10)->toDateString(),
            'enviado_em' => now()->subDays(2),
            'aceito_em' => null,
            'clausulas' => [
                [
                    'titulo' => 'Objeto',
                    'texto' => 'Prestação de serviços advocatícios consultivos no escopo descrito, sem garantia de resultado.',
                ],
                [
                    'titulo' => 'Honorários',
                    'texto' => 'Honorários fixos conforme tabela deste instrumento, alinhados à tabela mínima da OAB/seccional.',
                ],
            ],
        ];
    }

    public function aceita(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusProposta::Aceita,
            'aceito_em' => now()->subDay(),
            'valido_ate' => now()->addMonths(12)->toDateString(),
        ]);
    }

    public function expirada(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusProposta::Expirada,
            'valido_ate' => now()->subDays(6)->toDateString(),
            'enviado_em' => now()->subDays(21),
        ]);
    }
}
