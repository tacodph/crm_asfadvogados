<?php

namespace Database\Factories;

use App\Enums\MetaAdsNivel;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsConta;
use App\Models\MetaAdsInsightDiario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaAdsInsightDiario>
 */
class MetaAdsInsightDiarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $resultados = fake()->numberBetween(0, 8);
        $investimento = fake()->numberBetween(5_00, 500_00);
        $impressoes = fake()->numberBetween(500, 50_000);
        $cliques = fake()->numberBetween(5, 500);

        return [
            'meta_ads_conta_id' => MetaAdsConta::factory(),
            'nivel' => MetaAdsNivel::Anuncio,
            'objeto_id' => (string) fake()->unique()->numerify('############'),
            'referencia' => fake()->dateTimeBetween('-30 days', 'now'),
            'investimento_centavos' => $investimento,
            'impressoes' => $impressoes,
            'cliques' => $cliques,
            'cliques_link' => (int) round($cliques * 0.8),
            'alcance' => (int) round($impressoes * 0.7),
            'cpc_centavos' => $cliques > 0 ? (int) round($investimento / $cliques) : null,
            'cpm_centavos' => (int) round($investimento / max($impressoes, 1) * 1000),
            'custo_por_resultado_centavos' => $resultados > 0 ? (int) round($investimento / $resultados) : null,
            'ctr' => round($cliques / max($impressoes, 1) * 100, 4),
            'frequencia' => fake()->randomFloat(2, 1, 4),
            'resultados' => $resultados,
            'acoes' => [['action_type' => 'lead', 'value' => (string) $resultados]],
            'bruto' => [],
        ];
    }

    public function paraAnuncio(MetaAdsAnuncio $anuncio): static
    {
        return $this->state(fn (): array => [
            'meta_ads_conta_id' => $anuncio->meta_ads_conta_id,
            'objeto_id' => $anuncio->meta_ad_id,
            'nivel' => MetaAdsNivel::Anuncio,
        ]);
    }

    public function noDia(\DateTimeInterface|string $dia): static
    {
        return $this->state(fn (): array => ['referencia' => $dia]);
    }
}
