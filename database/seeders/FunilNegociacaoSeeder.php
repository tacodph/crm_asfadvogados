<?php

namespace Database\Seeders;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FunilNegociacaoSeeder extends Seeder
{
    /**
     * Seed funnels and prototype negotiations.
     */
    public function run(): void
    {
        $this->call(EmpresaContatoSeeder::class);

        User::query()->updateOrCreate(
            ['email' => 'diego.alencar@asfadvogados.adv.br'],
            ['name' => 'Diego Alencar', 'password' => 'password'],
        );

        $funis = $this->funis();
        $this->negociacoes($funis);
    }

    /**
     * @return array<string, Funil>
     */
    private function funis(): array
    {
        $paleta = ['#333A45', '#31496E', '#41503A', '#6B5330', '#6B3852', '#414A57', '#5A5230'];

        $definicoes = [
            [
                'slug' => 'b2b',
                'nome' => 'B2B consultivo',
                'distribuicao' => 'round robin por especialidade',
                'etapas' => [
                    ['nome' => 'Prospecção / Inbound', 'sla' => '4h', 'campos' => ['origem', 'consentimento'], 'exige' => false],
                    ['nome' => 'Diagnóstico', 'sla' => '5d', 'campos' => ['porte', 'dor mapeada', 'conflito verificado'], 'exige' => true],
                    ['nome' => 'Apresentação da solução', 'sla' => '7d', 'campos' => ['escopo', 'honorários'], 'exige' => true],
                    ['nome' => 'Negociação', 'sla' => '10d', 'campos' => ['minuta enviada', 'objeção'], 'exige' => true],
                    ['nome' => 'Fechamento', 'sla' => '—', 'campos' => ['contrato assinado', 'procuração'], 'exige' => false],
                ],
            ],
            [
                'slug' => 'pf',
                'nome' => 'Concursos (PF, volume)',
                'distribuicao' => 'round robin simples',
                'etapas' => [
                    ['nome' => 'Novo lead', 'sla' => '15min', 'campos' => ['telefone válido', 'consentimento'], 'exige' => false],
                    ['nome' => 'Triagem', 'sla' => '1d', 'campos' => ['concurso/banca', 'fase'], 'exige' => true],
                    ['nome' => 'Envio da oferta', 'sla' => '2d', 'campos' => ['oferta registrada'], 'exige' => true],
                    ['nome' => 'Objeções', 'sla' => '3d', 'campos' => ['objeção classificada'], 'exige' => true],
                    ['nome' => 'Fechamento', 'sla' => '—', 'campos' => ['contrato assinado'], 'exige' => false],
                ],
            ],
        ];

        $funis = [];

        foreach ($definicoes as $ordem => $definicao) {
            $funil = Funil::query()->updateOrCreate(
                ['slug' => $definicao['slug']],
                [
                    'nome' => $definicao['nome'],
                    'distribuicao' => $definicao['distribuicao'],
                    'ordem' => $ordem + 1,
                ],
            );

            foreach ($definicao['etapas'] as $i => $etapa) {
                $ultima = $i === count($definicao['etapas']) - 1;

                EtapaFunil::query()->updateOrCreate(
                    [
                        'funil_id' => $funil->id,
                        'ordem' => $i + 1,
                    ],
                    [
                        'nome' => $etapa['nome'],
                        'sla' => $etapa['sla'],
                        'campos' => $etapa['campos'],
                        'exige_motivo' => $etapa['exige'],
                        'cor_fundo' => $ultima ? '#0F4A43' : $paleta[$i % count($paleta)],
                        'cor_texto' => '#FBF9F4',
                        'cor_suave' => 'rgba(251,249,244,0.9)',
                    ],
                );
            }

            $funis[$funil->slug] = $funil->load('etapas');
        }

        return $funis;
    }

