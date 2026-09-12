<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { dashboard } from '@/routes';
import {
    ArrowDown,
    ArrowUpRight,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    ExternalLink,
    Filter,
    Globe,
    Layers,
    Lightbulb,
    MapPin,
    Megaphone,
    MessageSquare,
    Phone,
    Search,
    ShieldCheck,
    Sparkles,
    Target,
    User,
    Users,
    X,
    Zap,
} from '@lucide/vue';

type Kpi = {
    label: string;
    value: string;
    delta: string;
    deltaColor: string;
};

type ConversaoEtapa = {
    etapa: string;
    pct: number;
    cor: string;
    info: string;
};

type Canal = {
    nome: string;
    qtd: number;
    pct: number;
    cor: string;
};

type Perda = {
    motivo: string;
    qtd?: number;
    pct: number;
};

type Colaborador = {
    nome: string;
    leads: number;
    fechados: number;
    conv: string;
    sla: string;
    slaCor: string;
};

type ConversaoFunil = {
    nome: string;
    leads: number;
    fechados: number;
    pct: number;
    cor: string;
    info: string;
};

type ConversaoNegociacao = {
    rotulo: string;
    qtd: number;
    pct: number;
    cor: string;
    info: string;
    valorFmt: string;
};

type MotivoEncerrado = {
    motivo: string;
    qtd: number;
    pct: number;
    cor: string;
};

type ContinuidadeEncerrado = {
    nome: string;
    qtd: number;
    pct: number;
    cor: string;
};

type QualificacaoEncerrado = {
    nome: string;
    qtd: number;
    pct: number;
    cor: string;
};

type LeadEncerrado = {
    id: number;
    assunto: string;
    contatoNome: string;
    contatoTelefone: string | null;
    responsavelNome: string;
    motivo: string;
    continuidade: string;
    qualificacao: string;
    qualificacaoCorFundo: string;
    qualificacaoCorTexto: string;
    observacoes: string | null;
    data: string;
};

type AtendimentosEncerrados = {
    total: number;
    taxaEncerrados: number;
    totalQualificados: number;
    totalDesqualificados: number;
    totalSemResposta: number;
    motivos: MotivoEncerrado[];
    continuidade: ContinuidadeEncerrado[];
    qualificacao: QualificacaoEncerrado[];
    leads: LeadEncerrado[];
};

type TopDdd = {
    ddd: string;
    regiao: string;
    qtd: number;
    pct: number;
    cor: string;
};

type ClusterConcurso = {
    nome: string;
    total: number;
    qualificados: number;
    taxaQualificacao: number;
    fasePrincipal: string;
    cor: string;
};

type FaseDemanda = {
    fase: string;
    qtd: number;
    pct: number;
    cor: string;
};

type DiaPico = {
    dia: string;
    qtd: number;
    pct: number;
};

type PublicoSugerido = {
    nome: string;
    tipo: string;
    descricao: string;
};

type GeotargetingMeta = {
    conjunto: string;
    localizacao: string;
    ddds: string;
    justificativa: string;
};

type GanchoCriativo = {
    titulo: string;
    concursoOuFase: string;
    gancho: string;
    dor: string;
    cta: string;
};

type RecomendacoesMeta = {
    objetivo: string;
    canal: string;
    faixaEtaria: string;
    publicosSugeridos: PublicoSugerido[];
    geotargeting: GeotargetingMeta[];
    interesses: string[];
    ganchosCriativos: GanchoCriativo[];
};

type PerfilLeadsMeta = {
    total: number;
    totalQualificados: number;
    taxaQualificacao: number;
    totalComTelefone: number;
    taxaComTelefone: number;
    topDdds: TopDdd[];
    clustersConcurso: ClusterConcurso[];
    fasesDemanda: FaseDemanda[];
    diasPico: DiaPico[];
    recomendacoesMeta: RecomendacoesMeta;
};

const props = defineProps<{
    periodo: string;
    periodos: string[];
    kpis: Kpi[];
    conversao: {
        funil: string;
        etapas: ConversaoEtapa[];
    };
    conversaoFunis: ConversaoFunil[];
    conversaoNegociacoes: ConversaoNegociacao[];
    canais: Canal[];
    perdas: Perda[];
    equipe: Colaborador[];
    atendimentosEncerrados?: AtendimentosEncerrados;
    perfilLeadsMeta?: PerfilLeadsMeta;
}>();

const selecionarPeriodo = (item: string): void => {
    if (item === props.periodo) {
        return;
    }

    router.get(
        dashboard.url({ query: { periodo: item } }),
        {},
        { preserveState: true, preserveScroll: true },
    );
};

watch(
    () => props.periodo,
    (valor) => {
        window.dispatchEvent(new CustomEvent('crm:periodo', { detail: valor }));
    },
    { immediate: true },
);

// ESTADO DO CARD ANALÍTICO DE ATENDIMENTOS ENCERRADOS
const cardEncerradosRef = ref<HTMLElement | null>(null);
const abaEncerrados = ref<'motivos' | 'leads'>('motivos');
const termoBusca = ref('');
const filtroMotivo = ref('todos');
const filtroQualificacao = ref('todos');
const apenasComObservacoes = ref(false);
const limiteExibicao = ref(15);

const rolarParaEncerrados = () => {
    if (cardEncerradosRef.value) {
        cardEncerradosRef.value.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }
};

const selecionarMotivoEIrParaLeads = (motivo: string) => {
    filtroMotivo.value = motivo;
    abaEncerrados.value = 'leads';
    rolarParaEncerrados();
};

const limparFiltros = () => {
    termoBusca.value = '';
    filtroMotivo.value = 'todos';
    filtroQualificacao.value = 'todos';
    apenasComObservacoes.value = false;
};

const filtrosAtivos = computed(() => {
    return (
        termoBusca.value.trim() !== '' ||
        filtroMotivo.value !== 'todos' ||
        filtroQualificacao.value !== 'todos' ||
        apenasComObservacoes.value
    );
});

const leadsFiltrados = computed(() => {
    const lista = props.atendimentosEncerrados?.leads ?? [];
    const busca = termoBusca.value.trim().toLowerCase();

    return lista.filter((lead) => {
        if (
            filtroMotivo.value !== 'todos' &&
            lead.motivo !== filtroMotivo.value
        ) {
            return false;
        }

        if (filtroQualificacao.value !== 'todos') {
            const statusLower = lead.qualificacao.toLowerCase();
            if (
                filtroQualificacao.value === 'qualificado' &&
                (!statusLower.includes('qualificado') ||
                    statusLower.includes('desqualificado'))
            ) {
                return false;
            }
            if (
                filtroQualificacao.value === 'desqualificado' &&
                !statusLower.includes('desqualificado')
            ) {
                return false;
            }
        }

        if (
            apenasComObservacoes.value &&
            (!lead.observacoes || lead.observacoes.trim() === '')
        ) {
            return false;
        }

        if (busca !== '') {
            const matchNome = lead.contatoNome.toLowerCase().includes(busca);
            const matchAssunto = lead.assunto.toLowerCase().includes(busca);
            const matchResp = lead.responsavelNome
                .toLowerCase()
                .includes(busca);
            const matchMotivo = lead.motivo.toLowerCase().includes(busca);
            const matchObs =
                lead.observacoes?.toLowerCase().includes(busca) ?? false;
            const matchTelefone =
                lead.contatoTelefone?.toLowerCase().includes(busca) ?? false;

            return (
                matchNome ||
                matchAssunto ||
                matchResp ||
                matchMotivo ||
                matchObs ||
                matchTelefone
            );
        }

        return true;
    });
});

const leadsVisiveis = computed(() => {
    return leadsFiltrados.value.slice(0, limiteExibicao.value);
});

const carregarMais = () => {
    limiteExibicao.value += 15;
};

const carregarTodos = () => {
    limiteExibicao.value = leadsFiltrados.value.length;
};

// ESTADO DO CARD ANALÍTICO DE INTELIGÊNCIA META ADS
const abaMeta = ref<'segmentacao' | 'setup' | 'criativos'>('segmentacao');
const copiadoId = ref<string | null>(null);

