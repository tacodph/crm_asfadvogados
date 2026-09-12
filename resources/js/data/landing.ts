export type HeroCard = {
    nome: string;
    assunto: string;
    canal: string;
    status: string;
    statusCor: string;
    statusBg: string;
    responsavel: string;
    valor: string;
    previsao: string;
    urgente?: boolean;
};

export type HeroColumn = {
    nome: string;
    qtd: number;
    cor: string;
    cards: HeroCard[];
};

export type Recurso = {
    marca: string;
    titulo: string;
    texto: string;
    iconeBg: string;
    iconeCor: string;
};

export type TelaLinha = {
    titulo: string;
    sub: string;
    valor: string;
    cor: string;
};

export type Tela = {
    titulo: string;
    etiqueta: string;
    linhas: TelaLinha[];
};

export type AbaKey = 'funil' | 'agenda' | 'auditoria';

export type Trava = {
    titulo: string;
    texto: string;
};

export type TermoEtico = {
    re: RegExp;
    nivel: 'bloqueio' | 'atenção';
    titulo: string;
    norma: string;
};

export type FaqItem = {
    pergunta: string;
    resposta: string;
};

export type PlanDefinition = {
    nome: string;
    precoAnual: string;
    precoMensal: string;
    sobConsulta?: boolean;
    destaque: boolean;
    minimo: string;
    resumo: string;
    itens: string[];
    acao: string;
    ctaType: 'self-service' | 'contact-sales';
};

export const heroColunas: HeroColumn[] = [
    {
        nome: 'Novo lead',
        qtd: 1,
        cor: '#333A45',
        cards: [
            {
                nome: 'Joabe Miguel',
                assunto: 'PMDF — Discursiva',
                canal: 'WhatsApp',
                status: 'Análise Pendente',
                statusCor: '#8C6F3F',
                statusBg: '#FBF1DF',
                responsavel: 'Dr. Bruno',
                valor: 'R$ 3,2k',
                previsao: '18/09',
            },
        ],
    },
    {
        nome: 'Triagem',
        qtd: 3,
        cor: '#31496E',
        cards: [
            {
                nome: 'Maria Helena',
                assunto: 'TRF1 — Recurso',
                canal: 'Site',
                status: 'Qualificado',
                statusCor: '#14574F',
                statusBg: '#E7F0EE',
                responsavel: 'Dr. Flávio',
                valor: 'R$ 4,8k',
                previsao: '20/09',
            },
            {
                nome: 'Carlos Eduardo',
                assunto: 'PCDF — Teste físico',
                canal: 'Instagram',
                status: 'Retorno a agendar',
                statusCor: '#3F5E8C',
                statusBg: '#E8EEF6',
                responsavel: 'Dr. Bruno',
                valor: 'R$ 2,9k',
                previsao: '22/09',
            },
        ],
    },
    {
        nome: 'Envio da oferta',
        qtd: 2,
        cor: '#41503A',
        cards: [
            {
                nome: 'Gabriel Rodrigues',
                assunto: 'PMDF — Questões',
                canal: 'WhatsApp',
                status: 'Em Negociação',
                statusCor: '#6B5330',
                statusBg: '#F6EFE4',
                responsavel: 'Dr. Bruno',
                valor: 'R$ 5,1k',
                previsao: '09/09',
                urgente: true,
            },
        ],
    },
    {
        nome: 'Fechamento',
        qtd: 2,
        cor: '#0F4A43',
        cards: [
            {
                nome: 'Ana Beatriz',
                assunto: 'Contrato assinado',
                canal: 'Indicação',
                status: 'Contrato Fechado',
                statusCor: '#14574F',
                statusBg: '#E7F0EE',
                responsavel: 'Dr. Flávio',
                valor: 'R$ 6,5k',
                previsao: '01/09',
            },
        ],
    },
];

export const segmentos: string[] = [
    'Consultivo empresarial',
    'Concursos públicos',
    'Trabalhista',
    'Empresarial bancário',
    'Previdenciário',
];

