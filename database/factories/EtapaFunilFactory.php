<?php

namespace Database\Factories;

use App\Enums\EtapaFunilResultado;
use App\Models\EtapaFunil;
use App\Models\Funil;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtapaFunil>
 */
class EtapaFunilFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funil_id' => Funil::factory(),
            'nome' => fake()->words(2, true),
            'sla' => fake()->randomElement(['15min', '4h', '1d', '5d']),
            'campos' => ['origem', 'consentimento'],
            'exige_motivo' => false,
            'resultado' => EtapaFunilResultado::Aberta,
            'ordem' => 1,
            'cor_fundo' => '#333A45',
            'cor_texto' => '#FBF9F4',
            'cor_suave' => 'rgba(251,249,244,0.9)',
        ];
    }

    public function ganho(): static
    {
        return $this->state(fn (): array => [
            'resultado' => EtapaFunilResultado::Ganho,
            'nome' => 'Fechamento',
            'cor_fundo' => '#0F4A43',
        ]);
    }

    public function perdido(): static
    {
        return $this->state(fn (): array => [
            'resultado' => EtapaFunilResultado::Perdido,
            'nome' => 'Atendimentos encerrados',
            'exige_motivo' => true,
            'cor_fundo' => '#9B3B2F',
            'cor_suave' => 'rgba(251,249,244,0.92)',
        ]);
    }
}
