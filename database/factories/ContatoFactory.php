<?php

namespace Database\Factories;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contato>
 */
class ContatoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'cargo' => fake()->jobTitle(),
            'empresa_id' => Empresa::factory(),
            'tipo_pessoa_id' => TipoPessoa::query()->firstOrCreate(
                ['slug' => 'pj'],
                ['nome' => 'Pessoa jurídica', 'ordem' => 2],
            )->id,
            'email' => fake()->unique()->safeEmail(),
            'telefone' => fake()->numerify('(##) 9####-####'),
            'cpf' => null,
            'canal_contato_id' => CanalContato::factory(),
            'status_consentimento_id' => StatusConsentimento::factory(),
            'status_comercial_id' => StatusComercial::factory(),
            'registro_mesclado' => false,
            'observacao_deduplicacao' => null,
        ];
    }

    public function pessoaFisica(): static
    {
        return $this->state(fn (array $attributes): array => [
            'empresa_id' => null,
            'tipo_pessoa_id' => TipoPessoa::query()->firstOrCreate(
                ['slug' => 'pf'],
                ['nome' => 'Pessoa física', 'ordem' => 1],
            )->id,
            'cpf' => fake()->unique()->numerify('###.###.###-##'),
        ]);
    }
}
