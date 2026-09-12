<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use App\Models\Setor;
use App\Models\StatusComercial;
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
        $localidade = $this->ensureCaxiasDoSul();

        return [
            'nome' => fake()->unique()->company(),
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'setor_id' => Setor::factory(),
            'porte' => fake()->numberBetween(10, 400).' funcionários',
            'cidade' => $localidade['cidade'],
            'uf_id' => $localidade['uf_id'],
            'municipio_id' => $localidade['municipio_id'],
            'responsavel_user_id' => User::factory(),
            'status_conflito_id' => StatusConflito::factory(),
            'status_comercial_id' => StatusComercial::factory(),
            'conflito_texto' => null,
            'conflito_verificado_em' => null,
        ];
    }

    /**
     * @return array{cidade: string, uf_id: int, municipio_id: int}
     */
    private function ensureCaxiasDoSul(): array
    {
        IbgeEstado::query()->updateOrCreate(
            ['id' => 43],
            [
                'txt_uf' => 'Rio Grande do Sul',
                'txt_sigla_uf' => 'RS',
            ],
        );

        IbgeMunicipio::query()->updateOrCreate(
            ['id' => 4305108],
            [
                'txt_nome_municipios' => 'Caxias do Sul',
                'cod_municipio_6dig' => 430510,
                'estado_id' => 43,
            ],
        );

        $uf = Uf::query()->firstOrCreate(
            ['sigla' => 'RS'],
            ['nome' => 'Rio Grande do Sul', 'ordem' => 21],
        );

        return [
            'cidade' => 'Caxias do Sul',
            'uf_id' => $uf->id,
            'municipio_id' => 4305108,
        ];
    }
}
