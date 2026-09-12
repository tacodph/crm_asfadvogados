export type ScreenKey =
    | 'painel'
    | 'negociacoes'
    | 'atividades'
    | 'calendario'
    | 'contatos'
    | 'empresas'
    | 'propostas'
    | 'automacoes'
    | 'trafego'
    | 'investimento'
    | 'site'
    | 'compliance'
    | 'admin'
    | 'configuracoes';

export type FunilKey = string;

export type Lead = {
    id: number;
    funil: string;
    etapa: number;
    nome: string;
    assunto: string;
    canal: string;
    valor: number;
    resp: string;
    dias: number;
    consent: string;
    empresaId: string | null;
    contatoId: number | null;
    previsao: string;
    tarefa: string;
    tarefaOff: number;
    tarefaHora: string;
};

export type Empresa = {
    id: string;
    nome: string;
    cnpj: string;
    setor: string;
    porte: string;
    cidade: string;
    resp: string;
    conflito: string;
};

export type Contato = {
    id: number;
    nome: string;
    cargo: string;
    empresaId: string | null;
    email: string;
    tel: string;
    canal: string;
    consent: string;
};

export type FunilEtapa = {
    nome: string;
    sla: string;
    campos: string[];
    exigeMotivo: boolean;
};

export type FunilConfig = {
    key: string;
    nome: string;
    distribuicao: string;
    etapas: FunilEtapa[];
    motivos: string[];
};

export type Fonte = {
    id: string;
    nome: string;
    tipo: string;
    estado: string;
    conta: string;
    leads30: number;
    custo: string;
    detalhe: string;
    consentimento: string;
    ultimoEvento: string;
};

export type SiteContent = {
    olho: string;
    titulo: string;
    descricao: string;
    n1: string;
    l1: string;
    n2: string;
    l2: string;
    n3: string;
    l3: string;
};

export type EventoApi = {
    hora: string;
    fonte: string;
    tipo: string;
    status: string;
    detalhe: string;
};

export type TimelineItem = {
    tipo: string;
    titulo: string;
    desc: string;
    quando: string;
    autor: string;
};

export type Termo = {
    re: RegExp;
    nivel: 'bloqueio' | 'atenção';
    titulo: string;
    norma: string;
};

export const CANAIS: Record<string, string> = {
    WhatsApp: 'var(--accent)',
    Site: 'var(--primary)',
    Indicação: '#3F5E8C',
    Instagram: '#8C4A6B',
    'Google Maps': '#7A6E3F',
};

export const DISTRIBUICOES: string[] = [
    'round robin por especialidade',
    'round robin simples',
    'por carga de trabalho',
];

export const FONTES: Fonte[] = [
    {
        id: 'meta',
        nome: 'Meta Ads (Facebook / Instagram Lead Ads)',
        tipo: 'Formulário de lead nativo',
        estado: 'conectado',
        conta: 'act_882410 · ASF Advogados Associados',
        leads30: 41,
        custo: 'R$ 62,40',
        detalhe:
            'Webhook de leadgen assinado. Campos mapeados: nome, telefone, e-mail, concurso de interesse.',
        consentimento:
            'Checkbox de consentimento obrigatório no formulário, com texto versionado.',
        ultimoEvento: 'há 12 min',
    },
    {
        id: 'google',
        nome: 'Google Ads — Lead Form Extension',
        tipo: 'Formulário de lead nativo',
        estado: 'conectado',
        conta: '123-456-7890 · campanha institucional',
        leads30: 27,
        custo: 'R$ 88,10',
        detalhe:
            'Webhook com chave compartilhada. Só campanhas de pesquisa por termo institucional ou informativo.',
        consentimento: 'Aviso de privacidade e opt-in explícito no formulário.',
        ultimoEvento: 'há 1h',
    },
    {
        id: 'site',
        nome: 'Site do escritório — formulários',
        tipo: 'Webhook próprio',
        estado: 'conectado',
        conta: 'asfadvogados.adv.br',
        leads30: 34,
        custo: 'orgânico',
        detalhe:
            'POST assinado por HMAC. Captura UTMs, página de origem e ID de clique.',
        consentimento:
            'Consentimento granular por finalidade, armazenado com texto exibido.',
        ultimoEvento: 'há 4 min',
    },
    {
        id: 'gbp',
        nome: 'Google Business Profile',
        tipo: 'Mensagens e chamadas',
        estado: 'conectado',
        conta: 'Perfil Porto Alegre · matriz',
        leads30: 8,
        custo: 'orgânico',
        detalhe:
            'Mensagens do perfil e chamadas rastreadas viram interação no histórico do lead.',
        consentimento:
            'Aviso enviado na primeira resposta; opt-in registrado a partir do contato do titular.',
        ultimoEvento: 'há 3h',
    },
    {
        id: 'tiktok',
        nome: 'TikTok Lead Generation',
        tipo: 'Formulário de lead nativo',
        estado: 'desconectado',
        conta: 'não configurado',
        leads30: 0,
        custo: '—',
        detalhe:
            'Disponível para conexão. Exige revisão prévia do criativo pelo comitê de ética.',
        consentimento:
            'Requer template de formulário aprovado antes de ativar.',
        ultimoEvento: '—',
    },
];

