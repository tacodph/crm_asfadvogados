<?php

namespace Database\Seeders;

use App\Actions\Tenancy\SeedDefaultCatalogsForTenant;
use App\Models\Role;
use App\Models\TipoPessoa;
use App\Models\Uf;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;

class DominioCrmSeeder extends Seeder
{
    use SeedsForDevTenant;

    /**
     * Seed CRM domain catalogs used by contacts and companies: global
     * reference data (Uf, TipoPessoa, Role) plus the dev tenant's default
     * per-tenant catalogs (Setor, CanalContato, StatusConsentimento,
     * FinalidadeConsentimento, StatusConflito, StatusComercial).
     */
    public function run(): void
    {
        Role::ensureDefaults();

        foreach ($this->ufs() as $ordem => $uf) {
            Uf::query()->updateOrCreate(
                ['sigla' => $uf['sigla']],
                ['nome' => $uf['nome'], 'ordem' => $ordem + 1],
            );
        }

        foreach ($this->tiposPessoa() as $ordem => $tipo) {
            TipoPessoa::query()->updateOrCreate(
                ['slug' => $tipo['slug']],
                [...$tipo, 'ordem' => $ordem + 1],
            );
        }

        app(SeedDefaultCatalogsForTenant::class)($this->devTenant());
    }

    /**
     * @return list<array{sigla: string, nome: string}>
     */
    private function ufs(): array
    {
        return [
            ['sigla' => 'AC', 'nome' => 'Acre'],
            ['sigla' => 'AL', 'nome' => 'Alagoas'],
            ['sigla' => 'AP', 'nome' => 'Amapá'],
            ['sigla' => 'AM', 'nome' => 'Amazonas'],
            ['sigla' => 'BA', 'nome' => 'Bahia'],
            ['sigla' => 'CE', 'nome' => 'Ceará'],
            ['sigla' => 'DF', 'nome' => 'Distrito Federal'],
            ['sigla' => 'ES', 'nome' => 'Espírito Santo'],
            ['sigla' => 'GO', 'nome' => 'Goiás'],
            ['sigla' => 'MA', 'nome' => 'Maranhão'],
            ['sigla' => 'MT', 'nome' => 'Mato Grosso'],
            ['sigla' => 'MS', 'nome' => 'Mato Grosso do Sul'],
            ['sigla' => 'MG', 'nome' => 'Minas Gerais'],
            ['sigla' => 'PA', 'nome' => 'Pará'],
            ['sigla' => 'PB', 'nome' => 'Paraíba'],
            ['sigla' => 'PR', 'nome' => 'Paraná'],
            ['sigla' => 'PE', 'nome' => 'Pernambuco'],
            ['sigla' => 'PI', 'nome' => 'Piauí'],
            ['sigla' => 'RJ', 'nome' => 'Rio de Janeiro'],
            ['sigla' => 'RN', 'nome' => 'Rio Grande do Norte'],
            ['sigla' => 'RS', 'nome' => 'Rio Grande do Sul'],
            ['sigla' => 'RO', 'nome' => 'Rondônia'],
            ['sigla' => 'RR', 'nome' => 'Roraima'],
            ['sigla' => 'SC', 'nome' => 'Santa Catarina'],
            ['sigla' => 'SP', 'nome' => 'São Paulo'],
            ['sigla' => 'SE', 'nome' => 'Sergipe'],
            ['sigla' => 'TO', 'nome' => 'Tocantins'],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string}>
     */
    private function tiposPessoa(): array
    {
        return [
            ['slug' => 'pf', 'nome' => 'Pessoa física'],
            ['slug' => 'pj', 'nome' => 'Pessoa jurídica'],
        ];
    }
}
