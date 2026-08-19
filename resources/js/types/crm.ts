export type DrawerField = {
    label: string;
    valor: string;
};

export type ConsentimentoLinha = {
    finalidade: string;
    estado: string;
    cor: string;
};

export type NegociacaoResumo = {
    id: number;
    titulo: string;
    etapa: string;
    responsavel: string;
    valorFmt: string;
};

export type ContatoDrawer = {
    nome: string;
    subtitulo: string;
    campos: DrawerField[];
    consentimentos: ConsentimentoLinha[];
    negociacoes: NegociacaoResumo[];
};

export type ContatoLinha = {
    id: number;
    nome: string;
    cargo: string;
    email: string;
    telefone: string;
    cpf: string | null;
    cnpj: string | null;
    dedupe: string;
    contexto: string;
    canal: string;
    consent: string;
    consentBg: string;
    consentCor: string;
    negocios: string;
    drawer: ContatoDrawer;
};

export type EmpresaDrawer = {
    nome: string;
    cnpj: string;
    cidade: string;
    campos: DrawerField[];
    conflitoCor: string;
    conflitoBg: string;
    conflitoBorda: string;
    conflitoTexto: string;
    contatos: ContatoLinha[];
    negociacoes: NegociacaoResumo[];
};

export type EmpresaLinha = {
    id: number;
    nome: string;
    cnpj: string;
    setor: string;
    contatosResumo: string;
    conflito: string;
    conflitoBg: string;
    conflitoCor: string;
    valorFmt: string;
    abertas: string;
    drawer: EmpresaDrawer;
};

export type CrmCounts = {
    contatos?: string;
    empresas?: string;
    negociacoes?: string;
};

export type TimelineNegociacao = {
    titulo: string;
    descricao: string;
    quando: string;
    autor: string;
    cor: string;
};

export type NegociacaoDrawer = {
    nome: string;
    subtitulo: string;
    canal: string;
    canalCor: string;
    responsavel: string;
    consent: string;
    consentBg: string;
    consentCor: string;
    campos: DrawerField[];
    historicos: TimelineNegociacao[];
    avancandoLabel: string;
};

export type NegociacaoLinha = {
    id: number;
    funilId: number;
    etapaId: number;
    nome: string;
    assunto: string;
    conta: string;
    canal: string;
    canalCor: string;
    etapa: string;
    funilNome: string;
    responsavel: string;
    iniciais: string;
    valor: number;
    valorFmt: string;
    previsao: string;
    tarefa: string;
    tarefaCor: string;
    slaLabel: string;
    slaCor: string;
    slaBg: string;
    drawer: NegociacaoDrawer;
};

export type FunilEtapaView = {
    id: number;
    nome: string;
    sla: string;
    obrigatorio: string;
    corBg: string;
    corFg: string;
    corSuave: string;
};

export type FunilView = {
    id: number;
    slug: string;
    nome: string;
    distribuicao: string;
    etapas: FunilEtapaView[];
};
