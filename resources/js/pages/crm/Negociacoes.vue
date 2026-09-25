<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import {
    create as createNegociacao,
    cycleDistribuicao,
    updateEtapa,
} from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmNegociacaoDrawer from '@/components/crm/CrmNegociacaoDrawer.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as funisIndex } from '@/routes/admin/funis';
import type { FunilView, NegociacaoLinha } from '@/types/crm';

const props = defineProps<{
    funis: FunilView[];
    negociacoes: NegociacaoLinha[];
}>();

const SEM_STATUS = '—';

const visao = ref<'funil' | 'lista'>('funil');
const funilId = ref<number | null>(props.funis[0]?.id ?? null);
const negociacaoId = ref<number | null>(null);
const negociacaoArrastandoId = ref<number | null>(null);
const etapaSoltandoId = ref<number | null>(null);
const suprimirClique = ref(false);

/** Vazio = todos. Só campos das leads do funil ativo. */
const etapasSelecionadas = ref<number[]>([]);
const empresasSelecionadas = ref<string[]>([]);
const contatosSelecionados = ref<string[]>([]);
const canaisSelecionados = ref<string[]>([]);
const atendimentosSelecionados = ref<string[]>([]);
const qualificacoesSelecionadas = ref<string[]>([]);
const responsaveisSelecionados = ref<string[]>([]);

onMounted(() => {
    const parametro = new URLSearchParams(window.location.search).get(
        'negociacao',
    );

    if (!parametro) {
        return;
    }

    const id = Number(parametro);

    if (Number.isNaN(id)) {
        return;
    }

    const negociacao = props.negociacoes.find((item) => item.id === id);

    if (!negociacao) {
        return;
    }

    funilId.value = negociacao.funilId;
    negociacaoId.value = id;
});

const funilAtivo = computed(
    () =>
        props.funis.find((funil) => funil.id === funilId.value) ??
        props.funis[0] ??
        null,
);

const leadsDoFunil = computed(() => {
    if (funilAtivo.value === null) {
        return [];
    }

    return props.negociacoes.filter(
        (negociacao) => negociacao.funilId === funilAtivo.value?.id,
    );
});

const valoresUnicos = (valores: Array<string | null>): string[] =>
    [...new Set(valores.map((valor) => valor ?? SEM_STATUS))].sort((a, b) =>
        a.localeCompare(b, 'pt-BR'),
    );

const opcoesEtapa = computed(
    () =>
        funilAtivo.value?.etapas.map((etapa) => ({
            id: etapa.id,
            nome: etapa.nome,
        })) ?? [],
);

const opcoesEmpresa = computed(() =>
    valoresUnicos(leadsDoFunil.value.map((item) => item.empresa)),
);

const opcoesContato = computed(() =>
    valoresUnicos(leadsDoFunil.value.map((item) => item.contato)),
);

const opcoesCanal = computed(() =>
    valoresUnicos(leadsDoFunil.value.map((item) => item.canal)),
);

const opcoesAtendimento = computed(() =>
    valoresUnicos(leadsDoFunil.value.map((item) => item.statusAtendimento)),
);

const opcoesQualificacao = computed(() =>
    valoresUnicos(leadsDoFunil.value.map((item) => item.statusQualificacao)),
);

const opcoesResponsavel = computed(() =>
    valoresUnicos(leadsDoFunil.value.map((item) => item.responsavel)),
);

watch(funilId, () => {
    etapasSelecionadas.value = [];
    empresasSelecionadas.value = [];
    contatosSelecionados.value = [];
    canaisSelecionados.value = [];
    atendimentosSelecionados.value = [];
    qualificacoesSelecionadas.value = [];
    responsaveisSelecionados.value = [];
    negociacaoId.value = null;
});

const passaFiltro = (
    selecionados: string[],
    valor: string | null,
): boolean => {
    if (selecionados.length === 0) {
        return true;
    }

    return selecionados.includes(valor ?? SEM_STATUS);
};