export const recursos: Recurso[] = [
    {
        marca: 'F',
        titulo: 'Funis por área de atuação',
        texto: 'Etapas, campos obrigatórios, SLA e motivos de perda definidos pelo escritório. Um lead só avança quando a etapa está completa.',
        iconeBg: '#EEF1F6',
        iconeCor: '#31496E',
    },
    {
        marca: 'W',
        titulo: 'WhatsApp, site e indicação num só lugar',
        texto: 'Toda mensagem vira interação no histórico do contato, com deduplicação por telefone, e-mail e CPF/CNPJ.',
        iconeBg: '#E7F0EE',
        iconeCor: 'var(--accent)',
    },
    {
        marca: 'A',
        titulo: 'Follow-up que gera tarefa, não spam',
        texto: 'Sem resposta há três dias abre tarefa para o advogado responsável — o sistema não dispara mensagem em massa por decisão de projeto.',
        iconeBg: '#F6EFE2',
        iconeCor: 'var(--primary)',
    },
    {
        marca: 'P',
        titulo: 'Propostas versionadas',
        texto: 'Minutas a partir de modelo aprovado, honorários dentro da tabela da seccional e versões imutáveis com autor, data e diff.',
        iconeBg: '#F4EFF2',
        iconeCor: '#8C4A6B',
    },
    {
        marca: 'D',
        titulo: 'Distribuição automática',
        texto: 'Round robin, por especialidade ou por carga de trabalho — com verificação de conflito de interesses antes da atribuição.',
        iconeBg: '#F0F2EC',
        iconeCor: '#41503A',
    },
    {
        marca: 'M',
        titulo: 'Métricas que o sócio usa',
        texto: 'Conversão por etapa, ciclo médio, SLA de primeiro contato por colaborador e ranking de motivos de perda.',
        iconeBg: '#F2EFE8',
        iconeCor: '#3C4450',
    },
];

export const abas: { k: AbaKey; titulo: string; texto: string }[] = [
    {
        k: 'funil',
        titulo: 'Negociações',
        texto: 'Quadro por etapa, com valor, SLA e campos obrigatórios visíveis.',
    },
    {
        k: 'agenda',
        titulo: 'Calendário e atividades',
        texto: 'Tarefas, previsões de fechamento e validade de propostas no mesmo mês.',
    },
    {
        k: 'auditoria',
        titulo: 'Compliance e auditoria',
        texto: 'Quem viu o quê, quando — e o que o sistema recusou.',
    },
];