export const EVENTOS_API: EventoApi[] = [
    {
        hora: '14:38:12',
        fonte: 'Meta Ads',
        tipo: 'lead.created',
        status: 'aceito',
        detalhe:
            'Lead “A. Prestes” · utm_campaign=institucional-ago · atribuído a Diego Alencar',
    },
    {
        hora: '14:31:47',
        fonte: 'Site',
        tipo: 'lead.created',
        status: 'mesclado',
        detalhe:
            'Telefone já existente — mesclado ao contato #205 (Simone Barcelos)',
    },
    {
        hora: '13:58:03',
        fonte: 'Google Ads',
        tipo: 'lead.created',
        status: 'aceito',
        detalhe: 'Lead “M. Rocha” · gclid registrado · funil Concursos (PF)',
    },
    {
        hora: '13:12:55',
        fonte: 'Meta Ads',
        tipo: 'lead.rejected',
        status: 'recusado',
        detalhe:
            'Sem consentimento no payload — descartado sem persistir dado pessoal',
    },
    {
        hora: '12:40:21',
        fonte: 'Google Business',
        tipo: 'message.received',
        status: 'aceito',
        detalhe: 'Mensagem do perfil convertida em interação no histórico',
    },
    {
        hora: '11:07:34',
        fonte: 'Site',
        tipo: 'lead.rejected',
        status: 'recusado',
        detalhe:
            'Assinatura HMAC inválida — origem não confiável, requisição bloqueada',
    },
];

export const FUNIS_INIT: FunilConfig[] = [
    {
        key: 'b2b',
        nome: 'B2B consultivo',
        distribuicao: 'round robin por especialidade',
        etapas: [
            {
                nome: 'Prospecção / Inbound',
                sla: '4h',
                campos: ['origem', 'consentimento'],
                exigeMotivo: false,
            },
            {
                nome: 'Diagnóstico',
                sla: '5d',
                campos: ['porte', 'dor mapeada', 'conflito verificado'],
                exigeMotivo: true,
            },
            {
                nome: 'Apresentação da solução',
                sla: '7d',
                campos: ['escopo', 'honorários'],
                exigeMotivo: true,
            },
            {
                nome: 'Negociação',
                sla: '10d',
                campos: ['minuta enviada', 'objeção'],
                exigeMotivo: true,
            },
            {
                nome: 'Fechamento',
                sla: '—',
                campos: ['contrato assinado', 'procuração'],
                exigeMotivo: false,
            },
        ],
        motivos: [
            'Honorários',
            'Sem resposta',
            'Outro escritório',
            'Fora da área',
            'Resolveu internamente',
        ],
    },
    {
        key: 'pf',
        nome: 'Concursos (PF, volume)',
        distribuicao: 'round robin simples',
        etapas: [
            {
                nome: 'Novo lead',
                sla: '15min',
                campos: ['telefone válido', 'consentimento'],
                exigeMotivo: false,
            },
            {
                nome: 'Triagem',
                sla: '1d',
                campos: ['concurso/banca', 'fase'],
                exigeMotivo: true,
            },
            {
                nome: 'Envio da oferta',
                sla: '2d',
                campos: ['oferta registrada'],
                exigeMotivo: true,
            },
            {
                nome: 'Objeções',
                sla: '3d',
                campos: ['objeção classificada'],
                exigeMotivo: true,
            },
            {
                nome: 'Fechamento',
                sla: '—',
                campos: ['contrato assinado'],
                exigeMotivo: false,
            },
        ],
        motivos: [
            'Honorários',
            'Sem resposta',
            'Prazo perdido',
            'Fora da área',
            'Desistiu',
        ],
    },
];

