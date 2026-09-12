<?php

namespace Database\Seeders;

use App\Models\CanalContato;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\FinalidadeConsentimento;
use App\Models\IbgeMunicipio;
use App\Models\Role;
use App\Models\Setor;
use App\Models\StatusComercial;
use App\Models\StatusConflito;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\Uf;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;

class EmpresaContatoSeeder extends Seeder
{
    use SeedsForDevTenant;

    /**
     * Seed companies and contacts from the CRM prototype screens.
     */
    public function run(): void
    {
        Role::ensureDefaults();

        $this->call(DominioCrmSeeder::class);

        app(CurrentTenant::class)->runAs($this->devTenant(), function (): void {
            $responsaveis = $this->responsaveis();
            $empresas = $this->empresas($responsaveis);
            $this->contatos($empresas);
        });
    }

    /**
     * @return array<string, User>
     */
    private function responsaveis(): array
    {
        $senha = 'password';

        $dados = [
            [
                'name' => 'Camila Moraes',
                'email' => 'camila.moraes@asfadvogados.adv.br',
                'especialidades' => ['industria-metalurgica', 'alimentos', 'agronegocio'],
            ],
            [
                'name' => 'Rafael Prado',
                'email' => 'rafael.prado@asfadvogados.adv.br',
                'especialidades' => ['software-b2b', 'servicos-financeiros'],
            ],
            [
                'name' => 'Letícia Bonfim',
                'email' => 'leticia.bonfim@asfadvogados.adv.br',
                'especialidades' => ['saude', 'alimentos'],
            ],
        ];

        $usuarios = [];

        foreach ($dados as $dado) {
            $usuarios[$dado['name']] = User::query()->updateOrCreate(
                ['email' => $dado['email']],
                [
                    'name' => $dado['name'],
                    'password' => $senha,
                    'role' => Role::VENDEDOR,
                    'especialidades' => $dado['especialidades'],
                    'ausente_ate' => null,
                ],
            );
        }

        return $usuarios;
    }

    /**
     * @param  array<string, User>  $responsaveis
     * @return array<string, Empresa>
     */
    private function empresas(array $responsaveis): array
    {
        $rs = Uf::query()->where('sigla', 'RS')->firstOrFail();
        $verificado = StatusConflito::query()->where('slug', 'verificado')->firstOrFail();
        $pendente = StatusConflito::query()->where('slug', 'pendente')->firstOrFail();
        $statusComercialPadrao = StatusComercial::query()->where('slug', 'qualificado')->firstOrFail();
        $statusCliente = StatusComercial::query()->where('slug', 'cliente-efetivado')->firstOrFail();
        $statusEmAnalise = StatusComercial::query()->where('slug', 'em-analise')->firstOrFail();

        $linhas = [
            [
                'chave' => 'e1',
                'nome' => 'Metalúrgica Verano S/A',
                'cnpj' => '12.345.678/0001-90',
                'setor' => 'industria-metalurgica',
                'porte' => '340 funcionários',
                'cidade' => 'Caxias do Sul',
                'responsavel' => 'Camila Moraes',
                'conflito' => $verificado,
                'status_comercial' => $statusCliente,
            ],
            [
                'chave' => 'e2',
                'nome' => 'Nexa Tecnologia Ltda',
                'cnpj' => '23.456.789/0001-04',
                'setor' => 'software-b2b',
                'porte' => '85 funcionários',
                'cidade' => 'Porto Alegre',
                'responsavel' => 'Rafael Prado',
                'conflito' => $verificado,
            ],
            [
                'chave' => 'e3',
                'nome' => 'Grupo Aldeia Alimentos',
                'cnpj' => '34.567.890/0001-18',
                'setor' => 'alimentos',
                'porte' => '1.200 funcionários',
                'cidade' => 'Passo Fundo',
                'responsavel' => 'Camila Moraes',
                'conflito' => $verificado,
            ],
            [
                'chave' => 'e4',
                'nome' => 'Cooperativa Vale Verde',
                'cnpj' => '45.678.901/0001-22',
                'setor' => 'agronegocio',
                'porte' => '460 cooperados',
                'cidade' => 'Erechim',
                'responsavel' => 'Rafael Prado',
                'conflito' => $pendente,
                'status_comercial' => $statusEmAnalise,
            ],
            [
                'chave' => 'e5',
                'nome' => 'Construtora Piedade',
                'cnpj' => '56.789.012/0001-36',
                'setor' => 'construcao-civil',
                'porte' => '210 funcionários',
                'cidade' => 'Porto Alegre',
                'responsavel' => 'Camila Moraes',
                'conflito' => $verificado,
            ],
            [
                'chave' => 'e6',
                'nome' => 'Instituto Farrapo',
                'cnpj' => '67.890.123/0001-40',
                'setor' => 'terceiro-setor',
                'porte' => '40 colaboradores',
                'cidade' => 'Bento Gonçalves',
                'responsavel' => 'Letícia Bonfim',
                'conflito' => $verificado,
            ],
            [
                'chave' => 'e7',
                'nome' => 'Têxtil Guaporé Ltda',
                'cnpj' => '78.901.234/0001-55',
                'setor' => 'textil',
                'porte' => '150 funcionários',
                'cidade' => 'Guaporé',
                'responsavel' => 'Camila Moraes',
                'conflito' => $verificado,
            ],
        ];

        $empresas = [];

        foreach ($linhas as $linha) {
            $ok = $linha['conflito']->slug === 'verificado';
            $municipioId = IbgeMunicipio::query()
                ->whereHas('estado', fn ($query) => $query->whereRaw('UPPER(txt_sigla_uf) = ?', ['RS']))
                ->whereRaw('LOWER(txt_nome_municipios) = ?', [mb_strtolower($linha['cidade'])])
                ->value('id');

            $empresas[$linha['chave']] = Empresa::query()->updateOrCreate(
                ['cnpj' => $linha['cnpj']],
                [
                    'nome' => $linha['nome'],
                    'setor_id' => Setor::query()->where('slug', $linha['setor'])->firstOrFail()->id,
                    'porte' => $linha['porte'],
                    'cidade' => $linha['cidade'],
                    'uf_id' => $rs->id,
                    'municipio_id' => $municipioId,
                    'responsavel_user_id' => $responsaveis[$linha['responsavel']]->id,
                    'status_conflito_id' => $linha['conflito']->id,
                    'status_comercial_id' => ($linha['status_comercial'] ?? $statusComercialPadrao)->id,
                    'conflito_texto' => $ok
                        ? 'Verificado em 04/08 contra a base de clientes e partes contrárias. Sem impedimento para atuação.'
                        : 'Verificação pendente: há parte relacionada em processo patrocinado pelo escritório. A negociação não avança de etapa até o parecer do sócio responsável.',
                    'conflito_verificado_em' => $ok ? '2026-08-04' : null,
                ],
            );
        }

        return $empresas;
    }