const negociacoesFiltradas = computed(() => {
    const etapas =
        etapasSelecionadas.value.length === 0
            ? null
            : new Set(etapasSelecionadas.value);

    return leadsDoFunil.value.filter((negociacao) => {
        if (etapas !== null && !etapas.has(negociacao.etapaId)) {
            return false;
        }

        if (!passaFiltro(empresasSelecionadas.value, negociacao.empresa)) {
            return false;
        }

        if (!passaFiltro(contatosSelecionados.value, negociacao.contato)) {
            return false;
        }

        if (!passaFiltro(canaisSelecionados.value, negociacao.canal)) {
            return false;
        }

        if (
            !passaFiltro(
                atendimentosSelecionados.value,
                negociacao.statusAtendimento,
            )
        ) {
            return false;
        }

        if (
            !passaFiltro(
                qualificacoesSelecionadas.value,
                negociacao.statusQualificacao,
            )
        ) {
            return false;
        }

        if (
            !passaFiltro(
                responsaveisSelecionados.value,
                negociacao.responsavel,
            )
        ) {
            return false;
        }

        return true;
    });
});

const colunas = computed(() => {
    const funil = funilAtivo.value;

    if (!funil) {
        return [];
    }

    const etapas =
        etapasSelecionadas.value.length === 0
            ? funil.etapas
            : funil.etapas.filter((etapa) =>
                  etapasSelecionadas.value.includes(etapa.id),
              );

    return etapas.map((etapa) => {
        const cards = negociacoesFiltradas.value.filter(
            (negociacao) => negociacao.etapaId === etapa.id,
        );
        const total = cards.reduce((soma, card) => soma + card.valor, 0);

        return {
            ...etapa,
            qtd: cards.length,
            valor: compactar(total),
            cards,
        };
    });
});

const negociacaoAberta = computed(
    () =>
        props.negociacoes.find(
            (negociacao) => negociacao.id === negociacaoId.value,
        ) ?? null,
);

const filtrosAtivos = computed(
    () =>
        etapasSelecionadas.value.length > 0 ||
        empresasSelecionadas.value.length > 0 ||
        contatosSelecionados.value.length > 0 ||
        canaisSelecionados.value.length > 0 ||
        atendimentosSelecionados.value.length > 0 ||
        qualificacoesSelecionadas.value.length > 0 ||
        responsaveisSelecionados.value.length > 0,
);

const rotuloFiltro = (
    selecionados: string[] | number[],
    todas: number,
    singular: string,
    plural: string,
): string => {
    if (selecionados.length === 0 || selecionados.length === todas) {
        return `Todos os ${plural}`;
    }

    if (selecionados.length === 1) {
        return `1 ${singular}`;
    }

    return `${selecionados.length} ${plural}`;
};

const rotuloEtapas = computed(() =>
    rotuloFiltro(
        etapasSelecionadas.value,
        opcoesEtapa.value.length,
        'etapa',
        'etapas',
    ),
);

const rotuloEmpresas = computed(() =>
    rotuloFiltro(
        empresasSelecionadas.value,
        opcoesEmpresa.value.length,
        'empresa',
        'empresas',
    ),
);

const rotuloContatos = computed(() =>
    rotuloFiltro(
        contatosSelecionados.value,
        opcoesContato.value.length,
        'contato',
        'contatos',
    ),
);

const rotuloCanais = computed(() =>
    rotuloFiltro(
        canaisSelecionados.value,
        opcoesCanal.value.length,
        'canal',
        'canais',
    ),
);

const rotuloAtendimentos = computed(() =>
    rotuloFiltro(
        atendimentosSelecionados.value,
        opcoesAtendimento.value.length,
        'atendimento',
        'atendimentos',
    ),
);

const rotuloQualificacoes = computed(() =>
    rotuloFiltro(
        qualificacoesSelecionadas.value,
        opcoesQualificacao.value.length,
        'qualificação',
        'qualificações',
    ),
);

const rotuloResponsaveis = computed(() =>
    rotuloFiltro(
        responsaveisSelecionados.value,
        opcoesResponsavel.value.length,
        'responsável',
        'responsáveis',
    ),
);

const filtroTriggerClass =
    'inline-flex max-w-[180px] cursor-pointer items-center justify-between gap-2 rounded-md border border-border bg-white px-2.5 py-1.5 text-left text-[12.5px] text-foreground outline-none hover:border-primary';

const compactar = (valor: number): string => {
    if (valor >= 1000) {
        const casas = valor >= 100000 ? 0 : 1;

        return `R$ ${(valor / 1000).toFixed(casas).replace('.', ',')}k`;
    }

    return `R$ ${Math.round(valor)}`;
};