export const telas: Record<AbaKey, Tela> = {
    funil: {
        titulo: 'Negociações',
        etiqueta: 'Funil configurável',
        linhas: [
            {
                titulo: 'Metalúrgica Verano S/A',
                sub: 'Diagnóstico · Camila Moraes',
                valor: 'R$ 48.000',
                cor: '#31496E',
            },
            {
                titulo: 'Construtora Piedade',
                sub: 'Apresentação da solução · 4d na etapa',
                valor: 'R$ 210.000',
                cor: '#41503A',
            },
            {
                titulo: 'Grupo Aldeia Alimentos',
                sub: 'Negociação · proposta v1',
                valor: 'R$ 120.000',
                cor: '#6B5330',
            },
            {
                titulo: 'Têxtil Guaporé Ltda',
                sub: 'Fechamento · assinatura pendente',
                valor: 'R$ 96.000',
                cor: '#0F4A43',
            },
            {
                titulo: 'Cooperativa Vale Verde',
                sub: 'Diagnóstico · consentimento pendente',
                valor: 'R$ 64.000',
                cor: 'var(--primary)',
            },
        ],
    },
    agenda: {
        titulo: 'Calendário e atividades',
        etiqueta: 'Prazos das negociações',
        linhas: [
            {
                titulo: 'Reunião de diagnóstico presencial',
                sub: 'Metalúrgica Verano · hoje',
                valor: '15:00',
                cor: '#8C4A6B',
            },
            {
                titulo: 'Apresentar proposta v3',
                sub: 'Construtora Piedade · amanhã',
                valor: '10:00',
                cor: '#31496E',
            },
            {
                titulo: 'Retomar contato',
                sub: 'Cooperativa Vale Verde · 6d em atraso',
                valor: 'atrasada',
                cor: '#9B3B2F',
            },
            {
                titulo: 'PR-2026-124 vence',
                sub: 'Validade da proposta',
                valor: '17/08',
                cor: 'var(--primary)',
            },
            {
                titulo: 'Coletar assinatura eletrônica',
                sub: 'Têxtil Guaporé · hoje',
                valor: '17:00',
                cor: 'var(--accent)',
            },
        ],
    },
    auditoria: {
        titulo: 'Compliance e auditoria',
        etiqueta: 'Trilha imutável',
        linhas: [
            {
                titulo: 'Avanço de etapa registrado',
                sub: 'Camila Moraes · Diagnóstico → Apresentação',
                valor: '14:02',
                cor: '#31496E',
            },
            {
                titulo: 'Acesso ao caso negado por perfil',
                sub: 'Diego Alencar · consultor comercial',
                valor: '10:19',
                cor: '#9B3B2F',
            },
            {
                titulo: 'Revogação de consentimento',
                sub: 'Aline Cordeiro · contato suspenso',
                valor: '09:40',
                cor: 'var(--primary)',
            },
            {
                titulo: 'Proposta v2 gerada',
                sub: 'Letícia Bonfim · diff registrado',
                valor: '17:33',
                cor: '#41503A',
            },
            {
                titulo: 'Exportação de dados do titular',
                sub: 'Encarregado (DPO) · pedido de acesso',
                valor: '09:05',
                cor: '#0F4A43',
            },
        ],
    },
};

export const travas: Trava[] = [
    {
        titulo: 'Nenhum disparo em massa',
        texto: 'Envios são 1:1, a partir da ficha do lead, com cadência máxima configurável.',
    },
    {
        titulo: 'Sem importação de lista fria',
        texto: 'Todo lead exige origem rastreável: contato iniciado pelo interessado ou indicação nominal registrada.',
    },
    {
        titulo: 'Léxico bloqueado',
        texto: 'Promessa de êxito, superlativo e apelo promocional impedem a publicação do modelo — inclusive para o gestor.',
    },
    {
        titulo: 'Sigilo por arquitetura',
        texto: 'O CRM guarda dado comercial; conteúdo do caso vive no sistema processual e é referenciado por identificador.',
    },
];

export const termos: TermoEtico[] = [
    {
        re: /garant|assegur[ao]|com certeza|100%|êxito certo|ganho de causa|revers(ão|ao) certa/i,
        nivel: 'bloqueio',
        titulo: 'Promessa de resultado',
        norma: 'Vedado prometer ou insinuar êxito — Provimento 205/2021 (CFOAB) e EAOAB art. 34, IV.',
    },
    {
        re: /promo(ção|cional)|desconto|imperd|oferta relâmpago|barato|menor preço|condição especial/i,
        nivel: 'bloqueio',
        titulo: 'Linguagem mercantilista',
        norma: 'Vedada a mercantilização da advocacia e a captação por preço.',
    },
    {
        re: /melhor escritório|nº ?1|n1|líder de mercado|mais premiado|referência absoluta/i,
        nivel: 'bloqueio',
        titulo: 'Autopromoção comparativa',
        norma: 'Vedada publicidade imoderada, comparativa ou de autoexaltação.',
    },
    {
        re: /urgente|últim[ao]s? (vaga|dia)|corre|agora ou nunca|só hoje/i,
        nivel: 'atenção',
        titulo: 'Gatilho de urgência artificial',
        norma: 'Risco de captação desleal — revise o tom antes de publicar.',
    },
];