export const EMPRESAS: Empresa[] = [
    {
        id: 'e1',
        nome: 'Metalúrgica Verano S/A',
        cnpj: '12.345.678/0001-90',
        setor: 'Indústria metalúrgica',
        porte: '340 funcionários',
        cidade: 'Caxias do Sul / RS',
        resp: 'Camila Moraes',
        conflito: 'verificado',
    },
    {
        id: 'e2',
        nome: 'Nexa Tecnologia Ltda',
        cnpj: '23.456.789/0001-04',
        setor: 'Software B2B',
        porte: '85 funcionários',
        cidade: 'Porto Alegre / RS',
        resp: 'Rafael Prado',
        conflito: 'verificado',
    },
    {
        id: 'e3',
        nome: 'Grupo Aldeia Alimentos',
        cnpj: '34.567.890/0001-18',
        setor: 'Alimentos',
        porte: '1.200 funcionários',
        cidade: 'Passo Fundo / RS',
        resp: 'Camila Moraes',
        conflito: 'verificado',
    },
    {
        id: 'e4',
        nome: 'Cooperativa Vale Verde',
        cnpj: '45.678.901/0001-22',
        setor: 'Agronegócio',
        porte: '460 cooperados',
        cidade: 'Erechim / RS',
        resp: 'Rafael Prado',
        conflito: 'pendente',
    },
    {
        id: 'e5',
        nome: 'Construtora Piedade',
        cnpj: '56.789.012/0001-36',
        setor: 'Construção civil',
        porte: '210 funcionários',
        cidade: 'Porto Alegre / RS',
        resp: 'Camila Moraes',
        conflito: 'verificado',
    },
    {
        id: 'e6',
        nome: 'Instituto Farrapo',
        cnpj: '67.890.123/0001-40',
        setor: 'Terceiro setor',
        porte: '40 colaboradores',
        cidade: 'Bento Gonçalves / RS',
        resp: 'Letícia Bonfim',
        conflito: 'verificado',
    },
    {
        id: 'e7',
        nome: 'Têxtil Guaporé Ltda',
        cnpj: '78.901.234/0001-55',
        setor: 'Têxtil',
        porte: '150 funcionários',
        cidade: 'Guaporé / RS',
        resp: 'Camila Moraes',
        conflito: 'verificado',
    },
];

