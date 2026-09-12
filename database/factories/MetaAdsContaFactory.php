<?php

namespace Database\Factories;

use App\Models\MetaAdsConta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MetaAdsConta>
 */
class MetaAdsContaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nome = fake()->randomElement(['ASF — Bancário', 'ASF — Concurso', 'ASF — Institucional']);

        return [
            'nome' => $nome,
            'ad_account_id' => (string) fake()->unique()->numerify('################'),
            'business_id' => (string) fake()->numerify('###############'),
            'moeda' => 'BRL',
            'fuso_horario' => 'America/Sao_Paulo',
            // O cast SegredoMeta cifra ao persistir; token_ultimos4 vem do hook saving.
            'access_token' => 'EAA'.Str::random(120),
            'token_scopes' => ['ads_read'],
            'conta_status' => '1',
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (): array => ['ativo' => false]);
    }

    public function comGestao(): static
    {
        return $this->state(fn (): array => ['token_scopes' => ['ads_read', 'ads_management']]);
    }

    /**
     * Marca o token como verificado após criar (via saveQuietly, para não
     * disparar o hook `saving` que zeraria a verificação).
     */
    public function tokenVerificado(): static
    {
        return $this->afterCreating(function (MetaAdsConta $conta): void {
            $conta->forceFill([
                'token_verificado_em' => now(),
                'token_valido' => true,
            ])->saveQuietly();
        });
    }
}