export const planosDefs: PlanDefinition[] = [
    {
        nome: 'Essencial',
        precoAnual: 'R$ 89',
        precoMensal: 'R$ 109',
        destaque: false,
        minimo: 'mínimo de 3 usuários',
        resumo: 'Para bancas pequenas que precisam parar de perder lead no WhatsApp.',
        itens: [
            'Um funil configurável',
            'Entrada por WhatsApp e site',
            'Histórico unificado e tarefas',
            'Trilha de auditoria completa',
        ],
        acao: 'Criar conta grátis',
        ctaType: 'self-service',
    },
    {
        nome: 'Escritório',
        precoAnual: 'R$ 149',
        precoMensal: 'R$ 179',
        destaque: true,
        minimo: 'mínimo de 5 usuários',
        resumo: 'O plano do escritório de médio porte, com dois ou mais funis em operação.',
        itens: [
            'Funis ilimitados e campos por etapa',
            'Propostas versionadas e minutas',
            'Revisor ético e automações',
            'API de tráfego (Meta, Google, site)',
            'Painel de métricas e motivos de perda',
        ],
        acao: 'Criar conta grátis',
        ctaType: 'self-service',
    },
    {
        nome: 'Banca',
        precoAnual: 'sob consulta',
        precoMensal: 'sob consulta',
        sobConsulta: true,
        destaque: false,
        minimo: 'a partir de 25 usuários',
        resumo: 'Para escritórios com várias unidades, DPO dedicado e exigências próprias.',
        itens: [
            'Instância dedicada',
            'SSO e política de senha própria',
            'Integração com gestão processual',
            'Encarregado e relatórios de LGPD',
            'Gerente de conta nomeado',
        ],
        acao: 'Falar com o time',
        ctaType: 'contact-sales',
    },
];

export const faq: FaqItem[] = [
    {
        pergunta: 'Isso substitui o meu software de gestão processual?',
        resposta:
            'Não, e nem tenta. O Antessala cuida do que vem antes: lead, qualificação, proposta e contrato. Quando o contrato é assinado, ele dispara a abertura do caso no seu sistema processual e guarda apenas o identificador — o conteúdo do caso nunca é copiado para o CRM.',
    },
    {
        pergunta:
            'Como o produto me protege de uma representação por captação irregular?',
        resposta:
            'Por ausência de função, não por aviso. Não existe disparo em massa, importação de lista fria ou enriquecimento de contatos de terceiros. Todo lead exige origem rastreável de contato iniciado pelo interessado, e todo texto automatizado passa pelo revisor ético com parecer versionado — que serve como prova de diligência.',
    },
    {
        pergunta: 'Quem consegue ver o conteúdo dos casos?',
        resposta:
            'O advogado responsável e o sócio da área. O consultor comercial vê o pipeline e nada do caso; o encarregado de dados vê metadados e a trilha de auditoria. Acesso excepcional exige justificativa, notifica o responsável e fica registrado.',
    },
    {
        pergunta:
            'Dá para atender concursos e consultivo empresarial no mesmo sistema?',
        resposta:
            'É exatamente para isso que os funis são independentes. Volume de pessoa física roda com SLA de 15 minutos e triagem enxuta; consultivo empresarial roda com diagnóstico, conflito de interesses e proposta versionada. Cada funil tem etapas, campos e motivos de perda próprios.',
    },
    {
        pergunta: 'Meus dados ficam onde? E se eu quiser sair?',
        resposta:
            'Hospedagem em região brasileira, criptografia em trânsito e em repouso, backup diário com teste trimestral de restauração. Ao cancelar, você exporta tudo em formato aberto e a base é eliminada conforme a política de retenção acordada.',
    },
];

export const tamanhosTime = [
    'Até 2 pessoas',
    '3 a 8 pessoas',
    '9 ou mais',
] as const;

export const mensagemRevisorPadrao =
    'Olá! Podemos garantir a reversão da sua eliminação e ainda estamos com uma condição promocional imperdível neste mês.';
