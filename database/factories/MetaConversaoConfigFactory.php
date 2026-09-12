<?php

namespace Database\Factories;

use App\Models\MetaConversaoConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MetaConversaoConfig>
 */
class MetaConversaoConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->randomElement(['Bancário', 'Concurso', 'Previdenciário', 'Trabalhista']);

        return [
            'nome_campanha' => $nome,
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 999999),
            'pixel_id' => (string) fake()->numerify('###############'),
            // O cast SegredoMeta cifra ao persistir; token_ultimos4 vem do hook saving.
            'access_token' => 'EAA'.Str::random(180),
            'test_event_code' => null,
            'api_version' => 'v21.0',
            'action_source' => 'system_generated',
            'origem_url' => 'https://www.instagram.com/asfadvogados_/',
            'finalidade_consentimento_slug' => 'marketing',
            'ativo' => true,
        ];
    }

    public function bancario(): static
    {
        return $this->state(fn (): array => [
            'nome_campanha' => 'Bancário',
            'slug' => 'bancario',
            'pixel_id' => '2050053805929053',
        ]);
    }

    public function concurso(): static
    {
        return $this->state(fn (): array => [
            'nome_campanha' => 'Concurso',
            'slug' => 'concurso',
            'pixel_id' => '1536850061554800',
        ]);
    }

    public function inativa(): static
    {
        return $this->state(fn (): array => ['ativo' => false]);
    }

    /**
     * Marca o token como verificado após criar (via saveQuietly, para não
     * disparar o hook `saving` que zeraria a verificação).
     */
    public function tokenVerificado(): static
    {
        return $this->afterCreating(function (MetaConversaoConfig $config): void {
            $config->forceFill([
                'token_verificado_em' => now(),
                'token_valido' => true,
            ])->saveQuietly();
        });
    }
}