    /**
     * @param  array<string, Empresa>  $empresas
     */
    private function contatos(array $empresas): void
    {
        $pj = TipoPessoa::query()->where('slug', 'pj')->firstOrFail();
        $pf = TipoPessoa::query()->where('slug', 'pf')->firstOrFail();
        $statusComercialPadrao = StatusComercial::query()->where('slug', 'qualificado')->firstOrFail();
        $statusNovo = StatusComercial::query()->where('slug', 'novo')->firstOrFail();
        $statusCliente = StatusComercial::query()->where('slug', 'cliente-efetivado')->firstOrFail();

        $linhas = [
            ['email' => 'renata.bonfanti@verano.ind.br', 'nome' => 'Renata Bonfanti', 'cargo' => 'Diretora de RH', 'empresa' => 'e1', 'telefone' => '(54) 99712-4408', 'canal' => 'whatsapp', 'consent' => 'opt-in-registrado', 'mesclado' => true],
            ['email' => 'p.sartori@verano.ind.br', 'nome' => 'Paulo Sartori', 'cargo' => 'Gerente jurídico', 'empresa' => 'e1', 'telefone' => '(54) 99881-2210', 'canal' => 'e-mail', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'marina@nexatech.com.br', 'nome' => 'Marina Yoshida', 'cargo' => 'CTO e sócia', 'empresa' => 'e2', 'telefone' => '(51) 99604-7781', 'canal' => 'site', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'sergio@grupoaldeia.com.br', 'nome' => 'Sérgio Aldeia', 'cargo' => 'Diretor-presidente', 'empresa' => 'e3', 'telefone' => '(54) 99123-6650', 'canal' => 'indicacao', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'presidencia@valeverde.coop.br', 'nome' => 'Nelson Brizola', 'cargo' => 'Presidente do conselho', 'empresa' => 'e4', 'telefone' => '(54) 99450-1187', 'canal' => 'google-maps', 'consent' => 'pendente', 'mesclado' => false],
            ['email' => 'fabiana@construtorapiedade.com.br', 'nome' => 'Fabiana Piedade', 'cargo' => 'Diretora de contratos', 'empresa' => 'e5', 'telefone' => '(51) 99338-9042', 'canal' => 'indicacao', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'clara@institutofarrapo.org.br', 'nome' => 'Clara Menezes', 'cargo' => 'Superintendente', 'empresa' => 'e6', 'telefone' => '(54) 99277-3319', 'canal' => 'site', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'ricardo@textilguapore.com.br', 'nome' => 'Ricardo Guaporé', 'cargo' => 'CEO', 'empresa' => 'e7', 'telefone' => '(54) 99811-7723', 'canal' => 'indicacao', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'juliana.ferraz@email.com', 'nome' => 'Juliana Ferraz', 'cargo' => 'Candidata — TRF 4ª Região', 'empresa' => null, 'telefone' => '(51) 99120-3388', 'canal' => 'whatsapp', 'consent' => 'opt-in-registrado', 'mesclado' => true],
            ['email' => 'mvleal@email.com', 'nome' => 'Marcos Vinícius Leal', 'cargo' => 'Candidato — PM/RS', 'empresa' => null, 'telefone' => '(51) 99745-2210', 'canal' => 'instagram', 'consent' => 'pendente', 'mesclado' => false],
            ['email' => 'patricia.andrade@email.com', 'nome' => 'Patrícia Andrade', 'cargo' => 'Aprovada — Prefeitura de Canoas', 'empresa' => null, 'telefone' => '(51) 99633-8890', 'canal' => 'whatsapp', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'e.nakamura@email.com', 'nome' => 'Eduardo Nakamura', 'cargo' => 'Candidato PcD — INSS', 'empresa' => null, 'telefone' => '(51) 99502-1174', 'canal' => 'site', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'simone.b@email.com', 'nome' => 'Simone Barcelos', 'cargo' => 'Candidata — Banrisul', 'empresa' => null, 'telefone' => '(51) 99418-6603', 'canal' => 'whatsapp', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'rodrigo.tavares@email.com', 'nome' => 'Rodrigo Tavares', 'cargo' => 'Candidato — Polícia Civil', 'empresa' => null, 'telefone' => '(51) 99387-4429', 'canal' => 'indicacao', 'consent' => 'opt-in-registrado', 'mesclado' => false],
            ['email' => 'aline.cordeiro@email.com', 'nome' => 'Aline Cordeiro', 'cargo' => 'Candidata — Correios', 'empresa' => null, 'telefone' => '(51) 99254-7736', 'canal' => 'instagram', 'consent' => 'revogado', 'mesclado' => false],
            ['email' => 'h.sales@email.com', 'nome' => 'Henrique Sales', 'cargo' => 'Aprovado — TJ/RS', 'empresa' => null, 'telefone' => '(51) 99190-5562', 'canal' => 'whatsapp', 'consent' => 'opt-in-registrado', 'mesclado' => false],
        ];

        foreach ($linhas as $linha) {
            $empresa = $linha['empresa'] ? $empresas[$linha['empresa']] : null;
            $statusComercialId = $empresa?->status_comercial_id
                ?? ($linha['consent'] === 'opt-in-registrado' ? $statusComercialPadrao->id : $statusNovo->id);

            if ($linha['email'] === 'h.sales@email.com' || $linha['email'] === 'patricia.andrade@email.com') {
                $statusComercialId = $statusCliente->id;
            }

            $contato = Contato::query()->updateOrCreate(
                ['email' => $linha['email']],
                [
                    'nome' => $linha['nome'],
                    'cargo' => $linha['cargo'],
                    'empresa_id' => $empresa?->id,
                    'tipo_pessoa_id' => $empresa ? $pj->id : $pf->id,
                    'telefone' => $linha['telefone'],
                    'canal_contato_id' => CanalContato::query()->where('slug', $linha['canal'])->firstOrFail()->id,
                    'status_consentimento_id' => StatusConsentimento::query()->where('slug', $linha['consent'])->firstOrFail()->id,
                    'status_comercial_id' => $statusComercialId,
                    'registro_mesclado' => $linha['mesclado'],
                    'observacao_deduplicacao' => $linha['mesclado']
                        ? '2 registros mesclados · WhatsApp + formulário'
                        : 'registro único',
                ],
            );

            $this->consentimentos($contato, $linha['consent']);
        }
    }