export const CONTATOS: Contato[] = [
    {
        id: 101,
        nome: 'Renata Bonfanti',
        cargo: 'Diretora de RH',
        empresaId: 'e1',
        email: 'renata.bonfanti@verano.ind.br',
        tel: '(54) 99712-4408',
        canal: 'WhatsApp',
        consent: 'opt-in registrado',
    },
    {
        id: 108,
        nome: 'Paulo Sartori',
        cargo: 'Gerente jurídico',
        empresaId: 'e1',
        email: 'p.sartori@verano.ind.br',
        tel: '(54) 99881-2210',
        canal: 'E-mail',
        consent: 'opt-in registrado',
    },
    {
        id: 102,
        nome: 'Marina Yoshida',
        cargo: 'CTO e sócia',
        empresaId: 'e2',
        email: 'marina@nexatech.com.br',
        tel: '(51) 99604-7781',
        canal: 'Site',
        consent: 'opt-in registrado',
    },
    {
        id: 103,
        nome: 'Sérgio Aldeia',
        cargo: 'Diretor-presidente',
        empresaId: 'e3',
        email: 'sergio@grupoaldeia.com.br',
        tel: '(54) 99123-6650',
        canal: 'Indicação',
        consent: 'opt-in registrado',
    },
    {
        id: 104,
        nome: 'Nelson Brizola',
        cargo: 'Presidente do conselho',
        empresaId: 'e4',
        email: 'presidencia@valeverde.coop.br',
        tel: '(54) 99450-1187',
        canal: 'Google Maps',
        consent: 'pendente',
    },
    {
        id: 105,
        nome: 'Fabiana Piedade',
        cargo: 'Diretora de contratos',
        empresaId: 'e5',
        email: 'fabiana@construtorapiedade.com.br',
        tel: '(51) 99338-9042',
        canal: 'Indicação',
        consent: 'opt-in registrado',
    },
    {
        id: 106,
        nome: 'Clara Menezes',
        cargo: 'Superintendente',
        empresaId: 'e6',
        email: 'clara@institutofarrapo.org.br',
        tel: '(54) 99277-3319',
        canal: 'Site',
        consent: 'opt-in registrado',
    },
    {
        id: 107,
        nome: 'Ricardo Guaporé',
        cargo: 'CEO',
        empresaId: 'e7',
        email: 'ricardo@textilguapore.com.br',
        tel: '(54) 99811-7723',
        canal: 'Indicação',
        consent: 'opt-in registrado',
    },
    {
        id: 201,
        nome: 'Juliana Ferraz',
        cargo: 'Candidata — TRF 4ª Região',
        empresaId: null,
        email: 'juliana.ferraz@email.com',
        tel: '(51) 99120-3388',
        canal: 'WhatsApp',
        consent: 'opt-in registrado',
    },
    {
        id: 202,
        nome: 'Marcos Vinícius Leal',
        cargo: 'Candidato — PM/RS',
        empresaId: null,
        email: 'mvleal@email.com',
        tel: '(51) 99745-2210',
        canal: 'Instagram',
        consent: 'pendente',
    },
    {
        id: 203,
        nome: 'Patrícia Andrade',
        cargo: 'Aprovada — Prefeitura de Canoas',
        empresaId: null,
        email: 'patricia.andrade@email.com',
        tel: '(51) 99633-8890',
        canal: 'WhatsApp',
        consent: 'opt-in registrado',
    },
    {
        id: 204,
        nome: 'Eduardo Nakamura',
        cargo: 'Candidato PcD — INSS',
        empresaId: null,
        email: 'e.nakamura@email.com',
        tel: '(51) 99502-1174',
        canal: 'Site',
        consent: 'opt-in registrado',
    },
    {
        id: 205,
        nome: 'Simone Barcelos',
        cargo: 'Candidata — Banrisul',
        empresaId: null,
        email: 'simone.b@email.com',
        tel: '(51) 99418-6603',
        canal: 'WhatsApp',
        consent: 'opt-in registrado',
    },
    {
        id: 206,
        nome: 'Rodrigo Tavares',
        cargo: 'Candidato — Polícia Civil',
        empresaId: null,
        email: 'rodrigo.tavares@email.com',
        tel: '(51) 99387-4429',
        canal: 'Indicação',
        consent: 'opt-in registrado',
    },
    {
        id: 207,
        nome: 'Aline Cordeiro',
        cargo: 'Candidata — Correios',
        empresaId: null,
        email: 'aline.cordeiro@email.com',
        tel: '(51) 99254-7736',
        canal: 'Instagram',
        consent: 'revogado',
    },
    {
        id: 208,
        nome: 'Henrique Sales',
        cargo: 'Aprovado — TJ/RS',
        empresaId: null,
        email: 'h.sales@email.com',
        tel: '(51) 99190-5562',
        canal: 'WhatsApp',
        consent: 'opt-in registrado',
    },
];

