<?php

namespace Database\Seeders;

use App\Enums\StatusProposta;
use App\Models\Negociacao;
use App\Models\Proposta;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PropostaSeeder extends Seeder
{
    use SeedsForDevTenant;

    /**
     * Seed prototype proposals linked to existing negotiations, contacts and companies.
     */
    public function run(): void
    {
        app(CurrentTenant::class)->runAs($this->devTenant(), function (): void {
            $hoje = Carbon::parse('2026-08-17')->startOfDay();

            foreach ($this->linhas() as $linha) {
                $negociacao = Negociacao::query()
                    ->with(['contato', 'empresa', 'responsavel'])
                    ->where('assunto', $linha['assunto'])
                    ->first();

                if ($negociacao === null) {
                    continue;
                }

                Proposta::query()->updateOrCreate(
                    [
                        'codigo' => $linha['codigo'],
                        'versao' => $linha['versao'],
                    ],
                    [
                        'negociacao_id' => $negociacao->id,
                        'contato_id' => $negociacao->contato_id,
                        'empresa_id' => $negociacao->empresa_id,
                        'autor_user_id' => $negociacao->responsavel_user_id,
                        'titulo' => $negociacao->assunto,
                        'escopo' => $linha['escopo'],
                        'honorarios' => $linha['honorarios'],
                        'parcelamento' => $linha['parcelamento'],
                        'indice_reajuste' => $linha['indice'],
                        'modelo_origem' => $linha['modelo'],
                        'status' => $linha['status'],
                        'valido_ate' => $hoje->copy()->addDays($linha['validade_off'])->toDateString(),
                        'enviado_em' => $hoje->copy()->subDays($linha['enviado_off'])->setTime(10, 30),
                        'aceito_em' => $linha['status'] === StatusProposta::Aceita
                            ? $hoje->copy()->subDays(1)->setTime(16, 0)
                            : null,
                        'clausulas' => $this->clausulasPadrao($negociacao->assunto, $linha['honorarios']),
                    ],
                );
            }
        });
    }

    /**
     * @return list<array{
     *     codigo: string,
     *     versao: int,
     *     assunto: string,
     *     honorarios: int,
     *     status: StatusProposta,
     *     validade_off: int,
     *     enviado_off: int,
     *     parcelamento: string,
     *     indice: string,
     *     modelo: string,
     *     escopo: string
     * }>
     */
    private function linhas(): array
    {
        return [
            [
                'codigo' => 'PR-2026-124',
                'versao' => 3,
                'assunto' => 'Due diligence contratual',
                'honorarios' => 210000,
                'status' => StatusProposta::EmNegociacao,
                'validade_off' => 4,
                'enviado_off' => 2,
                'parcelamento' => '40% na assinatura · 60% em 2 parcelas',
                'indice' => 'IPCA',
                'modelo' => 'Due diligence imobiliária v2026.1',
                'escopo' => 'Análise de contratos de empreitada, garantias e riscos trabalhistas vinculados às obras em andamento da Construtora Piedade.',
            ],
            [
                'codigo' => 'PR-2026-121',
                'versao' => 1,
                'assunto' => 'Reestruturação societária',
                'honorarios' => 120000,
                'status' => StatusProposta::Enviada,
                'validade_off' => 9,
                'enviado_off' => 6,
                'parcelamento' => '3 parcelas mensais',
                'indice' => 'IPCA',
                'modelo' => 'Societário consultivo v2025.4',
                'escopo' => 'Desenho da reorganização societária do Grupo Aldeia Alimentos, com minuta de atos societários e parecer de governança.',
            ],
            [
                'codigo' => 'PR-2026-118',
                'versao' => 2,
                'assunto' => 'Investigação social reprovada',
                'honorarios' => 6500,
                'status' => StatusProposta::Enviada,
                'validade_off' => 1,
                'enviado_off' => 3,
                'parcelamento' => 'À vista ou 3 parcelas',
                'indice' => '—',
                'modelo' => 'Concursos — recurso administrativo',
                'escopo' => 'Impugnação da reprovação em investigação social, com pedido de reconsideração e, se necessário, mandado de segurança.',
            ],
            [
                'codigo' => 'PR-2026-112',
                'versao' => 2,
                'assunto' => 'Assessoria consultiva anual',
                'honorarios' => 96000,
                'status' => StatusProposta::Aceita,
                'validade_off' => 365,
                'enviado_off' => 9,
                'parcelamento' => '12 parcelas mensais',
                'indice' => 'IPCA anual',
                'modelo' => 'Retainer consultivo B2B',
                'escopo' => 'Assessoria consultiva anual em demandas trabalhistas e contratuais recorrentes da Têxtil Guaporé Ltda.',
            ],
            [
                'codigo' => 'PR-2026-104',
                'versao' => 1,
                'assunto' => 'Consultivo tributário',
                'honorarios' => 64000,
                'status' => StatusProposta::Expirada,
                'validade_off' => -6,
                'enviado_off' => 21,
                'parcelamento' => '2 parcelas',
                'indice' => 'IPCA',
                'modelo' => 'Tributário consultivo',
                'escopo' => 'Diagnóstico tributário da Cooperativa Vale Verde e plano de adequação para o exercício corrente.',
            ],
            [
                'codigo' => 'PR-2026-109',
                'versao' => 1,
                'assunto' => 'Governança do terceiro setor',
                'honorarios' => 25000,
                'status' => StatusProposta::EmNegociacao,
                'validade_off' => 11,
                'enviado_off' => 4,
                'parcelamento' => '50% na assinatura · 50% na entrega',
                'indice' => 'IPCA',
                'modelo' => 'Terceiro setor — governança',
                'escopo' => 'Revisão de estatuto, política de conflitos e rotinas de prestação de contas do Instituto Farrapo.',
            ],
            [
                'codigo' => 'PR-2026-115',
                'versao' => 1,
                'assunto' => 'Nomeação tardia — MS',
                'honorarios' => 4500,
                'status' => StatusProposta::Enviada,
                'validade_off' => 8,
                'enviado_off' => 2,
                'parcelamento' => 'À vista',
                'indice' => '—',
                'modelo' => 'Concursos — mandado de segurança',
                'escopo' => 'Ação para garantir nomeação tardia após aprovação em concurso da Prefeitura de Canoas.',
            ],
            [
                'codigo' => 'PR-2026-101',
                'versao' => 1,
                'assunto' => 'Posse suspensa — liminar',
                'honorarios' => 5200,
                'status' => StatusProposta::Aceita,
                'validade_off' => 180,
                'enviado_off' => 14,
                'parcelamento' => '2 parcelas',
                'indice' => '—',
                'modelo' => 'Concursos — liminar de posse',
                'escopo' => 'Pedido liminar para viabilizar a posse suspensa no TJ/RS, com acompanhamento até a decisão de mérito.',
            ],
        ];
    }

    /**
     * @return list<array{titulo: string, texto: string}>
     */
    private function clausulasPadrao(string $assunto, int $honorarios): array
    {
        $valor = 'R$ '.number_format($honorarios, 0, ',', '.');

        return [
            [
                'titulo' => 'Objeto',
                'texto' => 'Prestação de serviços advocatícios relativos a “'.$assunto.'”, em caráter consultivo/contencioso conforme escopo desta proposta, sem promessa de resultado.',
            ],
            [
                'titulo' => 'Honorários',
                'texto' => 'Honorários no valor de '.$valor.', observados os patamares mínimos da tabela da OAB/seccional. Valores promocionais ou abaixo da tabela não são admitidos.',
            ],
            [
                'titulo' => 'Sigilo e LGPD',
                'texto' => 'O tratamento de dados pessoais limita-se à finalidade pré-contratual e contratual. Conteúdo jurídico do caso permanece fora deste CRM comercial.',
            ],
            [
                'titulo' => 'Validade',
                'texto' => 'Esta versão é imutável. Qualquer alteração gera nova versão (v+1) com registro de autor, data e histórico de mudanças.',
            ],
        ];
    }
}
