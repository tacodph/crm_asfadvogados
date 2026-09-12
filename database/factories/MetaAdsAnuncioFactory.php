<?php

namespace Database\Factories;

use App\Enums\MetaAdsStatus;
use App\Models\MetaAdsAnuncio;
use App\Models\MetaAdsConjunto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaAdsAnuncio>
 */
class MetaAdsAnuncioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_ads_conjunto_id' => MetaAdsConjunto::factory(),
            // Herda campanha e conta do conjunto (id já resolvido).
            'meta_ads_campanha_id' => fn (array $a): int => MetaAdsConjunto::query()
                ->whereKey($a['meta_ads_conjunto_id'])->firstOrFail()->meta_ads_campanha_id,
            'meta_ads_conta_id' => fn (array $a): int => MetaAdsConjunto::query()
                ->whereKey($a['meta_ads_conjunto_id'])->firstOrFail()->meta_ads_conta_id,
            'meta_ad_id' => (string) fake()->unique()->numerify('############'),
            'nome' => 'Anúncio '.fake()->randomElement(['Vídeo', 'Carrossel', 'Imagem única', 'Coleção']),
            'status' => MetaAdsStatus::Ativo,
            'effective_status' => 'ACTIVE',
            'criativo_resumo' => [
                'titulo' => fake()->sentence(4),
                'corpo' => fake()->sentence(10),
                'thumb_url' => null,
                'cta' => 'LEARN_MORE',
            ],
            'bruto' => [],
            'sincronizado_em' => now(),
            'arquivado_em' => null,
        ];
    }

    public function pausado(): static
    {
        return $this->state(fn (): array => [
            'status' => MetaAdsStatus::Pausado,
            'effective_status' => 'PAUSED',
        ]);
    }

    public function arquivado(): static
    {
        return $this->state(fn (): array => ['arquivado_em' => now()]);
    }
}