export const LEADS: Lead[] = [
    {
        id: 1,
        funil: 'b2b',
        etapa: 0,
        nome: 'Metalúrgica Verano S/A',
        assunto: 'Compliance trabalhista',
        canal: 'Indicação',
        valor: 48000,
        resp: 'Camila Moraes',
        dias: 2,
        consent: 'opt-in registrado',
        empresaId: 'e1',
        contatoId: 101,
        previsao: '05/09',
        tarefa: 'Reunião de diagnóstico presencial',
        tarefaOff: 0,
        tarefaHora: '15:00',
    },
    {
        id: 2,
        funil: 'b2b',
        etapa: 0,
        nome: 'Nexa Tecnologia Ltda',
        assunto: 'LGPD e contratos SaaS',
        canal: 'Site',
        valor: 36000,
        resp: 'Rafael Prado',
        dias: 1,
        consent: 'opt-in registrado',
        empresaId: 'e2',
        contatoId: 102,
        previsao: '12/09',
        tarefa: 'Ligar para qualificar',
        tarefaOff: 0,
        tarefaHora: '16:00',
    },
    {
        id: 3,
        funil: 'b2b',
        etapa: 1,
        nome: 'Grupo Aldeia Alimentos',
        assunto: 'Reestruturação societária',
        canal: 'Indicação',
        valor: 120000,
        resp: 'Camila Moraes',
        dias: 6,
        consent: 'opt-in registrado',
        empresaId: 'e3',
        contatoId: 103,
        previsao: '30/09',
        tarefa: 'Enviar escopo preliminar',
        tarefaOff: 1,
        tarefaHora: '09:30',
    },
    {
        id: 4,
        funil: 'b2b',
        etapa: 1,
        nome: 'Cooperativa Vale Verde',
        assunto: 'Consultivo tributário',
        canal: 'Google Maps',
        valor: 64000,
        resp: 'Rafael Prado',
        dias: 11,
        consent: 'pendente',
        empresaId: 'e4',
        contatoId: 104,
        previsao: '—',
        tarefa: 'Retomar contato e coletar consentimento',
        tarefaOff: -6,
        tarefaHora: '10:00',
    },
    {
        id: 5,
        funil: 'b2b',
        etapa: 2,
        nome: 'Construtora Piedade',
        assunto: 'Due diligence contratual',
        canal: 'Indicação',
        valor: 210000,
        resp: 'Camila Moraes',
        dias: 4,
        consent: 'opt-in registrado',
        empresaId: 'e5',
        contatoId: 105,
        previsao: '22/08',
        tarefa: 'Apresentar proposta v3',
        tarefaOff: 1,
        tarefaHora: '10:00',
    },
    {
        id: 6,
        funil: 'b2b',
        etapa: 3,
        nome: 'Instituto Farrapo',
        assunto: 'Governança do terceiro setor',
        canal: 'Site',
        valor: 25000,
        resp: 'Letícia Bonfim',
        dias: 3,
        consent: 'opt-in registrado',
        empresaId: 'e6',
        contatoId: 106,
        previsao: '28/08',
        tarefa: 'Responder objeção de prazo',
        tarefaOff: 2,
        tarefaHora: '11:00',
    },
    {
        id: 7,
        funil: 'b2b',
        etapa: 4,
        nome: 'Têxtil Guaporé Ltda',
        assunto: 'Assessoria consultiva anual',
        canal: 'Indicação',
        valor: 96000,
        resp: 'Camila Moraes',
        dias: 1,
        consent: 'opt-in registrado',
        empresaId: 'e7',
        contatoId: 107,
        previsao: '15/08',
        tarefa: 'Coletar assinatura eletrônica',
        tarefaOff: 0,
        tarefaHora: '17:00',
    },
    {
        id: 8,
        funil: 'pf',
        etapa: 0,
        nome: 'Juliana Ferraz',
        assunto: 'Recurso administrativo — TRF',
        canal: 'WhatsApp',
        valor: 3200,
        resp: 'Letícia Bonfim',
        dias: 0,
        consent: 'opt-in registrado',
        empresaId: null,
        contatoId: 201,
        previsao: '18/08',
        tarefa: 'Primeiro contato — SLA de 15 minutos',
        tarefaOff: 0,
        tarefaHora: '08:30',
    },
    {
        id: 9,
        funil: 'pf',
        etapa: 0,
        nome: 'Marcos Vinícius Leal',
        assunto: 'Eliminação em teste físico',
        canal: 'Instagram',
        valor: 2800,
        resp: 'Diego Alencar',
        dias: 1,
        consent: 'pendente',
        empresaId: null,
        contatoId: 202,
        previsao: '20/08',
        tarefa: 'Coletar consentimento formal',
        tarefaOff: 1,
        tarefaHora: '09:00',
    },
    {
        id: 10,
        funil: 'pf',
        etapa: 1,
        nome: 'Patrícia Andrade',
        assunto: 'Nomeação tardia — MS',
        canal: 'WhatsApp',
        valor: 4500,
        resp: 'Diego Alencar',
        dias: 2,
        consent: 'opt-in registrado',
        empresaId: null,
        contatoId: 203,
        previsao: '25/08',
        tarefa: 'Conferir prazo administrativo',
        tarefaOff: 3,
        tarefaHora: '14:00',
    },
    {
        id: 11,
        funil: 'pf',
        etapa: 1,
        nome: 'Eduardo Nakamura',
        assunto: 'Reserva de vagas PcD',
        canal: 'Site',
        valor: 3900,
        resp: 'Letícia Bonfim',
        dias: 5,
        consent: 'opt-in registrado',
        empresaId: null,
        contatoId: 204,
        previsao: '27/08',
        tarefa: 'Solicitar edital e laudo médico',
        tarefaOff: -2,
        tarefaHora: '11:30',
    },
    {
        id: 12,
        funil: 'pf',
        etapa: 2,
        nome: 'Simone Barcelos',
        assunto: 'Questão anulável — banca',
        canal: 'WhatsApp',
        valor: 2400,
        resp: 'Diego Alencar',
        dias: 3,
        consent: 'opt-in registrado',
        empresaId: null,
        contatoId: 205,
        previsao: '19/08',
        tarefa: 'Confirmar recebimento da oferta',
        tarefaOff: 2,
        tarefaHora: '16:30',
    },
    {
        id: 13,
        funil: 'pf',
        etapa: 2,
        nome: 'Rodrigo Tavares',
        assunto: 'Investigação social reprovada',
        canal: 'Indicação',
        valor: 6500,
        resp: 'Letícia Bonfim',
        dias: 8,
        consent: 'opt-in registrado',
        empresaId: null,
        contatoId: 206,
        previsao: '16/08',
        tarefa: 'Retomar contato antes do vencimento da proposta',
        tarefaOff: -1,
        tarefaHora: '09:00',
    },
    {
        id: 14,
        funil: 'pf',
        etapa: 3,
        nome: 'Aline Cordeiro',
        assunto: 'Exame psicotécnico',
        canal: 'Instagram',
        valor: 3100,
        resp: 'Diego Alencar',
        dias: 4,
        consent: 'revogado',
        empresaId: null,
        contatoId: 207,
        previsao: '—',
        tarefa: 'Registrar revogação e encerrar contato',
        tarefaOff: 4,
        tarefaHora: '13:00',
    },
    {
        id: 15,
        funil: 'pf',
        etapa: 4,
        nome: 'Henrique Sales',
        assunto: 'Posse suspensa — liminar',
        canal: 'WhatsApp',
        valor: 5200,
        resp: 'Letícia Bonfim',
        dias: 1,
        consent: 'opt-in registrado',
        empresaId: null,
        contatoId: 208,
        previsao: '14/08',
        tarefa: 'Assinatura de contrato',
        tarefaOff: 0,
        tarefaHora: '15:30',
    },
];

