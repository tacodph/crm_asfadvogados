<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Setor;
use App\Models\StatusConflito;
use App\Models\Uf;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->company(),
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'setor_id' => Setor::factory(),
            'porte' => fake()->numberBetween(10, 400).' funcionários',
            'cidade' => fake()->city(),
            'uf_id' => Uf::factory(),
            'responsavel_user_id' => User::factory(),
            'status_conflito_id' => StatusConflito::factory(),
            'conflito_texto' => null,
            'conflito_verificado_em' => null,
        ];
    }
}
