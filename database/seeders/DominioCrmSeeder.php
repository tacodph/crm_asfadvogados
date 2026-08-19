<?php

namespace Database\Seeders;

use App\Models\CanalContato;
use App\Models\FinalidadeConsentimento;
use App\Models\Setor;
use App\Models\StatusConflito;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\Uf;
use Illuminate\Database\Seeder;

class DominioCrmSeeder extends Seeder
{
    /**
     * Seed CRM domain catalogs used by contacts and companies.
     */
    public function run(): void
    {
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

        foreach ($this->setores() as $ordem => $setor) {
            Setor::query()->updateOrCreate(
                ['slug' => $setor['slug']],
                [...$setor, 'ordem' => $ordem + 1],
            );
        }

        foreach ($this->canais() as $ordem => $canal) {
            CanalContato::query()->updateOrCreate(
                ['slug' => $canal['slug']],
                [...$canal, 'ordem' => $ordem + 1],
            );
        }

        foreach ($this->statusConsentimentos() as $ordem => $status) {
            StatusConsentimento::query()->updateOrCreate(
                ['slug' => $status['slug']],
                [...$status, 'ordem' => $ordem + 1],
            );
        }

        foreach ($this->finalidades() as $ordem => $finalidade) {
            FinalidadeConsentimento::query()->updateOrCreate(
                ['slug' => $finalidade['slug']],
                [...$finalidade, 'ordem' => $ordem + 1],
            );
        }

        foreach ($this->statusConflitos() as $ordem => $status) {
            StatusConflito::query()->updateOrCreate(
                ['slug' => $status['slug']],
                [...$status, 'ordem' => $ordem + 1],
            );
        }
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

    /**
     * @return list<array{slug: string, nome: string}>
     */
    private function setores(): array
    {
        return [
            ['slug' => 'industria-metalurgica', 'nome' => 'Indústria metalúrgica'],
            ['slug' => 'software-b2b', 'nome' => 'Software B2B'],
            ['slug' => 'alimentos', 'nome' => 'Alimentos'],
            ['slug' => 'agronegocio', 'nome' => 'Agronegócio'],
            ['slug' => 'construcao-civil', 'nome' => 'Construção civil'],
            ['slug' => 'terceiro-setor', 'nome' => 'Terceiro setor'],
            ['slug' => 'textil', 'nome' => 'Têxtil'],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string, cor: string}>
     */
    private function canais(): array
    {
        return [
            ['slug' => 'whatsapp', 'nome' => 'WhatsApp', 'cor' => '#14574F'],
            ['slug' => 'site', 'nome' => 'Site', 'cor' => '#8C6F3F'],
            ['slug' => 'indicacao', 'nome' => 'Indicação', 'cor' => '#3F5E8C'],
            ['slug' => 'instagram', 'nome' => 'Instagram', 'cor' => '#8C4A6B'],
            ['slug' => 'google-maps', 'nome' => 'Google Maps', 'cor' => '#7A6E3F'],
            ['slug' => 'e-mail', 'nome' => 'E-mail', 'cor' => '#3C4450'],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string, cor_fundo: string, cor_texto: string, visivel_cadastro: bool}>
     */
    private function statusConsentimentos(): array
    {
        return [
            [
                'slug' => 'opt-in-registrado',
                'nome' => 'opt-in registrado',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
                'visivel_cadastro' => true,
            ],
            [
                'slug' => 'pendente',
                'nome' => 'pendente',
                'cor_fundo' => '#FBF1DF',
                'cor_texto' => '#8C6F3F',
                'visivel_cadastro' => true,
            ],
            [
                'slug' => 'revogado',
                'nome' => 'revogado',
                'cor_fundo' => '#F8ECE9',
                'cor_texto' => '#9B3B2F',
                'visivel_cadastro' => true,
            ],
            [
                'slug' => 'vigente',
                'nome' => 'vigente',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
                'visivel_cadastro' => false,
            ],
            [
                'slug' => 'nao-concedido',
                'nome' => 'não concedido',
                'cor_fundo' => '#F4F2EC',
                'cor_texto' => '#77808E',
                'visivel_cadastro' => false,
            ],
            [
                'slug' => 'suspenso',
                'nome' => 'suspenso',
                'cor_fundo' => '#F8ECE9',
                'cor_texto' => '#9B3B2F',
                'visivel_cadastro' => false,
            ],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string}>
     */
    private function finalidades(): array
    {
        return [
            ['slug' => 'contato-comercial', 'nome' => 'Contato comercial (pré-contratual)'],
            ['slug' => 'mensagens', 'nome' => 'Mensagens pelo canal preferido'],
            ['slug' => 'newsletter', 'nome' => 'Conteúdo informativo / newsletter'],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string, cor_fundo: string, cor_texto: string, cor_fundo_detalhe: string, cor_borda_detalhe: string}>
     */
    private function statusConflitos(): array
    {
        return [
            [
                'slug' => 'verificado',
                'nome' => 'verificado',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
                'cor_fundo_detalhe' => '#F1F6F4',
                'cor_borda_detalhe' => '#CCE0DA',
            ],
            [
                'slug' => 'pendente',
                'nome' => 'verificação pendente',
                'cor_fundo' => '#FBF1DF',
                'cor_texto' => '#8C6F3F',
                'cor_fundo_detalhe' => '#FBF7EF',
                'cor_borda_detalhe' => '#E2D3B6',
            ],
        ];
    }
}