    /**
     * @param  array<string, Funil>  $funis
     */
    private function negociacoes(array $funis): void
    {
        $hoje = Carbon::parse('2026-08-17')->startOfDay();

        $linhas = [
            ['funil' => 'b2b', 'etapa' => 1, 'email' => 'renata.bonfanti@verano.ind.br', 'canal' => 'indicacao', 'assunto' => 'Compliance trabalhista', 'valor' => 48000, 'resp' => 'Camila Moraes', 'dias' => 2, 'previsao' => '2026-09-05', 'tarefa' => 'Reunião de diagnóstico presencial', 'off' => 0, 'hora' => '15:00'],
            ['funil' => 'b2b', 'etapa' => 1, 'email' => 'marina@nexatech.com.br', 'canal' => 'site', 'assunto' => 'LGPD e contratos SaaS', 'valor' => 36000, 'resp' => 'Rafael Prado', 'dias' => 1, 'previsao' => '2026-09-12', 'tarefa' => 'Ligar para qualificar', 'off' => 0, 'hora' => '16:00'],
            ['funil' => 'b2b', 'etapa' => 2, 'email' => 'sergio@grupoaldeia.com.br', 'canal' => 'indicacao', 'assunto' => 'Reestruturação societária', 'valor' => 120000, 'resp' => 'Camila Moraes', 'dias' => 6, 'previsao' => '2026-09-30', 'tarefa' => 'Enviar escopo preliminar', 'off' => 1, 'hora' => '09:30'],
            ['funil' => 'b2b', 'etapa' => 2, 'email' => 'presidencia@valeverde.coop.br', 'canal' => 'google-maps', 'assunto' => 'Consultivo tributário', 'valor' => 64000, 'resp' => 'Rafael Prado', 'dias' => 11, 'previsao' => null, 'tarefa' => 'Retomar contato e coletar consentimento', 'off' => -6, 'hora' => '10:00'],
            ['funil' => 'b2b', 'etapa' => 3, 'email' => 'fabiana@construtorapiedade.com.br', 'canal' => 'indicacao', 'assunto' => 'Due diligence contratual', 'valor' => 210000, 'resp' => 'Camila Moraes', 'dias' => 4, 'previsao' => '2026-08-22', 'tarefa' => 'Apresentar proposta v3', 'off' => 1, 'hora' => '10:00'],
            ['funil' => 'b2b', 'etapa' => 4, 'email' => 'clara@institutofarrapo.org.br', 'canal' => 'site', 'assunto' => 'Governança do terceiro setor', 'valor' => 25000, 'resp' => 'Letícia Bonfim', 'dias' => 3, 'previsao' => '2026-08-28', 'tarefa' => 'Responder objeção de prazo', 'off' => 2, 'hora' => '11:00'],
            ['funil' => 'b2b', 'etapa' => 5, 'email' => 'ricardo@textilguapore.com.br', 'canal' => 'indicacao', 'assunto' => 'Assessoria consultiva anual', 'valor' => 96000, 'resp' => 'Camila Moraes', 'dias' => 1, 'previsao' => '2026-08-15', 'tarefa' => 'Coletar assinatura eletrônica', 'off' => 0, 'hora' => '17:00'],
            ['funil' => 'pf', 'etapa' => 1, 'email' => 'juliana.ferraz@email.com', 'canal' => 'whatsapp', 'assunto' => 'Recurso administrativo — TRF', 'valor' => 3200, 'resp' => 'Letícia Bonfim', 'dias' => 0, 'previsao' => '2026-08-18', 'tarefa' => 'Primeiro contato — SLA de 15 minutos', 'off' => 0, 'hora' => '08:30'],
            ['funil' => 'pf', 'etapa' => 1, 'email' => 'mvleal@email.com', 'canal' => 'instagram', 'assunto' => 'Eliminação em teste físico', 'valor' => 2800, 'resp' => 'Diego Alencar', 'dias' => 1, 'previsao' => '2026-08-20', 'tarefa' => 'Coletar consentimento formal', 'off' => 1, 'hora' => '09:00'],
            ['funil' => 'pf', 'etapa' => 2, 'email' => 'patricia.andrade@email.com', 'canal' => 'whatsapp', 'assunto' => 'Nomeação tardia — MS', 'valor' => 4500, 'resp' => 'Diego Alencar', 'dias' => 2, 'previsao' => '2026-08-25', 'tarefa' => 'Conferir prazo administrativo', 'off' => 3, 'hora' => '14:00'],
            ['funil' => 'pf', 'etapa' => 2, 'email' => 'e.nakamura@email.com', 'canal' => 'site', 'assunto' => 'Reserva de vagas PcD', 'valor' => 3900, 'resp' => 'Letícia Bonfim', 'dias' => 5, 'previsao' => '2026-08-27', 'tarefa' => 'Solicitar edital e laudo médico', 'off' => -2, 'hora' => '11:30'],
            ['funil' => 'pf', 'etapa' => 3, 'email' => 'simone.b@email.com', 'canal' => 'whatsapp', 'assunto' => 'Questão anulável — banca', 'valor' => 2400, 'resp' => 'Diego Alencar', 'dias' => 3, 'previsao' => '2026-08-19', 'tarefa' => 'Confirmar recebimento da oferta', 'off' => 2, 'hora' => '16:30'],
            ['funil' => 'pf', 'etapa' => 3, 'email' => 'rodrigo.tavares@email.com', 'canal' => 'indicacao', 'assunto' => 'Investigação social reprovada', 'valor' => 6500, 'resp' => 'Letícia Bonfim', 'dias' => 8, 'previsao' => '2026-08-16', 'tarefa' => 'Retomar contato antes do vencimento da proposta', 'off' => -1, 'hora' => '09:00'],
            ['funil' => 'pf', 'etapa' => 4, 'email' => 'aline.cordeiro@email.com', 'canal' => 'instagram', 'assunto' => 'Exame psicotécnico', 'valor' => 3100, 'resp' => 'Diego Alencar', 'dias' => 4, 'previsao' => null, 'tarefa' => 'Registrar revogação e encerrar contato', 'off' => 4, 'hora' => '13:00'],
            ['funil' => 'pf', 'etapa' => 5, 'email' => 'h.sales@email.com', 'canal' => 'whatsapp', 'assunto' => 'Posse suspensa — liminar', 'valor' => 5200, 'resp' => 'Letícia Bonfim', 'dias' => 1, 'previsao' => '2026-08-14', 'tarefa' => 'Assinatura de contrato', 'off' => 0, 'hora' => '15:30'],
        ];

        foreach ($linhas as $linha) {
            $contato = Contato::query()->where('email', $linha['email'])->firstOrFail();
            $funil = $funis[$linha['funil']];
            $etapa = $funil->etapas->firstWhere('ordem', $linha['etapa']);
            $responsavel = User::query()->where('name', $linha['resp'])->firstOrFail();

            $negociacao = Negociacao::query()->updateOrCreate(
                [
                    'contato_id' => $contato->id,
                    'assunto' => $linha['assunto'],
                ],
                [
                    'funil_id' => $funil->id,
                    'etapa_funil_id' => $etapa->id,
                    'empresa_id' => $contato->empresa_id,
                    'canal_contato_id' => CanalContato::query()->where('slug', $linha['canal'])->firstOrFail()->id,
                    'responsavel_user_id' => $responsavel->id,
                    'valor' => $linha['valor'],
                    'previsao_fechamento' => $linha['previsao'],
                    'etapa_desde' => $hoje->copy()->subDays($linha['dias']),
                    'proxima_tarefa' => $linha['tarefa'],
                    'proxima_tarefa_em' => $hoje->copy()->addDays($linha['off'])->toDateString(),
                    'proxima_tarefa_hora' => $linha['hora'],
                ],
            );

            $this->historicos($negociacao, $linha['assunto'], $hoje);
        }
    }