export const TIMELINES: Record<number, TimelineItem[]> = {
    1: [
        {
            tipo: 'wa',
            titulo: 'Mensagem recebida (WhatsApp)',
            desc: '"Fomos indicados pelo Dr. Otávio. Precisamos revisar nossos contratos de terceirização."',
            quando: 'hoje 09:14',
            autor: 'Captura automática · canal oficial',
        },
        {
            tipo: 'sys',
            titulo: 'Consentimento registrado',
            desc: 'Base legal: consentimento (art. 7º, I, LGPD) para contato comercial por WhatsApp e e-mail.',
            quando: 'hoje 09:15',
            autor: 'Sistema',
        },
        {
            tipo: 'call',
            titulo: 'Ligação de qualificação — 12min',
            desc: 'Empresa com 340 funcionários, 3 ações trabalhistas ativas. Decisor: diretora de RH.',
            quando: 'hoje 11:02',
            autor: 'Camila Moraes',
        },
        {
            tipo: 'task',
            titulo: 'Tarefa agendada',
            desc: 'Reunião de diagnóstico presencial — quinta, 15h.',
            quando: 'hoje 11:20',
            autor: 'Camila Moraes',
        },
    ],
    13: [
        {
            tipo: 'wa',
            titulo: 'Indicação recebida',
            desc: 'Cliente anterior encaminhou contato. Origem registrada no módulo de indicações.',
            quando: 'há 8 dias',
            autor: 'Sistema',
        },
        {
            tipo: 'call',
            titulo: 'Triagem por telefone — 8min',
            desc: 'Reprovação em investigação social por antecedente arquivado. Documentação parcial recebida.',
            quando: 'há 7 dias',
            autor: 'Letícia Bonfim',
        },
        {
            tipo: 'mail',
            titulo: 'Oferta enviada',
            desc: 'Proposta PR-2026-118 v2, honorários em 3 parcelas. Validade: 5 dias.',
            quando: 'há 3 dias',
            autor: 'Letícia Bonfim',
        },
        {
            tipo: 'sys',
            titulo: 'Automação disparada',
            desc: 'Sem resposta há 3 dias → tarefa de follow-up criada para Letícia.',
            quando: 'hoje 08:00',
            autor: 'Automação #4',
        },
    ],
};

