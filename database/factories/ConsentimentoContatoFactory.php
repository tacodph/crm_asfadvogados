<?php

namespace Database\Factories;

use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\FinalidadeConsentimento;
use App\Models\StatusConsentimento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentimentoContato>
 */
class ConsentimentoContatoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contato_id' => Contato::factory(),
            'finalidade_consentimento_id' => FinalidadeConsentimento::factory(),
            'status_consentimento_id' => StatusConsentimento::factory(),
            'concedido_em' => null,
            'revogado_em' => null,
        ];
    }
}
