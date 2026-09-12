<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import {
    create as createNegociacao,
    cycleDistribuicao,
    updateEtapa,
} from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmNegociacaoDrawer from '@/components/crm/CrmNegociacaoDrawer.vue';
import { index as funisIndex } from '@/routes/admin/funis';
import type { FunilView, NegociacaoLinha } from '@/types/crm';

const props = defineProps<{
    funis: FunilView[];
    negociacoes: NegociacaoLinha[];
}>();

const visao = ref<'funil' | 'lista'>('funil');
const funilId = ref<number | null>(props.funis[0]?.id ?? null);
const negociacaoId = ref<number | null>(null);
const negociacaoArrastandoId = ref<number | null>(null);
const etapaSoltandoId = ref<number | null>(null);
const suprimirClique = ref(false);

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

const linhasDoFunil = computed(() => {
    if (funilAtivo.value === null) {
        return [];
    }

    return props.negociacoes.filter(
        (negociacao) => negociacao.funilId === funilAtivo.value?.id,
    );
});

const colunas = computed(() => {
    const funil = funilAtivo.value;

    if (!funil) {
        return [];
    }

    return funil.etapas.map((etapa) => {
        const cards = linhasDoFunil.value.filter(
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

const compactar = (valor: number): string => {
    if (valor >= 1000) {
        const casas = valor >= 100000 ? 0 : 1;

        return `R$ ${(valor / 1000).toFixed(casas).replace('.', ',')}k`;
    }

    return `R$ ${Math.round(valor)}`;
};

const selecionarFunil = (id: number): void => {
    funilId.value = id;
    negociacaoId.value = null;
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
            <div class="flex shrink-0 items-center justify-between gap-3.5">
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
                <div class="flex items-center gap-2.5">
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
                        :href="createNegociacao.url({})"
                        class="cursor-pointer rounded-[7px] border border-primary bg-primary px-[13px] py-[7px] text-[12.5px] text-primary-foreground"
                    >
                        Nova negociação
                    </Link>
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
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-1.5"
                                    >
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
                        <tr v-if="linhasDoFunil.length === 0">
                            <td
                                colspan="6"
                                class="border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px] text-muted-foreground"
                            >
                                Nenhuma negociação neste funil.
                            </td>
                        </tr>
                        <tr
                            v-for="linha in linhasDoFunil"
                            :key="linha.id"
                            class="cursor-pointer hover:bg-card"
                            :class="
                                negociacaoId === linha.id ? 'bg-card' : ''
                            "
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