export const TL_DEFAULT: TimelineItem[] = [
    {
        tipo: 'wa',
        titulo: 'Primeiro contato',
        desc: 'Lead entrou pelo canal de origem e foi distribuído automaticamente.',
        quando: 'há 4 dias',
        autor: 'Sistema',
    },
    {
        tipo: 'call',
        titulo: 'Contato de triagem',
        desc: 'Qualificação inicial concluída, campos obrigatórios da etapa preenchidos.',
        quando: 'há 3 dias',
        autor: 'Responsável',
    },
    {
        tipo: 'task',
        titulo: 'Follow-up agendado',
        desc: 'Retorno programado conforme cadência do funil.',
        quando: 'há 1 dia',
        autor: 'Automação #2',
    },
];

export const TL_COR: Record<string, string> = {
    wa: 'var(--accent)',
    call: '#3F5E8C',
    mail: 'var(--primary)',
    sys: 'var(--muted-foreground)',
    task: '#8C4A6B',
};

export const TERMOS: Termo[] = [
    {
        re: /garant|assegur[ao]|com certeza|100%|êxito certo|ganho de causa/i,
        nivel: 'bloqueio',
        titulo: 'Promessa de resultado',
        norma: 'Provimento 205/2021 (CFOAB) e EAOAB art. 34, IV — vedado prometer ou insinuar resultado.',
    },
    {
        re: /promo(ção|cional)|desconto|imperd|oferta relâmpago|barato|menor preço/i,
        nivel: 'bloqueio',
        titulo: 'Linguagem mercantilista',
        norma: 'Provimento 205/2021 — vedada mercantilização da advocacia e captação por preço.',
    },
    {
        re: /melhor escritório|nº ?1|líder de mercado|mais premiado/i,
        nivel: 'bloqueio',
        titulo: 'Autopromoção comparativa',
        norma: 'Provimento 205/2021 — vedada publicidade imoderada, comparativa ou de autoexaltação.',
    },
    {
        re: /urgente|últim[ao]s? (vaga|dia)|corre|agora ou nunca/i,
        nivel: 'atenção',
        titulo: 'Gatilho de urgência artificial',
        norma: 'Risco de captação desleal; revise o tom antes de publicar.',
    },
];