    private function consentimentos(Contato $contato, string $consentSlug): void
    {
        $revogado = $consentSlug === 'revogado';
        $pendente = $consentSlug === 'pendente';

        $finalidades = [
            'contato-comercial' => [
                'status' => $revogado ? 'revogado' : 'vigente',
                'concedido_em' => $revogado ? null : '2026-08-04',
                'revogado_em' => $revogado ? '2026-08-10' : null,
            ],
            'mensagens' => [
                'status' => $revogado ? 'suspenso' : ($pendente ? 'pendente' : 'opt-in-registrado'),
                'concedido_em' => $revogado || $pendente ? null : '2026-08-04',
                'revogado_em' => $revogado ? '2026-08-10' : null,
            ],
            'newsletter' => [
                'status' => 'nao-concedido',
                'concedido_em' => null,
                'revogado_em' => null,
            ],
        ];

        foreach ($finalidades as $slug => $dados) {
            $finalidade = FinalidadeConsentimento::query()->where('slug', $slug)->firstOrFail();

            ConsentimentoContato::query()->updateOrCreate(
                [
                    'contato_id' => $contato->id,
                    'finalidade_consentimento_id' => $finalidade->id,
                ],
                [
                    'status_consentimento_id' => StatusConsentimento::query()->where('slug', $dados['status'])->firstOrFail()->id,
                    'concedido_em' => $dados['concedido_em'],
                    'revogado_em' => $dados['revogado_em'],
                ],
            );
        }
    }
}
