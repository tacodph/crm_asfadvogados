<?php

namespace Tests\Feature;

use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IbgeMunicipiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_municipios_for_estado(): void
    {
        $user = User::factory()->create();

        IbgeEstado::query()->updateOrCreate(
            ['id' => 43],
            ['txt_uf' => 'Rio Grande do Sul', 'txt_sigla_uf' => 'RS'],
        );

        IbgeMunicipio::query()->updateOrCreate(
            ['id' => 4305108],
            [
                'txt_nome_municipios' => 'Caxias do Sul',
                'cod_municipio_6dig' => 430510,
                'estado_id' => 43,
            ],
        );

        IbgeMunicipio::query()->updateOrCreate(
            ['id' => 4314902],
            [
                'txt_nome_municipios' => 'Porto Alegre',
                'cod_municipio_6dig' => 431490,
                'estado_id' => 43,
            ],
        );

        $this->actingAs($user)
            ->getJson($this->tenantUrl('ibge.municipios.index').'?estado_id=43')
            ->assertOk()
            ->assertJsonCount(2, 'municipios')
            ->assertJsonFragment(['nome' => 'Caxias do Sul'])
            ->assertJsonFragment(['nome' => 'Porto Alegre']);
    }

    public function test_lists_municipios_by_uf_sigla(): void
    {
        $user = User::factory()->create();

        IbgeEstado::query()->updateOrCreate(
            ['id' => 43],
            ['txt_uf' => 'Rio Grande do Sul', 'txt_sigla_uf' => 'RS'],
        );

        IbgeMunicipio::query()->updateOrCreate(
            ['id' => 4305108],
            [
                'txt_nome_municipios' => 'Caxias do Sul',
                'cod_municipio_6dig' => 430510,
                'estado_id' => 43,
            ],
        );

        $this->actingAs($user)
            ->getJson($this->tenantUrl('ibge.municipios.index').'?uf=RS')
            ->assertOk()
            ->assertJsonFragment(['id' => 4305108, 'nome' => 'Caxias do Sul']);
    }
}