const selecionarFunil = (id: number): void => {
    funilId.value = id;
};

/** Refs unwrap in template — never pass a ref into these helpers from the template. */
const alternarEtapa = (id: number): void => {
    etapasSelecionadas.value = etapasSelecionadas.value.includes(id)
        ? etapasSelecionadas.value.filter((item) => item !== id)
        : [...etapasSelecionadas.value, id];
};

const alternarEmpresa = (valor: string): void => {
    empresasSelecionadas.value = empresasSelecionadas.value.includes(valor)
        ? empresasSelecionadas.value.filter((item) => item !== valor)
        : [...empresasSelecionadas.value, valor];
};

const alternarContato = (valor: string): void => {
    contatosSelecionados.value = contatosSelecionados.value.includes(valor)
        ? contatosSelecionados.value.filter((item) => item !== valor)
        : [...contatosSelecionados.value, valor];
};

const alternarCanal = (valor: string): void => {
    canaisSelecionados.value = canaisSelecionados.value.includes(valor)
        ? canaisSelecionados.value.filter((item) => item !== valor)
        : [...canaisSelecionados.value, valor];
};

const alternarAtendimento = (valor: string): void => {
    atendimentosSelecionados.value =
        atendimentosSelecionados.value.includes(valor)
            ? atendimentosSelecionados.value.filter((item) => item !== valor)
            : [...atendimentosSelecionados.value, valor];
};

const alternarQualificacao = (valor: string): void => {
    qualificacoesSelecionadas.value =
        qualificacoesSelecionadas.value.includes(valor)
            ? qualificacoesSelecionadas.value.filter((item) => item !== valor)
            : [...qualificacoesSelecionadas.value, valor];
};

const alternarResponsavel = (valor: string): void => {
    responsaveisSelecionados.value =
        responsaveisSelecionados.value.includes(valor)
            ? responsaveisSelecionados.value.filter((item) => item !== valor)
            : [...responsaveisSelecionados.value, valor];
};

const limparFiltros = (): void => {
    etapasSelecionadas.value = [];
    empresasSelecionadas.value = [];
    contatosSelecionados.value = [];
    canaisSelecionados.value = [];
    atendimentosSelecionados.value = [];
    qualificacoesSelecionadas.value = [];
    responsaveisSelecionados.value = [];
};

const iniciarArraste = (event: DragEvent, id: number): void => {
    suprimirClique.value = true;
    negociacaoArrastandoId.value = id;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(id));
    }
};

