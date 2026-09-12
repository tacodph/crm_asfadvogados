export type ContaAnuncio = {
    id: number;
    nome: string;
    ad_account_id: string;
    business_id: string | null;
    token_mascarado: string;
    token_definido: boolean;
    token_valido: boolean | null;
    token_scopes: string[];
    escreve_habilitado: boolean;
    moeda: string | null;
    conta_status: string | null;
    estrutura_sincronizada_em: string | null;
    insights_sincronizados_em: string | null;
    atualizado_por: string | null;
    gerenciador_url: string;
};

export type InvestimentoKpi = {
    label: string;
    value: string;
    secundario?: string;
};

export type InvestimentoLinha = {
    nivel: string;
    objeto_id: number;
    meta_id: string | null;
    nome: string;
    effective_status: string | null;
    investido_centavos: number;
    impressoes: number;
    cliques: number;
    cliques_link: number;
    alcance: number;
    cpm_centavos: number;
    ctr: number;
    leads_meta: number;
    leads_crm: number;
    cpl_meta_centavos: number;
    cpl_crm_centavos: number;
    reunioes: number;
    custo_reuniao_centavos: number;
    contratos: number;
    custo_contrato_centavos: number;
    receita_centavos: number;
    roas: number;
};

export type InvestimentoSeriePonto = {
    referencia: string;
    investido_centavos: number;
    leads_crm: number;
};

export type InvestimentoFiltros = {
    periodo: number;
    conta: number | null;
    nivel: string;
    campanha: number | null;
    conjunto: number | null;
};

export type InvestimentoOpcoes = {
    periodos: number[];
    niveis: { value: string; label: string }[];
};

export type ContaStatusAoVivo = Record<
    number,
    {
        ok: boolean;
        nome: string | null;
        moeda: string | null;
        conta_status: string | number | null;
        escopos: string[];
        mensagem: string | null;
    }
>;
