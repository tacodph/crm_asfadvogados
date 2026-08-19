<?php

namespace Database\Factories;

use App\Models\TipoPessoa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoPessoa>
 */
class TipoPessoaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->unique()->randomElement(['Pessoa física', 'Pessoa jurídica']);

        return [
            'slug' => Str::slug($nome),
            'nome' => $nome,
            'ordem' => 1,
        ];
    }

    public function fisica(): static
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => 'pf',
            'nome' => 'Pessoa física',
            'ordem' => 1,
        ]);
    }

    public function juridica(): static
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => 'pj',
            'nome' => 'Pessoa jurídica',
            'ordem' => 2,
        ]);
    }
}