const duranteArraste = (event: DragEvent, etapaId: number): void => {
    event.preventDefault();
    etapaSoltandoId.value = etapaId;

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const sairColuna = (event: DragEvent, etapaId: number): void => {
    const current = event.currentTarget;

    if (
        !(current instanceof HTMLElement) ||
        !(event.relatedTarget instanceof Node) ||
        current.contains(event.relatedTarget)
    ) {
        return;
    }

    if (etapaSoltandoId.value === etapaId) {
        etapaSoltandoId.value = null;
    }
};

const finalizarArraste = (): void => {
    negociacaoArrastandoId.value = null;
    etapaSoltandoId.value = null;

    window.setTimeout(() => {
        suprimirClique.value = false;
    }, 0);
};

const soltarNaEtapa = (event: DragEvent, etapaId: number): void => {
    event.preventDefault();

    const negociacaoIdStr = event.dataTransfer?.getData('text/plain');
    const negociacaoIdNum = negociacaoIdStr
        ? Number(negociacaoIdStr)
        : negociacaoArrastandoId.value;

    negociacaoArrastandoId.value = null;
    etapaSoltandoId.value = null;

    if (negociacaoIdNum === null || Number.isNaN(negociacaoIdNum)) {
        return;
    }

    const negociacao = props.negociacoes.find(
        (item) => item.id === negociacaoIdNum,
    );

    if (!negociacao || negociacao.etapaId === etapaId) {
        return;
    }

    router.patch(
        updateEtapa.url({ negociacao: negociacaoIdNum }),
        { etapa_funil_id: etapaId },
        {
            preserveScroll: true,
            only: ['negociacoes'],
        },
    );
};

const abrirCard = (id: number): void => {
    if (suprimirClique.value) {
        return;
    }

    negociacaoId.value = id;
};

const alterarRegra = (): void => {
    if (funilAtivo.value === null) {
        return;
    }

    router.patch(
        cycleDistribuicao.url({ funil: funilAtivo.value.id }),
        {},
        {
            preserveScroll: true,
            only: ['funis', 'negociacoes'],
        },
    );
};
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div class="flex shrink-0 flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-3.5">
                <div class="flex gap-1.5 rounded-[9px] bg-[#EFECE4] p-1">
                    <button
                        v-for="funil in props.funis"
                        :key="funil.id"
                        type="button"
                        class="cursor-pointer rounded-md px-3.5 py-[7px] text-[12.5px] font-medium"
                        :class="
                            funilAtivo?.id === funil.id
                                ? 'bg-card text-foreground shadow-[0_1px_3px_rgba(23,27,33,0.10)]'
                                : 'bg-transparent text-muted-foreground'
                        "
                        @click="selecionarFunil(funil.id)"
                    >
                        {{ funil.nome }}
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <div
                        class="flex gap-0.5 rounded-lg border border-border bg-card p-0.5"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-[5px] text-[12px]"
                            :class="
                                visao === 'funil'
                                    ? 'bg-primary/15 text-foreground'
                                    : 'bg-transparent text-muted-foreground'
                            "
                            @click="visao = 'funil'"
                        >
                            Funil
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-[5px] text-[12px]"
                            :class="
                                visao === 'lista'
                                    ? 'bg-primary/15 text-foreground'
                                    : 'bg-transparent text-muted-foreground'
                            "
                            @click="visao = 'lista'"
                        >
                            Negociações
                        </button>
                    </div>
                    <span class="text-[12px] text-muted-foreground">
                        Distribuição:
                        <strong class="font-medium text-muted-foreground">
                            {{ funilAtivo?.distribuicao ?? '—' }}
                        </strong>
                    </span>
                    <button
                        type="button"
                        class="cursor-pointer rounded-[7px] border border-border bg-card px-[13px] py-[7px] text-[12.5px] text-muted-foreground"
                        :disabled="funilAtivo === null"
                        @click="alterarRegra"
                    >
                        Alterar regra
                    </button>
                    <Link
                        :href="funisIndex()"
                        class="cursor-pointer rounded-[7px] border border-border bg-card px-[13px] py-[7px] text-[12.5px] text-muted-foreground"
                    >
                        Editar funis
                    </Link>
                    <Link
                        :href="
                            createNegociacao.url(
                                {},
                                {
                                    query: funilAtivo
                                        ? { funil_id: funilAtivo.id }
                                        : {},
                                },
                            )
                        "
                        class="cursor-pointer rounded-[7px] border border-primary bg-primary px-[13px] py-[7px] text-[12.5px] text-primary-foreground"
                    >
                        Nova lead
                    </Link>
                </div>
            </div>

            <div
                class="border-border bg-card flex flex-wrap items-end gap-x-2 gap-y-2.5 rounded-[10px] border px-3 py-2.5"
            >
                <span
                    class="text-muted-foreground mb-1.5 font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.1em] uppercase"
                >
                    Filtros
                </span>

                <div class="flex min-w-0 flex-col gap-1">
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.08em] uppercase"
                    >
                        Etapa
                    </span>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger :class="filtroTriggerClass">
                            <span class="truncate">{{ rotuloEtapas }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="max-h-72 w-64 overflow-y-auto p-1"
                        >
                            <DropdownMenuLabel>Etapas</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <button
                                v-for="etapa in opcoesEtapa"
                                :key="etapa.id"
                                type="button"
                                class="hover:bg-accent flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                @click="alternarEtapa(etapa.id)"
                            >
                                <input
                                    type="checkbox"
                                    class="pointer-events-none size-3.5 shrink-0 accent-primary"
                                    tabindex="-1"
                                    :checked="
                                        etapasSelecionadas.includes(etapa.id)
                                    "
                                />
                                <span class="truncate">{{ etapa.nome }}</span>
                            </button>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.08em] uppercase"
                    >
                        Empresa
                    </span>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger :class="filtroTriggerClass">
                            <span class="truncate">{{ rotuloEmpresas }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="max-h-72 w-64 overflow-y-auto p-1"
                        >
                            <DropdownMenuLabel>Empresa</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <button
                                v-for="empresa in opcoesEmpresa"
                                :key="empresa"
                                type="button"
                                class="hover:bg-accent flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                @click="alternarEmpresa(empresa)"
                            >
                                <input
                                    type="checkbox"
                                    class="pointer-events-none size-3.5 shrink-0 accent-primary"
                                    tabindex="-1"
                                    :checked="
                                        empresasSelecionadas.includes(empresa)
                                    "
                                />
                                <span class="truncate">{{ empresa }}</span>
                            </button>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.08em] uppercase"
                    >
                        Contato
                    </span>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger :class="filtroTriggerClass">
                            <span class="truncate">{{ rotuloContatos }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="max-h-72 w-64 overflow-y-auto p-1"
                        >
                            <DropdownMenuLabel>Contato</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <button
                                v-for="contato in opcoesContato"
                                :key="contato"
                                type="button"
                                class="hover:bg-accent flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                @click="alternarContato(contato)"
                            >
                                <input
                                    type="checkbox"
                                    class="pointer-events-none size-3.5 shrink-0 accent-primary"
                                    tabindex="-1"
                                    :checked="
                                        contatosSelecionados.includes(contato)
                                    "
                                />
                                <span class="truncate">{{ contato }}</span>
                            </button>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.08em] uppercase"
                    >
                        Canal
                    </span>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger :class="filtroTriggerClass">
                            <span class="truncate">{{ rotuloCanais }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="max-h-72 w-56 overflow-y-auto p-1"
                        >
                            <DropdownMenuLabel>Canal</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <button
                                v-for="canal in opcoesCanal"
                                :key="canal"
                                type="button"
                                class="hover:bg-accent flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                @click="alternarCanal(canal)"
                            >
                                <input
                                    type="checkbox"
                                    class="pointer-events-none size-3.5 shrink-0 accent-primary"
                                    tabindex="-1"
                                    :checked="
                                        canaisSelecionados.includes(canal)
                                    "
                                />
                                <span class="truncate">{{ canal }}</span>
                            </button>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.08em] uppercase"
                    >
                        Atendimento
                    </span>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger :class="filtroTriggerClass">
                            <span class="truncate">{{
                                rotuloAtendimentos
                            }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="max-h-72 w-64 overflow-y-auto p-1"
                        >
                            <DropdownMenuLabel>Atendimento</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <button
                                v-for="status in opcoesAtendimento"
                                :key="status"
                                type="button"
                                class="hover:bg-accent flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                @click="alternarAtendimento(status)"
                            >
                                <input
                                    type="checkbox"
                                    class="pointer-events-none size-3.5 shrink-0 accent-primary"
                                    tabindex="-1"
                                    :checked="
                                        atendimentosSelecionados.includes(
                                            status,
                                        )
                                    "
                                />
                                <span class="truncate">{{ status }}</span>
                            </button>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.08em] uppercase"
                    >
                        Qualificação
                    </span>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger :class="filtroTriggerClass">
                            <span class="truncate">{{
                                rotuloQualificacoes
                            }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="max-h-72 w-64 overflow-y-auto p-1"
                        >
                            <DropdownMenuLabel>Qualificação</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <button
                                v-for="status in opcoesQualificacao"
                                :key="status"
                                type="button"
                                class="hover:bg-accent flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                @click="alternarQualificacao(status)"
                            >
                                <input
                                    type="checkbox"
                                    class="pointer-events-none size-3.5 shrink-0 accent-primary"
                                    tabindex="-1"
                                    :checked="
                                        qualificacoesSelecionadas.includes(
                                            status,
                                        )
                                    "
                                />
                                <span class="truncate">{{ status }}</span>
                            </button>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="flex min-w-0 flex-col gap-1">
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.08em] uppercase"
                    >
                        Responsável
                    </span>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger :class="filtroTriggerClass">
                            <span class="truncate">{{
                                rotuloResponsaveis
                            }}</span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            class="max-h-72 w-56 overflow-y-auto p-1"
                        >
                            <DropdownMenuLabel>Responsável</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <button
                                v-for="responsavel in opcoesResponsavel"
                                :key="responsavel"
                                type="button"
                                class="hover:bg-accent flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                @click="alternarResponsavel(responsavel)"
                            >
                                <input
                                    type="checkbox"
                                    class="pointer-events-none size-3.5 shrink-0 accent-primary"
                                    tabindex="-1"
                                    :checked="
                                        responsaveisSelecionados.includes(
                                            responsavel,
                                        )
                                    "
                                />
                                <span class="truncate">{{ responsavel }}</span>
                            </button>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <button
                    v-if="filtrosAtivos"
                    type="button"
                    class="mb-0.5 cursor-pointer rounded-md px-2.5 py-1.5 text-[12px] font-medium text-[#9B3B2F] transition-colors hover:bg-[#FDF3F2]"
                    @click="limparFiltros"
                >
                    Limpar
                </button>
            </div>
        </div>

        <div
            v-if="visao === 'funil'"
            class="flex min-h-0 flex-1 items-stretch gap-[13px] overflow-x-auto overflow-y-hidden pb-1"
        >
            <div
                v-for="coluna in colunas"
                :key="coluna.id"
                class="flex h-full max-h-full w-[268px] shrink-0 flex-col gap-[11px] overflow-hidden rounded-[10px] border border-border bg-secondary px-[11px] pb-3.5"
            >
                <div
                    class="-mx-[11px] flex shrink-0 flex-col gap-[5px] px-[13px] pt-2.5 pb-[11px]"
                    :style="{ background: coluna.corBg }"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="text-[12.5px] font-semibold tracking-[-0.005em]"
                            :style="{ color: coluna.corFg }"
                        >
                            {{ coluna.nome }}
                        </span>
                        <span
                            class="rounded-full px-[7px] py-px font-[family-name:var(--font-crm-mono)] text-[11px]"
                            :style="{
                                color: coluna.corFg,
                                background: 'rgba(255,255,255,0.16)',
                            }"
                        >
                            {{ coluna.qtd }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-[11px]"
                            :style="{ color: coluna.corFg }"
                        >
                            {{ coluna.valor }}
                        </span>
                    </div>
                </div>
                <div
                    class="flex min-h-0 flex-1 flex-col gap-[9px] overflow-y-auto rounded-lg transition-shadow"
                    :class="
                        etapaSoltandoId === coluna.id
                            ? 'bg-card/60 ring-2 ring-primary ring-inset'
                            : ''
                    "
                    @dragover="duranteArraste($event, coluna.id)"
                    @dragleave="sairColuna($event, coluna.id)"
                    @drop="soltarNaEtapa($event, coluna.id)"
                >
                    <div
                        v-for="card in coluna.cards"
                        :key="card.id"
                        role="button"
                        tabindex="0"
                        draggable="true"
                        class="flex cursor-grab flex-col gap-[9px] rounded-[9px] border border-border p-3 text-left active:cursor-grabbing hover:border-primary hover:shadow-[0_2px_10px_rgba(23,27,33,0.06)]"
                        :class="[
                            card.cardFundo ? '' : 'bg-card',
                            negociacaoArrastandoId === card.id
                                ? 'opacity-50'
                                : '',
                        ]"
                        :style="
                            card.cardFundo
                                ? { background: card.cardFundo }
                                : undefined
                        "
                        @dragstart="iniciarArraste($event, card.id)"
                        @dragend="finalizarArraste"
                        @click="abrirCard(card.id)"
                        @keydown.enter="abrirCard(card.id)"
                    >
                        <div class="flex flex-col gap-[3px]">
                            <span
                                class="text-[13px] leading-snug font-medium text-foreground"
                            >
                                {{ card.nome }}
                            </span>
                            <span class="text-[11.5px] text-muted-foreground">
                                {{ card.assunto }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                v-if="card.statusAtendimento"
                                class="rounded-full px-2 py-[3px] text-[10.5px] font-medium"
                                :style="{
                                    color: card.statusAtendimentoCor,
                                    background: card.statusAtendimentoBg,
                                }"
                            >
                                {{ card.statusAtendimento }}
                            </span>
                            <span
                                v-if="card.statusQualificacao"
                                class="rounded-full px-2 py-[3px] text-[10.5px] font-medium"
                                :style="{
                                    color: card.statusQualificacaoCor,
                                    background: card.statusQualificacaoBg,
                                }"
                            >
                                {{ card.statusQualificacao }}
                            </span>
                            <span
                                class="inline-flex items-center gap-[5px] rounded-full bg-muted px-2 py-[3px] text-[10.5px] text-muted-foreground"
                            >
                                <span
                                    class="h-[5px] w-[5px] rounded-full"
                                    :style="{ background: card.canalCor }"
                                />
                                {{ card.canal }}
                            </span>
                            <span
                                class="rounded-full px-2 py-[3px] font-[family-name:var(--font-crm-mono)] text-[10.5px]"
                                :style="{
                                    color: card.slaCor,
                                    background: card.slaBg,
                                }"
                            >
                                {{ card.slaLabel }}
                            </span>
                            <span
                                v-if="card.capi.estado === 'enviavel'"
                                class="rounded-full px-2 py-[3px] text-[10.5px]"
                                :style="{
                                    color: card.capi.cor,
                                    background: card.capi.bg,
                                }"
                                title="A API de Conversões recebe os eventos desta negociação"
                            >
                                {{ card.capi.label }}
                            </span>
                        </div>
                        <div
                            class="flex flex-col gap-1.5 border-t border-[#F2EFE8] pt-2"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span
                                        class="grid h-[22px] w-[22px] shrink-0 place-items-center rounded-full bg-primary/15 text-[10px] font-semibold text-primary"
                                        :title="card.responsavel"
                                    >
                                        {{ card.iniciais }}
                                    </span>
                                    <span
                                        class="truncate text-[11.5px] text-foreground"
                                        :title="card.responsavel"
                                    >
                                        {{ card.responsavel }}
                                    </span>
                                </span>
                                <span
                                    class="font-[family-name:var(--font-crm-mono)] shrink-0 text-xs text-foreground"
                                >
                                    {{ card.valorFmt }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-2 text-[11px]"
                            >
                                <span class="text-muted-foreground">{{
                                    card.dataLimiteLabel
                                }}</span>
                                <span
                                    class="font-[family-name:var(--font-crm-mono)] font-medium"
                                    :style="{ color: card.dataLimiteCor }"
                                >
                                    {{ card.dataLimite }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="max-w-[1180px] overflow-hidden rounded-[10px] border border-border bg-card"
        >
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-card">
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Negociação
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Empresa / contato
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Etapa
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Próxima tarefa
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Previsão
                        </th>
                        <th
                            class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Valor
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="negociacoesFiltradas.length === 0">
                        <td
                            colspan="6"
                            class="border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px] text-muted-foreground"
                        >
                            Nenhuma negociação com esses filtros.
                        </td>
                    </tr>
                    <tr
                        v-for="linha in negociacoesFiltradas"
                        :key="linha.id"
                        class="cursor-pointer hover:bg-card"
                        :class="negociacaoId === linha.id ? 'bg-card' : ''"
                        @click="negociacaoId = linha.id"
                    >
                        <td class="border-t border-[#F2EFE8] px-4 py-3">
                            <div class="flex flex-col gap-[3px]">
                                <span class="text-[13px] text-foreground">
                                    {{ linha.assunto }}
                                </span>
                                <span class="text-[11px] text-muted-foreground">
                                    {{ linha.responsavel }} ·
                                    {{ linha.funilNome }}
                                </span>
                            </div>
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                        >
                            {{ linha.conta }}
                        </td>
                        <td class="border-t border-[#F2EFE8] px-4 py-3">
                            <span
                                class="rounded-full bg-secondary px-[9px] py-[3px] text-[11px] text-muted-foreground"
                            >
                                {{ linha.etapa }}
                            </span>
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                            :style="{ color: linha.tarefaCor }"
                        >
                            {{ linha.tarefa }}
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 font-[family-name:var(--font-crm-mono)] text-xs text-muted-foreground"
                        >
                            {{ linha.previsao }}
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-right font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                        >
                            {{ linha.valorFmt }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Teleport defer to="#crm-drawer-root">
            <CrmNegociacaoDrawer
                v-if="negociacaoAberta"
                :modelo="negociacaoAberta.drawer"
                :negociacao-id="negociacaoAberta.id"
                @close="negociacaoId = null"
            />
        </Teleport>
    </div>
</template>
