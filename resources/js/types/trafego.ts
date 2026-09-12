export type EventoLinha = {
    id: number;
    campanha: string;
    campanha_slug: string | null;
    event_name: string;
    event_name_label: string;
    event_id: string;
    status: string;
    status_label: string;
    status_cor: string;
    event_time: string | null;
    action_source: string;
    negociacao_id: number | null;
    contato: string | null;
    tentativas: number;
    http_status: number | null;
    events_received: number | null;
    fbtrace_id: string | null;
    error_code: string | null;
    error_message: string | null;
    motivo_descarte: string | null;
    is_teste: boolean;
    enviado_em: string | null;
    created_at: string | null;
};

export type EventoDetalhe = EventoLinha & {
    request_payload: Record<string, unknown>;
    response_body: Record<string, unknown> | null;
};

export type EventosPaginados = {
    data: EventoLinha[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type EventoFiltros = {
    campanha: string;
    status: string;
    event_name: string;
    periodo: number;
    busca: string;
};

export type EventoOpcoes = {
    campanhas: { slug: string; nome: string }[];
    status: { value: string; label: string }[];
    eventos: { value: string; label: string }[];
    periodos: number[];
};

export type DiagnosticoCampanha = {
    slug: string;
    nome: string;
    pixel_id: string;
    link_events_manager: string;
    ultima_sincronizacao: string | null;
    ultimo_evento_em: string | null;
    token_valido: boolean | null;
    totais: {
        enviados: number;
        com_erro: number;
        descartados: number;
        deduplicados_estimados: number;
    };
    emq_proxy: {
        em: number;
        ph: number;
        fbp_fbc: number;
        ip_ua: number;
        base: number;
    };
    erros_por_codigo: Record<string, number>;
    serie_30d: {
        referencia: string | null;
        servidor: number | null;
        navegador: number | null;
        deduplicados: number | null;
    }[];
};

export type DiagnosticoMeta = Record<
    string,
    {
        name: string | null;
        last_fired_time: string | null;
        pixel_ok: boolean;
        leitura_pixel_ok: boolean;
        stats: Record<string, unknown>;
    }
>;