    private function historicos(Negociacao $negociacao, string $assunto, Carbon $hoje): void
    {
        $historicos = match ($assunto) {
            'Compliance trabalhista' => [
                ['tipo' => 'wa', 'titulo' => 'Mensagem recebida (WhatsApp)', 'descricao' => '"Fomos indicados pelo Dr. Otávio. Precisamos revisar nossos contratos de terceirização."', 'quando' => $hoje->copy()->setTime(9, 14), 'autor' => 'Captura automática · canal oficial'],
                ['tipo' => 'sys', 'titulo' => 'Consentimento registrado', 'descricao' => 'Base legal: consentimento (art. 7º, I, LGPD) para contato comercial por WhatsApp e e-mail.', 'quando' => $hoje->copy()->setTime(9, 15), 'autor' => 'Sistema'],
                ['tipo' => 'call', 'titulo' => 'Ligação de qualificação — 12min', 'descricao' => 'Empresa com 340 funcionários, 3 ações trabalhistas ativas. Decisor: diretora de RH.', 'quando' => $hoje->copy()->setTime(11, 2), 'autor' => 'Camila Moraes'],
                ['tipo' => 'task', 'titulo' => 'Tarefa agendada', 'descricao' => 'Reunião de diagnóstico presencial — quinta, 15h.', 'quando' => $hoje->copy()->setTime(11, 20), 'autor' => 'Camila Moraes'],
            ],
            'Investigação social reprovada' => [
                ['tipo' => 'wa', 'titulo' => 'Indicação recebida', 'descricao' => 'Cliente anterior encaminhou contato. Origem registrada no módulo de indicações.', 'quando' => $hoje->copy()->subDays(8)->setTime(10, 0), 'autor' => 'Sistema'],
                ['tipo' => 'call', 'titulo' => 'Triagem por telefone — 8min', 'descricao' => 'Reprovação em investigação social por antecedente arquivado. Documentação parcial recebida.', 'quando' => $hoje->copy()->subDays(7)->setTime(11, 0), 'autor' => 'Letícia Bonfim'],
                ['tipo' => 'mail', 'titulo' => 'Oferta enviada', 'descricao' => 'Proposta PR-2026-118 v2, honorários em 3 parcelas. Validade: 5 dias.', 'quando' => $hoje->copy()->subDays(3)->setTime(9, 30), 'autor' => 'Letícia Bonfim'],
                ['tipo' => 'sys', 'titulo' => 'Automação disparada', 'descricao' => 'Sem resposta há 3 dias → tarefa de follow-up criada para Letícia.', 'quando' => $hoje->copy()->setTime(8, 0), 'autor' => 'Automação #4'],
            ],
            default => [
                ['tipo' => 'wa', 'titulo' => 'Primeiro contato', 'descricao' => 'Lead entrou pelo canal de origem e foi distribuído automaticamente.', 'quando' => $hoje->copy()->subDays(4)->setTime(10, 0), 'autor' => 'Sistema'],
                ['tipo' => 'call', 'titulo' => 'Contato de triagem', 'descricao' => 'Qualificação inicial concluída, campos obrigatórios da etapa preenchidos.', 'quando' => $hoje->copy()->subDays(3)->setTime(14, 0), 'autor' => 'Responsável'],
                ['tipo' => 'task', 'titulo' => 'Follow-up agendado', 'descricao' => 'Retorno programado conforme cadência do funil.', 'quando' => $hoje->copy()->subDay()->setTime(9, 0), 'autor' => 'Automação #2'],
            ],
        };

        foreach ($historicos as $item) {
            HistoricoNegociacao::query()->updateOrCreate(
                [
                    'negociacao_id' => $negociacao->id,
                    'titulo' => $item['titulo'],
                ],
                [
                    'tipo' => $item['tipo'],
                    'descricao' => $item['descricao'],
                    'autor' => $item['autor'],
                    'ocorrido_em' => $item['quando'],
                ],
            );
        }
    }
}