const copiarTexto = async (texto: string, id: string) => {
    try {
        await navigator.clipboard.writeText(texto);
        copiadoId.value = id;
        setTimeout(() => {
            if (copiadoId.value === id) {
                copiadoId.value = null;
            }
        }, 2000);
    } catch (e) {
        console.error('Falha ao copiar:', e);
    }
};

const copiarParametrosCompletosMeta = () => {
    const meta = props.perfilLeadsMeta;
    if (!meta) return;

    const dddsTexto = meta.topDdds
        .map((d) => `${d.ddd} (${d.regiao}: ${d.qtd} leads, ${d.pct}%)`)
        .join(', ');
    const concursosTexto = meta.clustersConcurso
        .map(
            (c) =>
                `- ${c.nome}: ${c.total} leads (${c.taxaQualificacao}% qualificados | Fase principal: ${c.fasePrincipal})`,
        )
        .join('\n');
    const fasesTexto = meta.fasesDemanda
        .map((f) => `- ${f.fase}: ${f.qtd} leads (${f.pct}%)`)
        .join('\n');
    const conjuntosTexto = meta.recomendacoesMeta.geotargeting
        .map(
            (g) =>
                `* ${g.conjunto}\n  Local: ${g.localizacao} | DDDs: ${g.ddds}\n  Justificativa: ${g.justificativa}`,
        )
        .join('\n\n');
    const criativosTexto = meta.recomendacoesMeta.ganchosCriativos
        .map(
            (cr) =>
                `* ${cr.titulo} (${cr.concursoOuFase}):\n  Gancho: "${cr.gancho}"\n  Dor: ${cr.dor}\n  CTA: ${cr.cta}`,
        )
        .join('\n\n');

    const resumo = `=== INTELIGÊNCIA DE AUDIÊNCIA & SETUP META ADS (ASF ADVOGADOS) ===

1. PERSONA & INDICADORES DA BASE:
- Total Analisado: ${meta.total} leads (100% Mobile/WhatsApp)
- Leads Qualificados: ${meta.totalQualificados} (${meta.taxaQualificacao}% de qualificação jurídica)
- Telefones Celulares Válidos: ${meta.totalComTelefone} (${meta.taxaComTelefone}% prontos para CAPI / Lookalike)
- Faixa Etária Recomendada: ${meta.recomendacoesMeta.faixaEtaria}
- Objetivo Recomendado: ${meta.recomendacoesMeta.objetivo}
- Canal de Entrada: ${meta.recomendacoesMeta.canal}

2. GEOTARGETING & PRINCIPAIS DDDS:
${dddsTexto}

3. CLUSTERS POR CONCURSO & QUALIFICAÇÃO:
${concursosTexto}

4. FASES PROCESSUAIS MAIS DEMANDADAS:
${fasesTexto}

5. ESTRUTURA RECOMENDADA DE CONJUNTOS DE ANÚNCIOS (AD SETS):
${conjuntosTexto}

6. INTERESSES SUGERIDOS NO META ADS MANAGER:
${meta.recomendacoesMeta.interesses.join(', ')}

7. GANCHOS DE CRIATIVO BASEADOS NAS DORES REAIS:
${criativosTexto}
`;

    copiarTexto(resumo, 'briefing-completo');
};
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-[18px]">
        <!-- SELETOR DE PERÍODOS -->
        <div class="flex gap-2">
            <button
                v-for="item in periodos"
                :key="item"
                type="button"
                class="cursor-pointer rounded-full border px-3 py-1.5 text-xs transition-colors"
                :class="
                    periodo === item
                        ? 'border-primary bg-primary text-white'
                        : 'border-border text-muted-foreground bg-white hover:bg-[#FAF8F5]'
                "
                @click="selecionarPeriodo(item)"
            >
                {{ item }}
            </button>
        </div>

        <!-- KPIS PRINCIPAIS -->
        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="border-border flex flex-col gap-[7px] rounded-[10px] border bg-white px-[17px] py-4"
            >
                <div
                    class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-[0.12em]"
                >
                    {{ kpi.label }}
                </div>
                <div
                    class="font-[family-name:var(--font-crm-display)] text-[32px] font-medium leading-none tracking-[-0.02em]"
                >
                    {{ kpi.value }}
                </div>
                <div class="text-[11.5px]" :style="{ color: kpi.deltaColor }">
                    {{ kpi.delta }}
                </div>
            </div>
        </div>

        <!-- CONVERSÃO POR FUNIL & NEGOCIAÇÃO -->
        <div class="grid gap-3.5 lg:grid-cols-2">
            <div
                class="border-border rounded-[10px] border bg-white px-5 pb-5 pt-[18px]"
            >
                <h2
                    class="mb-4 mt-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Conversão por funil
                </h2>
                <p
                    v-if="conversaoFunis.length === 0"
                    class="text-muted-foreground m-0 text-[12.5px]"
                >
                    Nenhum funil com negociações no período.
                </p>
                <div v-else class="flex flex-col gap-[13px]">
                    <div
                        v-for="item in conversaoFunis"
                        :key="item.nome"
                        class="flex flex-col gap-[5px]"
                    >
                        <div class="flex justify-between text-[12.5px]">
                            <span class="text-muted-foreground">{{
                                item.nome
                            }}</span>
                            <span
                                class="text-muted-foreground font-[family-name:var(--font-crm-mono)]"
                            >
                                {{ item.info }}
                            </span>
                        </div>
                        <div
                            class="h-[9px] overflow-hidden rounded-full bg-[#F2EFE8]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    background: item.cor,
                                    width: `${item.pct}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="border-border rounded-[10px] border bg-white px-5 pb-5 pt-[18px]"
            >
                <h2
                    class="mb-4 mt-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Conversão por negociação
                </h2>
                <p
                    v-if="conversaoNegociacoes.length === 0"
                    class="text-muted-foreground m-0 text-[12.5px]"
                >
                    Nenhuma negociação no período.
                </p>
                <div v-else class="flex flex-col gap-[13px]">
                    <div
                        v-for="item in conversaoNegociacoes"
                        :key="item.rotulo"
                        class="flex flex-col gap-[5px]"
                    >
                        <div
                            class="flex items-baseline justify-between gap-2 text-[12.5px]"
                        >
                            <span class="text-muted-foreground">{{
                                item.rotulo
                            }}</span>
                            <span
                                class="text-muted-foreground font-[family-name:var(--font-crm-mono)]"
                            >
                                {{ item.info }} · {{ item.valorFmt }}
                            </span>
                        </div>
                        <div
                            class="h-[9px] overflow-hidden rounded-full bg-[#F2EFE8]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    background: item.cor,
                                    width: `${item.pct}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONVERSÃO POR ETAPA & ORIGEM DOS LEADS -->
        <div class="grid gap-3.5 lg:grid-cols-[1.35fr_1fr]">
            <div
                class="border-border rounded-[10px] border bg-white px-5 pb-5 pt-[18px]"
            >
                <div class="mb-4 flex items-baseline justify-between">
                    <h2
                        class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                    >
                        Conversão por etapa
                    </h2>
                    <span class="text-muted-foreground text-[11.5px]">{{
                        conversao.funil
                    }}</span>
                </div>
                <p
                    v-if="conversao.etapas.length === 0"
                    class="text-muted-foreground m-0 text-[12.5px]"
                >
                    Nenhuma negociação no período para este funil.
                </p>
                <div v-else class="flex flex-col gap-[13px]">
                    <div
                        v-for="item in conversao.etapas"
                        :key="item.etapa"
                        class="flex flex-col gap-[5px]"
                    >
                        <div class="flex justify-between text-[12.5px]">
                            <span class="text-muted-foreground">{{
                                item.etapa
                            }}</span>
                            <span
                                class="text-muted-foreground font-[family-name:var(--font-crm-mono)]"
                            >
                                {{ item.info }}
                            </span>
                        </div>
                        <div
                            class="h-[9px] overflow-hidden rounded-full bg-[#F2EFE8]"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    background: item.cor,
                                    width: `${item.pct}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="border-border rounded-[10px] border bg-white px-5 pb-5 pt-[18px]"
            >
                <h2
                    class="mb-4 mt-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Origem dos leads
                </h2>
                <p
                    v-if="canais.length === 0"
                    class="text-muted-foreground m-0 text-[12.5px]"
                >
                    Nenhum lead no período.
                </p>
                <div v-else class="flex flex-col gap-3">
                    <div
                        v-for="canal in canais"
                        :key="canal.nome"
                        class="flex items-center gap-2.5"
                    >
                        <span
                            class="h-2 w-2 rounded-sm"
                            :style="{ background: canal.cor }"
                        />
                        <span
                            class="text-muted-foreground w-[108px] text-[12.5px]"
                        >
                            {{ canal.nome }}
                        </span>
                        <div
                            class="h-[7px] flex-1 overflow-hidden rounded-full bg-[#F2EFE8]"
                        >
                            <div
                                class="h-full"
                                :style="{
                                    background: canal.cor,
                                    width: `${canal.pct}%`,
                                }"
                            />
                        </div>
                        <span
                            class="text-muted-foreground w-[30px] text-right font-[family-name:var(--font-crm-mono)] text-[11.5px]"
                        >
                            {{ canal.qtd }}
                        </span>
                    </div>
                </div>
                <p
                    class="border-border text-muted-foreground mb-0 mt-4 border-t pt-3 text-[11.5px] leading-normal"
                >
                    Todos os canais são de
                    <strong class="text-muted-foreground font-medium"
                        >entrada passiva</strong
                    >. O CRM não permite importar listas frias — exigência do
                    Provimento 205/2021.
                </p>
            </div>
        </div>

        <!-- MOTIVOS DE PERDA & PRODUTIVIDADE -->
        <div class="grid gap-3.5 lg:grid-cols-[1fr_1.35fr]">
            <div
                class="border-border rounded-[10px] border bg-white px-5 pb-5 pt-[18px]"
            >
                <div class="mb-3.5 flex items-center justify-between">
                    <h2
                        class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                    >
                        Motivos de perda
                    </h2>
                    <span
                        v-if="
                            atendimentosEncerrados &&
                            atendimentosEncerrados.total > 0
                        "
                        class="rounded-full bg-[#FDF3F2] px-2 py-0.5 font-[family-name:var(--font-crm-mono)] text-[11px] font-medium text-[#9B3B2F]"
                    >
                        {{ atendimentosEncerrados.total }} encerrados
                    </span>
                </div>
                <p
                    v-if="perdas.length === 0"
                    class="text-muted-foreground m-0 text-[12.5px]"
                >
                    Nenhum motivo de perda registrado no período.
                </p>
                <div v-else class="flex flex-col gap-2.5">
                    <div
                        v-for="perda in perdas"
                        :key="perda.motivo"
                        class="group flex cursor-pointer items-center justify-between gap-2.5 border-b border-[#F2EFE8] pb-[9px] transition-colors hover:bg-[#FAF8F5]"
                        title="Clique para ver os leads deste motivo"
                        @click="selecionarMotivoEIrParaLeads(perda.motivo)"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <span
                                class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#9B3B2F]"
                            />
                            <span
                                class="text-muted-foreground group-hover:text-foreground truncate text-[12.5px]"
                            >
                                {{ perda.motivo }}
                            </span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                v-if="perda.qtd !== undefined"
                                class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[11px]"
                            >
                                {{ perda.qtd }}
                            </span>
                            <span
                                class="font-[family-name:var(--font-crm-mono)] text-xs font-medium text-[#9B3B2F]"
                            >
                                {{ perda.pct }}%
                            </span>
                        </div>
                    </div>

                    <button
                        v-if="
                            atendimentosEncerrados &&
                            atendimentosEncerrados.total > 0
                        "
                        type="button"
                        class="mt-2 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5] py-2 text-xs font-medium text-[#9B3B2F] transition-colors hover:border-[#E8D0CC] hover:bg-[#FDF3F2]"
                        @click="rolarParaEncerrados"
                    >
                        <span
                            >Ver diagnóstico completo e relatos ({{
                                atendimentosEncerrados.total
                            }}
                            leads)</span
                        >
                        <ArrowDown class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <div
                class="border-border rounded-[10px] border bg-white px-5 pb-5 pt-[18px]"
            >
                <h2
                    class="mb-3.5 mt-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Produtividade por colaborador
                </h2>
                <p
                    v-if="equipe.length === 0"
                    class="text-muted-foreground m-0 text-[12.5px]"
                >
                    Nenhum responsável com leads no período.
                </p>
                <table v-else class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th
                                class="text-muted-foreground pb-[9px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Colaborador
                            </th>
                            <th
                                class="text-muted-foreground pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Leads
                            </th>
                            <th
                                class="text-muted-foreground pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Fechados
                            </th>
                            <th
                                class="text-muted-foreground pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Conversão
                            </th>
                            <th
                                class="text-muted-foreground pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                SLA 1º contato
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pessoa in equipe" :key="pessoa.nome">
                            <td
                                class="text-foreground border-t border-[#F2EFE8] py-[9px] text-[12.5px]"
                            >
                                {{ pessoa.nome }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-right font-[family-name:var(--font-crm-mono)] text-xs"
                            >
                                {{ pessoa.leads }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-right font-[family-name:var(--font-crm-mono)] text-xs"
                            >
                                {{ pessoa.fechados }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-right font-[family-name:var(--font-crm-mono)] text-xs"
                            >
                                {{ pessoa.conv }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-right font-[family-name:var(--font-crm-mono)] text-xs"
                                :style="{ color: pessoa.slaCor }"
                            >
                                {{ pessoa.sla }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- CARD ANALÍTICO EXCLUSIVO: PERFIL DAS LEADS & BASE PARA CAMPANHAS META ADS -->
        <!-- ========================================================================= -->
        <div
            id="perfil-leads-meta"
            class="border-border shadow-xs rounded-[10px] border bg-white px-5 pb-6 pt-5 transition-all"
        >
            <!-- CABEÇALHO DO CARD -->
            <div
                class="flex flex-col gap-3 border-b border-[#F2EFE8] pb-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-[#EFF4FA] px-2.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[10.5px] font-medium text-[#31496E]"
                        >
                            <Target class="h-3.5 w-3.5 text-[#31496E]" />
                            Meta Ads Intelligence · Segmentação &amp; Persona
                        </span>
                        <span
                            class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-xs"
                        >
                            Base: {{ perfilLeadsMeta?.total ?? 0 }} leads
                            analisados · 100% Mobile &amp; WhatsApp
                        </span>
                    </div>
                    <h2
                        class="text-foreground mb-0.5 mt-1.5 font-[family-name:var(--font-crm-display)] text-[20px] font-medium"
                    >
                        Perfil das Leads &amp; Inteligência para Campanhas Meta
                        Ads
                    </h2>
                    <p class="text-muted-foreground m-0 text-[12.5px]">
                        Mapeamento demográfico, geolocalização por DDD, clusters
                        de interesse e recomendações táticas para criar públicos
                        e anúncios no Gerenciador da Meta.
                    </p>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 self-start sm:self-auto"
                >
                    <!-- CONTROLE DE ABAS -->
                    <div
                        class="border-border flex rounded-lg border bg-[#F8F7F4] p-0.5 text-xs"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-1 font-medium transition-all"
                            :class="
                                abaMeta === 'segmentacao'
                                    ? 'text-foreground shadow-xs bg-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="abaMeta = 'segmentacao'"
                        >
                            Segmentação &amp; DDDs
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-1 font-medium transition-all"
                            :class="
                                abaMeta === 'setup'
                                    ? 'text-foreground shadow-xs bg-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="abaMeta = 'setup'"
                        >
                            Setup no Gerenciador Meta
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-1 font-medium transition-all"
                            :class="
                                abaMeta === 'criativos'
                                    ? 'text-foreground shadow-xs bg-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="abaMeta = 'criativos'"
                        >
                            Ganchos de Criativo &amp; OAB
                        </button>
                    </div>

                    <!-- BOTÃO COPIAR PARÂMETROS COMPLETOS -->
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-[#31496E]/20 bg-[#EFF4FA] px-3 py-1 text-xs font-medium text-[#31496E] transition-all hover:bg-[#E3ECF7]"
                        @click="copiarParametrosCompletosMeta"
                    >
                        <Check
                            v-if="copiadoId === 'briefing-completo'"
                            class="h-3.5 w-3.5 text-[#14574F]"
                        />
                        <Copy v-else class="h-3.5 w-3.5" />
                        <span>{{
                            copiadoId === 'briefing-completo'
                                ? 'Copiado para Área de Transferência!'
                                : 'Copiar Parâmetros da Campanha'
                        }}</span>
                    </button>

                    <Link
                        href="/crm/trafego"
                        class="border-border text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded-lg border bg-white px-2.5 py-1 text-xs transition-colors hover:bg-[#FAF8F5]"
                    >
                        <span>Tráfego &amp; CAPI</span>
                        <ExternalLink class="h-3 w-3" />
                    </Link>
                </div>
            </div>

            <!-- MINI-KPIS RESUMO DA AUDIÊNCIA -->
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5] p-3"
                >
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider"
                        >Base Total Analisada</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="text-foreground font-[family-name:var(--font-crm-display)] text-2xl font-semibold"
                        >
                            {{ perfilLeadsMeta?.total ?? 0 }}
                        </span>
                        <span class="text-muted-foreground text-xs">leads</span>
                    </div>
                    <span class="text-muted-foreground text-[11px]">
                        100% entrada via WhatsApp / Mobile
                    </span>
                </div>

                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#E0EBE6] bg-[#F4F8F6] p-3"
                >
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider text-[#14574F]"
                        >Leads Qualificados</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="font-[family-name:var(--font-crm-display)] text-2xl font-semibold text-[#14574F]"
                        >
                            {{ perfilLeadsMeta?.totalQualificados ?? 0 }}
                        </span>
                        <span class="text-xs text-[#14574F]"
                            >leads ({{
                                perfilLeadsMeta?.taxaQualificacao ?? 0
                            }}%)</span
                        >
                    </div>
                    <span class="text-[11px] text-[#14574F]/80">
                        Perfil apto para ação jurídica
                    </span>
                </div>

                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#E1EAF4] bg-[#F2F6FB] p-3"
                >
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider text-[#31496E]"
                        >Telefones Válidos (CAPI)</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="font-[family-name:var(--font-crm-display)] text-2xl font-semibold text-[#31496E]"
                        >
                            {{ perfilLeadsMeta?.taxaComTelefone ?? 0 }}%
                        </span>
                        <span class="text-xs text-[#31496E]">da base</span>
                    </div>
                    <span class="text-[11px] text-[#31496E]/80">
                        Prontos para Lookalike 1% e CAPI
                    </span>
                </div>

                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5] p-3"
                >
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider"
                        >Cluster Dominante</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="text-foreground font-[family-name:var(--font-crm-display)] text-xl font-semibold"
                        >
                            Policial &amp; Federal
                        </span>
                    </div>
                    <span class="text-muted-foreground text-[11px]">
                        PMDF, PCRS e CNU (&gt;80% do volume)
                    </span>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- ABA 1: SEGMENTAÇÃO & GEOTARGETING (DDDs)   -->
            <!-- ========================================== -->
            <div
                v-if="abaMeta === 'segmentacao'"
                class="mt-5 grid gap-5 lg:grid-cols-[1.1fr_1.3fr]"
            >
                <!-- COLUNA 1: GEOLOCALIZAÇÃO & CONCENTRAÇÃO DE DDDS -->
                <div
                    class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5]/40 p-4"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#F2EFE8] pb-2.5"
                    >
                        <div>
                            <div class="flex items-center gap-1.5">
                                <MapPin class="h-4 w-4 text-[#31496E]" />
                                <h3
                                    class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                                >
                                    Top Regiões &amp; DDDs Telefônicos
                                </h3>
                            </div>
                            <span class="text-muted-foreground text-[11.5px]">
                                Concentração geográfica real para direcionamento
                                no Meta Ads
                            </span>
                        </div>

                        <button
                            type="button"
                            class="border-border text-muted-foreground hover:text-foreground inline-flex cursor-pointer items-center gap-1 rounded-md border bg-white px-2 py-1 text-[11px] hover:bg-[#FAF8F5]"
                            title="Copiar DDDs para o Gerenciador da Meta"
                            @click="
                                copiarTexto(
                                    perfilLeadsMeta?.topDdds
                                        .map((d) => d.ddd)
                                        .join(', ') ?? '',
                                    'ddds-lista',
                                )
                            "
                        >
                            <Check
                                v-if="copiadoId === 'ddds-lista'"
                                class="h-3 w-3 text-[#14574F]"
                            />
                            <Copy v-else class="h-3 w-3" />
                            <span>{{
                                copiadoId === 'ddds-lista'
                                    ? 'DDDs copiados!'
                                    : 'Copiar DDDs'
                            }}</span>
                        </button>
                    </div>

                    <div
                        v-if="
                            !perfilLeadsMeta ||
                            perfilLeadsMeta.topDdds.length === 0
                        "
                        class="text-muted-foreground py-6 text-center text-xs"
                    >
                        Nenhum telefone registrado para extração de DDD.
                    </div>

                    <div v-else class="flex flex-col gap-2.5">
                        <div
                            v-for="item in perfilLeadsMeta.topDdds"
                            :key="item.ddd"
                            class="flex flex-col gap-1 rounded-md p-1.5 transition-colors hover:bg-white"
                        >
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded bg-[#EFF4FA] px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] font-bold text-[#31496E]"
                                    >
                                        DDD {{ item.ddd }}
                                    </span>
                                    <span class="text-foreground font-medium">
                                        {{ item.regiao }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[11px]"
                                    >
                                        {{ item.qtd }} leads
                                    </span>
                                    <span
                                        class="text-foreground rounded bg-[#F2EFE8] px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[11px] font-semibold"
                                    >
                                        {{ item.pct }}%
                                    </span>
                                </div>
                            </div>

                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-[#EAE6DF]"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-300"
                                    :style="{
                                        background: item.cor,
                                        width: `${item.pct}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- INSIGHT GEOGRÁFICO ESTRATÉGICO -->
                    <div
                        class="mt-1 rounded-md border border-[#E1EAF4] bg-[#F2F6FB] p-3 text-[11.5px] leading-relaxed text-[#31496E]"
                    >
                        <strong>Diretriz de Geotargeting:</strong> O polo
                        Brasília/DF e Entorno de Goiás (DDD 61/62) responde por
                        mais de 40% dos contatos totais. Para otimizar o CPA
                        (Custo por Aquisição), configure conjuntos separados: um
                        para <strong>DF + 80km de raio</strong> (PMDF/Câmara) e
                        outro para o <strong>Rio Grande do Sul</strong> (PCRS -
                        DDDs 51/53/55).
                    </div>
                </div>

                <!-- COLUNA 2: CLUSTERS POR CONCURSO & FASES DA DEMANDA -->
                <div class="flex flex-col gap-4">
                    <!-- CLUSTERS POR CONCURSO -->
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div
                            class="flex items-center justify-between border-b border-[#F2EFE8] pb-2"
                        >
                            <div class="flex items-center gap-1.5">
                                <Layers class="h-4 w-4 text-[#8C6F3F]" />
                                <h3
                                    class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                                >
                                    Clusters por Concurso &amp; Taxa de
                                    Qualificação
                                </h3>
                            </div>
                            <span
                                class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-xs"
                            >
                                Volume &amp; Viabilidade
                            </span>
                        </div>

                        <div
                            v-if="
                                !perfilLeadsMeta ||
                                perfilLeadsMeta.clustersConcurso.length === 0
                            "
                            class="text-muted-foreground py-4 text-center text-xs"
                        >
                            Sem dados de concurso.
                        </div>

                        <div v-else class="grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="item in perfilLeadsMeta.clustersConcurso"
                                :key="item.nome"
                                class="hover:shadow-xs flex flex-col gap-1.5 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5]/60 p-2.5 transition-all hover:bg-white"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-foreground font-[family-name:var(--font-crm-display)] text-[13px] font-semibold"
                                    >
                                        {{ item.nome }}
                                    </span>
                                    <span
                                        class="rounded px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[10.5px] font-bold"
                                        :class="
                                            item.taxaQualificacao >= 50
                                                ? 'bg-[#E1F5EA] text-[#0F5132]'
                                                : 'text-muted-foreground bg-[#FAF8F5]'
                                        "
                                    >
                                        {{ item.taxaQualificacao }}%
                                        qualificados
                                    </span>
                                </div>

                                <div
                                    class="text-muted-foreground flex items-center justify-between text-xs"
                                >
                                    <span
                                        >{{ item.total }} leads ({{
                                            item.qualificados
                                        }}
                                        aptos)</span
                                    >
                                    <span
                                        class="rounded bg-white px-1.5 py-0.5 text-[10.5px] font-medium text-[#31496E]"
                                    >
                                        {{ item.fasePrincipal }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FASES MAIS DEMANDADAS -->
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div
                            class="flex items-center justify-between border-b border-[#F2EFE8] pb-2"
                        >
                            <div class="flex items-center gap-1.5">
                                <Zap class="h-4 w-4 text-[#14574F]" />
                                <h3
                                    class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                                >
                                    Fases em que o Candidato Busca o Escritório
                                </h3>
                            </div>
                            <span class="text-muted-foreground text-[11.5px]">
                                Objeto central da ação jurídica
                            </span>
                        </div>

                        <div
                            v-if="
                                !perfilLeadsMeta ||
                                perfilLeadsMeta.fasesDemanda.length === 0
                            "
                            class="text-muted-foreground py-4 text-center text-xs"
                        >
                            Sem dados de fase.
                        </div>

                        <div v-else class="flex flex-col gap-2">
                            <div
                                v-for="item in perfilLeadsMeta.fasesDemanda"
                                :key="item.fase"
                                class="flex items-center justify-between text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :style="{ background: item.cor }"
                                    />
                                    <span class="text-foreground font-medium">{{
                                        item.fase
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)]"
                                    >
                                        {{ item.qtd }} leads
                                    </span>
                                    <span
                                        class="text-foreground w-10 text-right font-[family-name:var(--font-crm-mono)] font-semibold"
                                    >
                                        {{ item.pct }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- ABA 2: SETUP NO GERENCIADOR META           -->
            <!-- ========================================== -->
            <div
                v-else-if="abaMeta === 'setup'"
                class="mt-5 grid gap-5 lg:grid-cols-[1.15fr_1fr]"
            >
                <!-- COLUNA 1: OBJETIVO, PÚBLICOS & INTERESSES -->
                <div class="flex flex-col gap-4">
                    <!-- OBJETIVO & CANAL -->
                    <div
                        class="rounded-lg border border-[#E1EAF4] bg-[#F2F6FB] p-4 text-xs"
                    >
                        <div class="flex items-center gap-2 text-[#31496E]">
                            <Target class="h-4 w-4 shrink-0" />
                            <span class="font-semibold uppercase tracking-wider"
                                >Objetivo de Campanha Recomendado:</span
                            >
                        </div>
                        <p
                            class="text-foreground mb-1 mt-1.5 font-[family-name:var(--font-crm-display)] text-sm font-semibold"
                        >
                            {{ perfilLeadsMeta?.recomendacoesMeta.objetivo }}
                        </p>
                        <p
                            class="text-muted-foreground m-0 text-[11.5px] leading-relaxed"
                        >
                            Direcionar o tráfego pago para o WhatsApp Business
                            com disparos em tempo real de eventos
                            <code>Lead</code> e <code>Schedule</code> via
                            Conversions API (CAPI) para retroalimentar o
                            algoritmo da Meta com leads qualificados.
                        </p>
                    </div>

                    <!-- PÚBLICOS SUGERIDOS -->
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div class="border-b border-[#F2EFE8] pb-2">
                            <h3
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                            >
                                Estrutura de Públicos Recomendados
                            </h3>
                            <span class="text-muted-foreground text-[11.5px]">
                                Hierarquia de audiências para evitar
                                sobreposição de leilão
                            </span>
                        </div>

                        <div class="flex flex-col gap-2.5">
                            <div
                                v-for="pub in perfilLeadsMeta?.recomendacoesMeta
                                    .publicosSugeridos"
                                :key="pub.nome"
                                class="flex flex-col gap-1 rounded-md border border-[#F2EFE8] bg-[#FAF8F5]/60 p-2.5"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-foreground font-semibold"
                                        >{{ pub.nome }}</span
                                    >
                                    <span
                                        class="rounded bg-[#EFF4FA] px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[10.5px] font-medium text-[#31496E]"
                                    >
                                        {{ pub.tipo }}
                                    </span>
                                </div>
                                <p
                                    class="text-muted-foreground m-0 text-[11.5px] leading-relaxed"
                                >
                                    {{ pub.descricao }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- INTERESSES PARA SEGMENTAÇÃO DETALHADA -->
                    <div
                        class="flex flex-col gap-2.5 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div
                            class="flex items-center justify-between border-b border-[#F2EFE8] pb-2"
                        >
                            <h3
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[14px] font-medium"
                            >
                                Interesses para Segmentação Detalhada
                            </h3>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 text-[11px] text-[#31496E] hover:underline"
                                @click="
                                    copiarTexto(
                                        perfilLeadsMeta?.recomendacoesMeta.interesses.join(
                                            ', ',
                                        ) ?? '',
                                        'interesses-meta',
                                    )
                                "
                            >
                                <Check
                                    v-if="copiadoId === 'interesses-meta'"
                                    class="h-3 w-3 text-[#14574F]"
                                />
                                <Copy v-else class="h-3 w-3" />
                                <span>{{
                                    copiadoId === 'interesses-meta'
                                        ? 'Copiados!'
                                        : 'Copiar Interesses'
                                }}</span>
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="interesse in perfilLeadsMeta
                                    ?.recomendacoesMeta.interesses"
                                :key="interesse"
                                class="border-border text-foreground select-all rounded-md border bg-[#FAF8F5] px-2 py-1 text-xs"
                            >
                                {{ interesse }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- COLUNA 2: CONJUNTOS DE ANÚNCIOS (AD SETS) & DIAS DE PICO -->
                <div class="flex flex-col gap-4">
                    <!-- AD SETS COM GEOTARGETING -->
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div class="border-b border-[#F2EFE8] pb-2">
                            <h3
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                            >
                                Conjuntos de Anúncios (Ad Sets) Estruturados
                            </h3>
                            <span class="text-muted-foreground text-[11.5px]">
                                Segmentação geográfica por órgão do concurso
                            </span>
                        </div>

                        <div class="flex flex-col gap-2.5">
                            <div
                                v-for="geo in perfilLeadsMeta?.recomendacoesMeta
                                    .geotargeting"
                                :key="geo.conjunto"
                                class="flex flex-col gap-1 rounded-md border border-[#F2EFE8] bg-[#FAF8F5]/40 p-2.5"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="text-foreground font-semibold"
                                        >{{ geo.conjunto }}</span
                                    >
                                    <span
                                        class="rounded bg-[#FAF8F5] px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[10.5px] text-[#31496E]"
                                    >
                                        {{ geo.ddds }}
                                    </span>
                                </div>
                                <span
                                    class="text-[11.5px] font-medium text-[#14574F]"
                                    >{{ geo.localizacao }}</span
                                >
                                <p
                                    class="text-muted-foreground m-0 text-[11px] leading-normal"
                                >
                                    {{ geo.justificativa }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- DAYPARTING: DIAS DE PICO -->
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div class="border-b border-[#F2EFE8] pb-2">
                            <h3
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[14px] font-medium"
                            >
                                Comportamento Temporal &amp; Alocação de
                                Orçamento
                            </h3>
                            <span class="text-muted-foreground text-[11.5px]">
                                Distribuição de entrada de leads ao longo da
                                semana
                            </span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <div
                                v-for="dp in perfilLeadsMeta?.diasPico"
                                :key="dp.dia"
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="text-muted-foreground">{{
                                    dp.dia
                                }}</span>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-1.5 w-24 overflow-hidden rounded-full bg-[#EAE6DF]"
                                    >
                                        <div
                                            class="h-full rounded-full bg-[#31496E]"
                                            :style="{ width: `${dp.pct * 2}%` }"
                                        />
                                    </div>
                                    <span
                                        class="text-foreground w-12 text-right font-[family-name:var(--font-crm-mono)] font-medium"
                                    >
                                        {{ dp.qtd }} ({{ dp.pct }}%)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p
                            class="text-muted-foreground m-0 border-t border-[#F2EFE8] pt-2 text-[11px]"
                        >
                            <strong>Dica de Budget Pacing:</strong> Concentre
                            70% a 75% da verba semanal entre
                            <strong>Segunda e Quinta-feira</strong>, dias com
                            maior engajamento dos concurseiros pós-gabaritos e
                            publicações em diários oficiais.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- ABA 3: GANCHOS DE CRIATIVO & COMPLIANCE    -->
            <!-- ========================================== -->
            <div v-else class="mt-5 flex flex-col gap-4">
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <div
                        v-for="(criativo, idx) in perfilLeadsMeta
                            ?.recomendacoesMeta.ganchosCriativos"
                        :key="criativo.titulo"
                        class="shadow-2xs flex flex-col justify-between gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4 transition-all hover:border-[#31496E]/30"
                    >
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span
                                    class="rounded bg-[#EFF4FA] px-2 py-0.5 font-[family-name:var(--font-crm-mono)] text-[10.5px] font-semibold text-[#31496E]"
                                >
                                    Criativo #{{ idx + 1 }} ·
                                    {{ criativo.concursoOuFase }}
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 text-[11px] font-medium text-[#31496E] hover:underline"
                                    @click="
                                        copiarTexto(
                                            `${criativo.gancho}\n\n${criativo.cta}`,
                                            `criativo-${idx}`,
                                        )
                                    "
                                >
                                    <Check
                                        v-if="copiadoId === `criativo-${idx}`"
                                        class="h-3 w-3 text-[#14574F]"
                                    />
                                    <Copy v-else class="h-3 w-3" />
                                    <span>{{
                                        copiadoId === `criativo-${idx}`
                                            ? 'Copiado!'
                                            : 'Copiar Texto'
                                    }}</span>
                                </button>
                            </div>

                            <h4
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[14.5px] font-semibold"
                            >
                                {{ criativo.titulo }}
                            </h4>

                            <!-- GANCHO PRINCIPAL -->
                            <div
                                class="border-l-3 text-foreground rounded-md border-[#31496E] bg-[#FAF8F5] p-2.5 text-xs italic"
                            >
                                {{ criativo.gancho }}
                            </div>

                            <!-- DOR CENTRAL -->
                            <div class="text-muted-foreground text-[11.5px]">
                                <strong class="text-foreground"
                                    >Dor que motiva o lead:</strong
                                >
                                {{ criativo.dor }}
                            </div>
                        </div>

                        <!-- CTA SUGERIDO -->
                        <div
                            class="rounded bg-[#F4F8F6] p-2 text-[11px] text-[#14574F]"
                        >
                            <strong>Call to Action sugerido:</strong>
                            {{ criativo.cta }}
                        </div>
                    </div>
                </div>

                <!-- ALERTA DE COMPLIANCE OAB -->
                <div
                    class="flex items-start gap-3 rounded-lg border border-[#E0EBE6] bg-[#F4F8F6] p-4 text-xs text-[#14574F]"
                >
                    <ShieldCheck
                        class="mt-0.5 h-5 w-5 shrink-0 text-[#14574F]"
                    />
                    <div class="flex-1 leading-relaxed">
                        <strong class="text-foreground font-semibold"
                            >Compliance com o Provimento 205/2021 da
                            OAB:</strong
                        >
                        <p class="text-muted-foreground mb-0 mt-1">
                            A publicidade jurídica no Meta Ads deve ter caráter
                            meramente informativo, com moderação e discrição. É
                            estritamente vedada a promessa de vitória, garantia
                            de anulação de questões ou aprovação em TAF, menção
                            a valores de causas ou honorários, e qualquer forma
                            de captação mercantil. O CRM da ASF opera
                            exclusivamente com canais de
                            <strong>entrada passiva</strong> iniciados
                            espontaneamente pelo candidato.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- CARD ANALÍTICO EXCLUSIVO: ATENDIMENTOS ENCERRADOS & MOTIVOS DE PERDA     -->
        <!-- ========================================================================= -->
        <div
            id="atendimentos-encerrados"
            ref="cardEncerradosRef"
            class="border-border shadow-xs rounded-[10px] border bg-white px-5 pb-6 pt-5 transition-all"
        >
            <!-- CABEÇALHO DO CARD -->
            <div
                class="flex flex-col gap-3 border-b border-[#F2EFE8] pb-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-[#FDF3F2] px-2.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[10.5px] font-medium text-[#9B3B2F]"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-[#9B3B2F]"
                            />
                            Etapa Terminal · Atendimentos Encerrados
                        </span>
                        <span
                            class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-xs"
                        >
                            {{ atendimentosEncerrados?.total ?? 0 }} leads
                            catalogados ({{
                                atendimentosEncerrados?.taxaEncerrados ?? 0
                            }}% da base total)
                        </span>
                    </div>
                    <h2
                        class="text-foreground mb-0.5 mt-1.5 font-[family-name:var(--font-crm-display)] text-[20px] font-medium"
                    >
                        Análise de Atendimentos Encerrados &amp; Motivos de
                        Desqualificação
                    </h2>
                    <p class="text-muted-foreground m-0 text-[12.5px]">
                        Diagnóstico completo dos motivos de perda, momento da
                        interrupção do contato e relatos detalhados dos
                        atendentes.
                    </p>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <!-- CONTROLE DE ABAS -->
                    <div
                        class="border-border flex rounded-lg border bg-[#F8F7F4] p-0.5 text-xs"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-1 font-medium transition-all"
                            :class="
                                abaEncerrados === 'motivos'
                                    ? 'text-foreground shadow-xs bg-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="abaEncerrados = 'motivos'"
                        >
                            Motivos &amp; Métricas
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-1 font-medium transition-all"
                            :class="
                                abaEncerrados === 'leads'
                                    ? 'text-foreground shadow-xs bg-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="abaEncerrados = 'leads'"
                        >
                            Explorador de Leads ({{ leadsFiltrados.length }})
                        </button>
                    </div>

                    <Link
                        href="/negociacoes"
                        class="border-border text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded-lg border bg-white px-2.5 py-1 text-xs transition-colors hover:bg-[#FAF8F5]"
                    >
                        <span>Ver no Funil</span>
                        <ExternalLink class="h-3 w-3" />
                    </Link>
                </div>
            </div>

            <!-- MINI-KPIS RESUMO -->
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5] p-3"
                >
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider"
                        >Total Encerrados</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="text-foreground font-[family-name:var(--font-crm-display)] text-2xl font-semibold"
                        >
                            {{ atendimentosEncerrados?.total ?? 0 }}
                        </span>
                        <span class="text-muted-foreground text-xs">leads</span>
                    </div>
                    <span class="text-muted-foreground text-[11px]">
                        {{ atendimentosEncerrados?.taxaEncerrados ?? 0 }}% de
                        todo o histórico
                    </span>
                </div>

                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#F8E7E5] bg-[#FDF8F7] p-3"
                >
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider text-[#9B3B2F]"
                        >Sem Resposta / Vácuo</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="font-[family-name:var(--font-crm-display)] text-2xl font-semibold text-[#9B3B2F]"
                        >
                            {{ atendimentosEncerrados?.totalSemResposta ?? 0 }}
                        </span>
                        <span class="text-xs text-[#9B3B2F]">leads</span>
                    </div>
                    <span class="text-[11px] text-[#9B3B2F]/80">
                        {{
                            atendimentosEncerrados?.total
                                ? Math.round(
                                      (atendimentosEncerrados.totalSemResposta /
                                          atendimentosEncerrados.total) *
                                          100,
                                  )
                                : 0
                        }}% não responderam follow-up
                    </span>
                </div>

                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#EBEBEB] bg-[#FAFAFA] p-3"
                >
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider"
                        >Desqualificados no Perfil</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="text-foreground font-[family-name:var(--font-crm-display)] text-2xl font-semibold"
                        >
                            {{
                                atendimentosEncerrados?.totalDesqualificados ??
                                0
                            }}
                        </span>
                        <span class="text-muted-foreground text-xs">leads</span>
                    </div>
                    <span class="text-muted-foreground text-[11px]">
                        Sem enquadramento na tese jurídica
                    </span>
                </div>

                <div
                    class="flex flex-col gap-1 rounded-lg border border-[#E0EBE6] bg-[#F4F8F6] p-3"
                >
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-wider text-[#14574F]"
                        >Qualificados c/ Parada</span
                    >
                    <div class="flex items-baseline gap-1.5">
                        <span
                            class="font-[family-name:var(--font-crm-display)] text-2xl font-semibold text-[#14574F]"
                        >
                            {{ atendimentosEncerrados?.totalQualificados ?? 0 }}
                        </span>
                        <span class="text-xs text-[#14574F]">leads</span>
                    </div>
                    <span class="text-[11px] text-[#14574F]/80">
                        Aptos juridicamente, mas pausaram
                    </span>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- ABA 1: MOTIVOS E INDICADORES VISUAIS       -->
            <!-- ========================================== -->
            <div
                v-if="abaEncerrados === 'motivos'"
                class="mt-5 grid gap-5 lg:grid-cols-[1.25fr_1fr]"
            >
                <!-- COLUNA DA ESQUERDA: RANKING DE MOTIVOS -->
                <div
                    class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5]/40 p-4"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#F2EFE8] pb-2.5"
                    >
                        <div>
                            <h3
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                            >
                                Ranking dos Motivos de Encerramento
                            </h3>
                            <span class="text-muted-foreground text-[11.5px]">
                                Clique em qualquer motivo para filtrar e listar
                                os leads
                            </span>
                        </div>
                        <span
                            class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-xs"
                        >
                            {{ atendimentosEncerrados?.motivos.length ?? 0 }}
                            categorias
                        </span>
                    </div>

                    <div
                        v-if="
                            !atendimentosEncerrados ||
                            atendimentosEncerrados.motivos.length === 0
                        "
                        class="text-muted-foreground py-6 text-center text-xs"
                    >
                        Nenhum atendimento encerrado registrado.
                    </div>

                    <div v-else class="flex flex-col gap-2.5">
                        <div
                            v-for="item in atendimentosEncerrados.motivos"
                            :key="item.motivo"
                            class="hover:shadow-xs group flex cursor-pointer flex-col gap-1.5 rounded-md p-2 transition-colors hover:bg-white"
                            @click="selecionarMotivoEIrParaLeads(item.motivo)"
                        >
                            <div
                                class="flex items-center justify-between text-[12.5px]"
                            >
                                <span
                                    class="text-foreground font-medium group-hover:text-[#9B3B2F]"
                                >
                                    {{ item.motivo }}
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-xs"
                                    >
                                        {{ item.qtd }} leads
                                    </span>
                                    <span
                                        class="rounded bg-[#FDF3F2] px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[11px] font-semibold text-[#9B3B2F]"
                                    >
                                        {{ item.pct }}%
                                    </span>
                                    <ArrowUpRight
                                        class="text-muted-foreground h-3.5 w-3.5 opacity-0 transition-opacity group-hover:opacity-100"
                                    />
                                </div>
                            </div>

                            <div
                                class="h-2 overflow-hidden rounded-full bg-[#EAE6DF]"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-300"
                                    :style="{
                                        background: item.cor,
                                        width: `${item.pct}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLUNA DA DIREITA: CONTINUIDADE & QUALIFICAÇÃO -->
                <div class="flex flex-col gap-4">
                    <!-- MOMENTO DA INTERRUPÇÃO -->
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div class="border-b border-[#F2EFE8] pb-2">
                            <h3
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                            >
                                Momento da Interrupção (Continuidade)
                            </h3>
                            <span class="text-muted-foreground text-[11.5px]">
                                Ponto exato da jornada em que o contato cessou
                            </span>
                        </div>

                        <div
                            v-if="
                                !atendimentosEncerrados ||
                                atendimentosEncerrados.continuidade.length === 0
                            "
                            class="text-muted-foreground py-4 text-center text-xs"
                        >
                            Sem dados de continuidade.
                        </div>

                        <div v-else class="flex flex-col gap-2.5">
                            <div
                                v-for="item in atendimentosEncerrados.continuidade"
                                :key="item.nome"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex justify-between text-xs">
                                    <span class="text-muted-foreground">{{
                                        item.nome
                                    }}</span>
                                    <span
                                        class="text-foreground font-[family-name:var(--font-crm-mono)] font-medium"
                                    >
                                        {{ item.qtd }} leads ({{ item.pct }}%)
                                    </span>
                                </div>
                                <div
                                    class="h-1.5 overflow-hidden rounded-full bg-[#F2EFE8]"
                                >
                                    <div
                                        class="h-full rounded-full"
                                        :style="{
                                            background: item.cor,
                                            width: `${item.pct}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DISTRIBUIÇÃO DE QUALIFICAÇÃO -->
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-[#F2EFE8] bg-white p-4"
                    >
                        <div class="border-b border-[#F2EFE8] pb-2">
                            <h3
                                class="text-foreground m-0 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                            >
                                Perfil Jurídico Prévio
                            </h3>
                            <span class="text-muted-foreground text-[11.5px]">
                                Avaliação inicial de elegibilidade da causa
                            </span>
                        </div>

                        <div
                            v-if="
                                !atendimentosEncerrados ||
                                atendimentosEncerrados.qualificacao.length === 0
                            "
                            class="text-muted-foreground py-4 text-center text-xs"
                        >
                            Sem dados de qualificação.
                        </div>

                        <div v-else class="flex flex-col gap-2.5">
                            <div
                                v-for="item in atendimentosEncerrados.qualificacao"
                                :key="item.nome"
                                class="flex items-center justify-between text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :style="{ background: item.cor }"
                                    />
                                    <span class="text-foreground font-medium">{{
                                        item.nome
                                    }}</span>
                                </div>
                                <span
                                    class="text-muted-foreground font-[family-name:var(--font-crm-mono)]"
                                >
                                    {{ item.qtd }} leads ({{ item.pct }}%)
                                </span>
                            </div>
                        </div>

                        <!-- NOTA INFORMATIVA ESTRATÉGICA -->
                        <div
                            class="mt-1 rounded-md border border-[#E0EBE6] bg-[#F4F8F6] p-2.5 text-[11px] leading-relaxed text-[#14574F]"
                        >
                            <strong>Oportunidade de Repescagem:</strong> Os
                            leads com status <em>"Qualificado"</em> encerrados
                            possuem perfil válido para judicialização, porém
                            pararam por fatores de certame ou aguardo do
                            Ministério Público. Podem ser reabordados
                            estrategicamente em novas fases do concurso.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- ABA 2: EXPLORADOR DE LEADS & RELATOS       -->
            <!-- ========================================== -->
            <div v-else class="mt-5 flex flex-col gap-4">
                <!-- BARRA DE FERRAMENTAS & FILTROS -->
                <div
                    class="flex flex-col gap-2.5 rounded-lg border border-[#F2EFE8] bg-[#FAF8F5] p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <!-- CAMPO DE BUSCA -->
                        <div class="relative flex-1">
                            <Search
                                class="text-muted-foreground absolute left-2.5 top-2.5 h-3.5 w-3.5"
                            />
                            <input
                                v-model="termoBusca"
                                type="text"
                                placeholder="Buscar por lead, concurso (ex: PMDF, CNU), anotações..."
                                class="border-border text-foreground placeholder:text-muted-foreground/60 focus:outline-hidden w-full rounded-md border bg-white py-1.5 pl-8 pr-3 text-xs focus:border-[#9B3B2F]"
                            />
                        </div>

                        <!-- FILTRO POR MOTIVO -->
                        <select
                            v-model="filtroMotivo"
                            class="border-border text-foreground focus:outline-hidden rounded-md border bg-white px-2.5 py-1.5 text-xs focus:border-[#9B3B2F]"
                        >
                            <option value="todos">
                                Todos os motivos ({{
                                    atendimentosEncerrados?.motivos.length ?? 0
                                }})
                            </option>
                            <option
                                v-for="m in atendimentosEncerrados?.motivos"
                                :key="m.motivo"
                                :value="m.motivo"
                            >
                                {{ m.motivo }} ({{ m.qtd }})
                            </option>
                        </select>

                        <!-- FILTRO POR QUALIFICAÇÃO -->
                        <select
                            v-model="filtroQualificacao"
                            class="border-border text-foreground focus:outline-hidden rounded-md border bg-white px-2.5 py-1.5 text-xs focus:border-[#9B3B2F]"
                        >
                            <option value="todos">
                                Todas as qualificações
                            </option>
                            <option value="qualificado">
                                Apenas Qualificados
                            </option>
                            <option value="desqualificado">
                                Apenas Desqualificados
                            </option>
                        </select>

                        <!-- CHECKBOX APENAS COM OBSERVAÇÕES -->
                        <label
                            class="text-muted-foreground flex cursor-pointer select-none items-center gap-1.5 text-xs"
                        >
                            <input
                                v-model="apenasComObservacoes"
                                type="checkbox"
                                class="border-border rounded text-[#9B3B2F] focus:ring-[#9B3B2F]"
                            />
                            <span>Com anotações</span>
                        </label>
                    </div>

                    <!-- BOTÃO LIMPAR FILTROS -->
                    <button
                        v-if="filtrosAtivos"
                        type="button"
                        class="flex cursor-pointer items-center gap-1 text-xs text-[#9B3B2F] hover:underline"
                        @click="limparFiltros"
                    >
                        <X class="h-3.5 w-3.5" />
                        <span>Limpar</span>
                    </button>
                </div>

                <!-- CONTADOR DE RESULTADOS -->
                <div
                    class="text-muted-foreground flex items-center justify-between text-xs"
                >
                    <span>
                        Exibindo <strong>{{ leadsVisiveis.length }}</strong> de
                        <strong>{{ leadsFiltrados.length }}</strong> leads
                        encerrados encontrados
                    </span>
                    <span
                        v-if="
                            leadsFiltrados.length <
                            (atendimentosEncerrados?.total ?? 0)
                        "
                        class="text-[11px] text-[#9B3B2F]"
                    >
                        Filtro ativo ({{ atendimentosEncerrados?.total ?? 0 }}
                        no total)
                    </span>
                </div>

                <!-- LISTA DE LEADS ENCERRADOS -->
                <div
                    v-if="leadsFiltrados.length === 0"
                    class="border-border flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center"
                >
                    <Filter class="text-muted-foreground/40 mb-2 h-8 w-8" />
                    <p class="text-foreground m-0 text-sm font-medium">
                        Nenhum lead encontrado com os filtros aplicados
                    </p>
                    <p class="text-muted-foreground mb-3 mt-1 text-xs">
                        Tente alterar o termo de busca ou limpar os filtros de
                        motivo e qualificação.
                    </p>
                    <button
                        type="button"
                        class="cursor-pointer rounded-md bg-[#FAF8F5] px-3 py-1.5 text-xs font-medium text-[#9B3B2F] hover:bg-[#FDF3F2]"
                        @click="limparFiltros"
                    >
                        Limpar todos os filtros
                    </button>
                </div>

                <div v-else class="flex flex-col gap-3">
                    <div
                        v-for="lead in leadsVisiveis"
                        :key="lead.id"
                        class="hover:shadow-xs flex flex-col gap-2.5 rounded-lg border border-[#F2EFE8] bg-white p-4 transition-all hover:border-[#E8D0CC]"
                    >
                        <!-- LINHA 1: CONTATO, ASSUNTO, RESPONSÁVEL E DATA -->
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="`/negociacoes/${lead.id}/edit`"
                                    class="text-foreground font-[family-name:var(--font-crm-display)] text-sm font-semibold hover:text-[#9B3B2F] hover:underline"
                                >
                                    {{ lead.contatoNome }}
                                </Link>

                                <span
                                    v-if="lead.contatoTelefone"
                                    class="text-muted-foreground inline-flex items-center gap-1 font-[family-name:var(--font-crm-mono)] text-[11px]"
                                >
                                    <Phone class="h-3 w-3" />
                                    {{ lead.contatoTelefone }}
                                </span>

                                <span
                                    class="text-foreground rounded bg-[#FAF8F5] px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ lead.assunto }}
                                </span>
                            </div>

                            <div
                                class="text-muted-foreground flex items-center gap-3 text-xs"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <User class="h-3 w-3" />
                                    {{ lead.responsavelNome }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 font-[family-name:var(--font-crm-mono)] text-[11px]"
                                >
                                    <Clock class="h-3 w-3" />
                                    {{ lead.data }}
                                </span>
                                <Link
                                    :href="`/negociacoes/${lead.id}/edit`"
                                    class="inline-flex items-center gap-0.5 text-[#9B3B2F] hover:underline"
                                    title="Abrir negociação no CRM"
                                >
                                    <span>Editar</span>
                                    <ExternalLink class="h-3 w-3" />
                                </Link>
                            </div>
                        </div>

                        <!-- LINHA 2: BADGES DE QUALIFICAÇÃO, MOTIVO E CONTINUIDADE -->
                        <div
                            class="flex flex-wrap items-center gap-1.5 text-[11px]"
                        >
                            <!-- STATUS QUALIFICAÇÃO -->
                            <span
                                class="rounded px-2 py-0.5 font-medium"
                                :style="{
                                    backgroundColor: lead.qualificacaoCorFundo,
                                    color: lead.qualificacaoCorTexto,
                                }"
                            >
                                {{ lead.qualificacao }}
                            </span>

                            <!-- MOTIVO DE DESQUALIFICAÇÃO -->
                            <span
                                class="inline-flex items-center gap-1 rounded bg-[#FDF3F2] px-2 py-0.5 font-medium text-[#9B3B2F]"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#9B3B2F]"
                                />
                                Motivo: {{ lead.motivo }}
                            </span>

                            <!-- MOMENTO DA INTERRUPÇÃO -->
                            <span
                                class="inline-flex items-center gap-1 rounded bg-[#F0F4F8] px-2 py-0.5 text-[#31496E]"
                            >
                                Interrupção: {{ lead.continuidade }}
                            </span>
                        </div>

                        <!-- LINHA 3: OBSERVAÇÕES COMPLEMENTARES / RELATO DO ATENDIMENTO -->
                        <div
                            v-if="lead.observacoes"
                            class="border-l-3 text-foreground flex items-start gap-2.5 rounded-md border-[#9B3B2F] bg-[#FAF8F5] p-2.5 text-xs"
                        >
                            <MessageSquare
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#9B3B2F]"
                            />
                            <div class="flex-1">
                                <span class="font-medium text-[#9B3B2F]"
                                    >Anotação do Atendimento:</span
                                >
                                <p
                                    class="text-muted-foreground mb-0 mt-0.5 whitespace-pre-wrap text-[12px] leading-relaxed"
                                >
                                    {{ lead.observacoes }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONTROLES DE PAGINAÇÃO / CARREGAR MAIS -->
                <div
                    v-if="leadsFiltrados.length > limiteExibicao"
                    class="mt-2 flex flex-col items-center justify-center gap-2 sm:flex-row"
                >
                    <button
                        type="button"
                        class="border-border text-foreground shadow-xs cursor-pointer rounded-lg border bg-white px-4 py-2 text-xs font-medium transition-colors hover:bg-[#FAF8F5]"
                        @click="carregarMais"
                    >
                        Carregar mais 15 leads (restam
                        {{ leadsFiltrados.length - limiteExibicao }})
                    </button>
                    <button
                        type="button"
                        class="text-muted-foreground cursor-pointer text-xs transition-colors hover:text-[#9B3B2F] hover:underline"
                        @click="carregarTodos"
                    >
                        Exibir todos os {{ leadsFiltrados.length }} leads
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
