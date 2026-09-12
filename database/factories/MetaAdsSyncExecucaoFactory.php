<?php

namespace Database\Factories;

use App\Enums\MetaAdsSyncStatus;
use App\Enums\MetaAdsSyncTipo;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsSyncExecucao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaAdsSyncExecucao>
 */
class MetaAdsSyncExecucaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_ads_conta_id' => MetaAdsConta::factory(),
            'tipo' => MetaAdsSyncTipo::Estrutura,
            'status' => MetaAdsSyncStatus::Ok,
            'janela_inicio' => null,
            'janela_fim' => null,
            'objetos_afetados' => fake()->numberBetween(1, 20),
            'duracao_ms' => fake()->numberBetween(100, 5_000),
            'erro' => null,
            'iniciado_em' => now()->subMinute(),
            'concluido_em' => now(),
        ];
    }

    public function insights(): static
    {
        return $this->state(fn (): array => [
            'tipo' => MetaAdsSyncTipo::Insights,
            'janela_inicio' => today()->subDays(28),
            'janela_fim' => today(),
        ]);
    }

    public function parcial(): static
    {
        return $this->state(fn (): array => [
            'status' => MetaAdsSyncStatus::Parcial,
            'erro' => 'rate limit — retoma na próxima rodada',
        ]);
    }
}
