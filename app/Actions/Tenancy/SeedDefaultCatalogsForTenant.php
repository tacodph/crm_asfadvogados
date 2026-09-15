<?php

namespace App\Actions\Tenancy;

use App\Models\CanalContato;
use App\Models\FinalidadeConsentimento;
use App\Models\Setor;
use App\Models\StatusAtendimento;
use App\Models\StatusComercial;
use App\Models\StatusConflito;
use App\Models\StatusConsentimento;
use App\Models\StatusQualificacao;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;

/**
 * Seeds a new tenant's default catalog rows (Setor, CanalContato,
 * StatusConsentimento, FinalidadeConsentimento, StatusConflito,
 * StatusComercial). Shared by the self-service signup flow and
 * `database/seeders/DominioCrmSeeder`, so both dev seeding and real tenant
 * provisioning stay in sync.
 *
 * `Uf` and `TipoPessoa` are not seeded here — they are global catalogs,
 * shared across every tenant.
 */
class SeedDefaultCatalogsForTenant
{
    public function __invoke(Tenant $tenant): void
    {
        app(CurrentTenant::class)->runAs($tenant, function () use ($tenant): void {
            foreach ($this->setores() as $ordem => $setor) {
                Setor::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $setor['slug']],
                    [...$setor, 'ordem' => $ordem + 1],
                );
            }

            foreach ($this->canais() as $ordem => $canal) {
                CanalContato::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $canal['slug']],
                    [...$canal, 'ordem' => $ordem + 1],
                );
            }

            foreach ($this->statusConsentimentos() as $ordem => $status) {
                StatusConsentimento::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $status['slug']],
                    [...$status, 'ordem' => $ordem + 1],
                );
            }

            foreach ($this->finalidades() as $ordem => $finalidade) {
                FinalidadeConsentimento::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $finalidade['slug']],
                    [...$finalidade, 'ordem' => $ordem + 1],
                );
            }

            foreach ($this->statusConflitos() as $ordem => $status) {
                StatusConflito::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $status['slug']],
                    [...$status, 'ordem' => $ordem + 1],
                );
            }

            foreach ($this->statusComerciais() as $ordem => $status) {
                StatusComercial::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $status['slug']],
                    [...$status, 'ordem' => $ordem + 1],
                );
            }

            foreach ($this->statusAtendimentos() as $ordem => $status) {
                StatusAtendimento::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $status['slug']],
                    [...$status, 'ordem' => $ordem + 1],
                );
            }

            foreach ($this->statusQualificacoes() as $ordem => $status) {
                StatusQualificacao::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $status['slug']],
                    [...$status, 'ordem' => $ordem + 1],
                );
            }
        });
    }

    /**
     * @return list<array{slug: string, nome: string}>
     */
    public function setores(): array
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
    public function canais(): array
    {
        return [
            ['slug' => 'whatsapp', 'nome' => 'WhatsApp', 'cor' => '#14574F'],
            ['slug' => 'site', 'nome' => 'Site', 'cor' => '#8C6F3F'],
            ['slug' => 'indicacao', 'nome' => 'Indicação', 'cor' => '#3F5E8C'],
            ['slug' => 'instagram', 'nome' => 'Instagram', 'cor' => '#8C4A6B'],
            ['slug' => 'google-maps', 'nome' => 'Google Maps', 'cor' => '#7A6E3F'],
            ['slug' => 'e-mail', 'nome' => 'E-mail', 'cor' => '#3C4450'],
            ['slug' => 'meta-ads', 'nome' => 'Meta Ads', 'cor' => '#1877F2'],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string, cor_fundo: string, cor_texto: string, visivel_cadastro: bool}>
     */
    public function statusConsentimentos(): array
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
    public function finalidades(): array
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
    public function statusConflitos(): array
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

    /**
     * @return list<array{slug: string, nome: string, descricao: string, cor_fundo: string, cor_texto: string}>
     */
    public function statusComerciais(): array
    {
        return [
            [
                'slug' => 'novo',
                'nome' => 'Novo',
                'descricao' => 'Cadastro recente, ainda sem triagem comercial.',
                'cor_fundo' => '#F4F2EC',
                'cor_texto' => '#77808E',
            ],
            [
                'slug' => 'em-analise',
                'nome' => 'Em análise',
                'descricao' => 'Em triagem interna (perfil, conflito, interesse e fit).',
                'cor_fundo' => '#FBF1DF',
                'cor_texto' => '#8C6F3F',
            ],
            [
                'slug' => 'qualificado',
                'nome' => 'Qualificado',
                'descricao' => 'Apto a avançar no funil comercial com responsável atribuído.',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
            ],
            [
                'slug' => 'em-negociacao',
                'nome' => 'Em negociação',
                'descricao' => 'Há negociação ativa em andamento com este cadastro.',
                'cor_fundo' => '#E8EEF6',
                'cor_texto' => '#3F5E8C',
            ],
            [
                'slug' => 'cliente-efetivado',
                'nome' => 'Cliente efetivado',
                'descricao' => 'Contrato assinado ou relacionamento de cliente consolidado.',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
            ],
            [
                'slug' => 'inativo',
                'nome' => 'Inativo',
                'descricao' => 'Sem movimento recente; pode ser reativado se houver novo contato.',
                'cor_fundo' => '#F4F2EC',
                'cor_texto' => '#77808E',
            ],
            [
                'slug' => 'desqualificado',
                'nome' => 'Desqualificado',
                'descricao' => 'Não encaixa no perfil de atuação do escritório neste momento.',
                'cor_fundo' => '#F8ECE9',
                'cor_texto' => '#9B3B2F',
            ],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string, cor_fundo: string, cor_texto: string}>
     */
    public function statusAtendimentos(): array
    {
        return [
            [
                'slug' => 'analise-pendente',
                'nome' => 'Análise Pendente',
                'cor_fundo' => '#FBF1DF',
                'cor_texto' => '#8C6F3F',
            ],
            [
                'slug' => 'atendimento-em-andamento',
                'nome' => 'Atendimento em andamento',
                'cor_fundo' => '#E8EEF6',
                'cor_texto' => '#3F5E8C',
            ],
            [
                'slug' => 'em-negociacao',
                'nome' => 'Em Negociação',
                'cor_fundo' => '#EDE9FE',
                'cor_texto' => '#6D28D9',
            ],
            [
                'slug' => 'retorno-a-agendar',
                'nome' => 'Retorno a agendar',
                'cor_fundo' => '#DBEAFE',
                'cor_texto' => '#1D4ED8',
            ],
            [
                'slug' => 'contrato-fechado',
                'nome' => 'Contrato Fechado',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
            ],
            [
                'slug' => 'encerrado',
                'nome' => 'Encerrado',
                'cor_fundo' => '#F4F2EC',
                'cor_texto' => '#77808E',
            ],
        ];
    }

    /**
     * @return list<array{slug: string, nome: string, cor_fundo: string, cor_texto: string}>
     */
    public function statusQualificacoes(): array
    {
        return [
            [
                'slug' => 'aguardando-retorno-1-contato',
                'nome' => 'Aguardando Retorno do 1º contato',
                'cor_fundo' => '#DBEAFE',
                'cor_texto' => '#1D4ED8',
            ],
            [
                'slug' => 'qualificado',
                'nome' => 'Qualificado',
                'cor_fundo' => '#E7F0EE',
                'cor_texto' => '#14574F',
            ],
            [
                'slug' => 'desqualificado',
                'nome' => 'Desqualificado',
                'cor_fundo' => '#F8ECE9',
                'cor_texto' => '#9B3B2F',
            ],
        ];
    }
}