export const SITE_INIT: SiteContent = {
    olho: 'QUEM SOMOS',
    titulo: 'Um escritório,\nduas causas.',
    descricao:
        'Somos um escritório de advocacia com atuação nacional, presente em 24 estados e com mais de 3 mil clientes atendidos. Temos ampla experiência em Direito Público, especialmente na defesa de candidatos em concursos públicos e de servidores públicos, na esfera administrativa e judicial.\n\nAlém do Direito Público, atuamos em Direito Empresarial Bancário — na gestão e reestruturação de passivos bancários de empresas e empresários endividados, ajudando negócios a atravessar a crise sem perder o fôlego para crescer.\n\nSob a liderança de Dr. Silas Adauto e Dra. Nayara França, somos reconhecidos nacionalmente pela atuação especializada, pelo atendimento próximo e pelo acompanhamento individualizado de cada caso. Dr. Silas, ex-concurseiro aprovado, entende na pele os desafios de quem estuda para um cargo público — e aplica essa mesma proximidade para proteger empresários endividados ou em reestruturação.',
    n1: '24',
    l1: 'Estados atendidos',
    n2: '3.000+',
    l2: 'Clientes atendidos',
    n3: '2',
    l3: 'Áreas de atuação nacional',
};

export const NAV_GROUPS: {
    title: string;
    items: { key: ScreenKey; label: string }[];
}[] = [
    {
        title: 'Comercial',
        items: [
            { key: 'painel', label: 'Painel' },
            { key: 'negociacoes', label: 'Funil' },
            { key: 'contatos', label: 'Contatos' },
            { key: 'empresas', label: 'Empresas' },
            { key: 'atividades', label: 'Atividades' },
            { key: 'calendario', label: 'Calendário' },
            { key: 'propostas', label: 'Propostas' },
        ],
    },
    {
        title: 'Operação',
        items: [
            { key: 'automacoes', label: 'Automações' },
            { key: 'trafego', label: 'API de tráfego' },
            { key: 'investimento', label: 'Investimento em anúncios' },
            { key: 'site', label: 'Site' },
        ],
    },
    {
        title: 'Governança',
        items: [
            { key: 'compliance', label: 'Compliance' },
            { key: 'admin', label: 'Administração' },
        ],
    },
];

export const SCREEN_TITLES: Record<ScreenKey, [string, string]> = {
    painel: ['Painel comercial', 'últimos {periodo} · 2 funis ativos'],
    negociacoes: [
        'Funil',
        'visão em quadro ou lista — abra um card para avançar etapa',
    ],
    atividades: ['Minhas atividades', 'tarefas atribuídas a Camila Moraes'],
    calendario: [
        'Calendário',
        'compromissos, tarefas e prazos das negociações',
    ],
    contatos: ['Contatos', 'pessoas, deduplicadas por telefone, e-mail e CPF'],
    empresas: [
        'Empresas',
        'contas jurídicas, contatos e conflito de interesses',
    ],
    propostas: [
        'Propostas e minutas',
        'versionamento imutável e prazos de validade',
    ],
    automacoes: ['Automações', 'gatilhos, condições e revisão ética de texto'],
    trafego: [
        'API de tráfego',
        'entrada de leads pagos e orgânicos, com consentimento na origem',
    ],
    investimento: [
        'Investimento em anúncios',
        'Meta Ads × resultados do CRM — CPL real, custo por contrato e ROAS',
    ],
    site: [
        'Módulos do site',
        'seção “Quem somos” — edite à esquerda, confira à direita',
    ],
    compliance: [
        'Compliance e auditoria',
        'LGPD, sigilo profissional e trilha de eventos',
    ],
    admin: [
        'Administração',
        'tabelas de domínio: finalidades e status de consentimento',
    ],
    configuracoes: [
        'Configurações',
        'perfil, segurança e preferências da conta',
    ],
};
