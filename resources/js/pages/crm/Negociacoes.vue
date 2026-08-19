<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { updateEtapa } from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmNegociacaoDrawer from '@/components/crm/CrmNegociacaoDrawer.vue';
import type { FunilView, NegociacaoLinha } from '@/types/crm';

const { funis, negociacoes } = defineProps<{
    funis: FunilView[];
    negociacoes: NegociacaoLinha[];
}>();

const visao = ref<'funil' | 'lista'>('funil');
const funilId = ref<number | null>(funis[0]?.id ?? null);
const negociacaoId = ref<number | null>(null);
const negociacaoArrastandoId = ref<number | null>(null);
const etapaSoltandoId = ref<number | null>(null);
const suprimirClique = ref(false);

const funilAtivo = computed(
    () => funis.find((funil) => funil.id === funilId.value) ?? funis[0] ?? null,
);

const linhasDoFunil = computed(() => {
    if (funilAtivo.value === null) {
        return [];
    }

    return negociacoes.filter(
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
        negociacoes.find(
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

const sairColuna = (etapaId: number): void => {
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

    const negociacao = negociacoes.find((item) => item.id === negociacaoIdNum);

    if (!negociacao || negociacao.etapaId === etapaId) {
        return;
    }

    router.patch(
        updateEtapa.url(negociacaoIdNum),
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
</script>

<template>
    <div>
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3.5">
                <div class="flex gap-1.5 rounded-[9px] bg-[#EFECE4] p-1">
                    <button
                        v-for="funil in funis"
                        :key="funil.id"
                        type="button"
                        class="cursor-pointer rounded-md px-3.5 py-[7px] text-[12.5px] font-medium"
                        :class="
                            funilAtivo?.id === funil.id
                                ? 'bg-white text-[#171B21] shadow-[0_1px_3px_rgba(23,27,33,0.10)]'
                                : 'bg-transparent text-[#77808E]'
                        "
                        @click="selecionarFunil(funil.id)"
                    >
                        {{ funil.nome }}
                    </button>
                </div>
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex gap-0.5 rounded-lg border border-[#E3DFD6] bg-white p-0.5"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-md px-3 py-[5px] text-[12px]"
                            :class="
                                visao === 'funil'
                                    ? 'bg-[#EFEBE2] text-[#171B21]'
                                    : 'bg-transparent text-[#77808E]'
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
                                    ? 'bg-[#EFEBE2] text-[#171B21]'
                                    : 'bg-transparent text-[#77808E]'
                            "
                            @click="visao = 'lista'"
                        >
                            Lista
                        </button>
                    </div>
                    <span class="text-[12px] text-[#77808E]">
                        Distribuição:
                        <strong class="font-medium text-[#3C4450]">
                            {{ funilAtivo?.distribuicao ?? '—' }}
                        </strong>
                    </span>
                    <button
                        type="button"
                        class="cursor-pointer rounded-[7px] border border-[#E3DFD6] bg-white px-[13px] py-[7px] text-[12.5px] text-[#3C4450]"
                    >
                        Alterar regra
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-[7px] border border-[#171B21] bg-[#171B21] px-[13px] py-[7px] text-[12.5px] text-[#FBF9F4]"
                    >
                        Nova negociação
                    </button>
                </div>
            </div>

            <div
                v-if="visao === 'funil'"
                class="flex items-start gap-[13px] overflow-x-auto pb-2"
            >
                <div
                    v-for="coluna in colunas"
                    :key="coluna.id"
                    class="flex w-[268px] shrink-0 flex-col gap-[11px] overflow-hidden rounded-[10px] border border-[#E7E3DA] bg-[#F1EEE7] px-[11px] pb-3.5"
                >
                    <div
                        class="-mx-[11px] flex flex-col gap-[5px] px-[13px] pt-2.5 pb-[11px]"
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
                            <span
                                class="text-right text-[10.5px]"
                                :style="{ color: coluna.corSuave }"
                            >
                                {{ coluna.obrigatorio }}
                            </span>
                        </div>
                    </div>
                    <div
                        class="flex min-h-[80px] flex-col gap-[9px] rounded-lg transition-shadow"
                        :class="
                            etapaSoltandoId === coluna.id
                                ? 'bg-white/60 ring-2 ring-[#C79A4E] ring-inset'
                                : ''
                        "
                        @dragover="duranteArraste($event, coluna.id)"
                        @dragleave="sairColuna(coluna.id)"
                        @drop="soltarNaEtapa($event, coluna.id)"
                    >
                        <div
                            v-for="card in coluna.cards"
                            :key="card.id"
                            role="button"
                            tabindex="0"
                            draggable="true"
                            class="flex cursor-grab flex-col gap-[9px] rounded-[9px] border border-[#E3DFD6] bg-white p-3 text-left active:cursor-grabbing hover:border-[#C79A4E] hover:shadow-[0_2px_10px_rgba(23,27,33,0.06)]"
                            :class="
                                negociacaoArrastandoId === card.id
                                    ? 'opacity-50'
                                    : ''
                            "
                            @dragstart="iniciarArraste($event, card.id)"
                            @dragend="finalizarArraste"
                            @click="abrirCard(card.id)"
                            @keydown.enter="abrirCard(card.id)"
                        >
                            <div class="flex flex-col gap-[3px]">
                                <span
                                    class="text-[13px] leading-snug font-medium text-[#171B21]"
                                >
                                    {{ card.nome }}
                                </span>
                                <span class="text-[11.5px] text-[#77808E]">
                                    {{ card.assunto }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    class="inline-flex items-center gap-[5px] rounded-full bg-[#F4F2EC] px-2 py-[3px] text-[10.5px] text-[#3C4450]"
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
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-[#F2EFE8] pt-2"
                            >
                                <span
                                    class="font-[family-name:var(--font-crm-mono)] text-xs text-[#171B21]"
                                >
                                    {{ card.valorFmt }}
                                </span>
                                <span
                                    class="grid h-[22px] w-[22px] place-items-center rounded-full bg-[#EFE7D8] text-[10px] font-semibold text-[#6F5730]"
                                >
                                    {{ card.iniciais }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="max-w-[1180px] overflow-hidden rounded-[10px] border border-[#E3DFD6] bg-white"
            >
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[#FBFAF7]">
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Negociação
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Empresa / contato
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Etapa
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Próxima tarefa
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Previsão
                            </th>
                            <th
                                class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Valor
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="linhasDoFunil.length === 0">
                            <td
                                colspan="6"
                                class="border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px] text-[#77808E]"
                            >
                                Nenhuma negociação neste funil.
                            </td>
                        </tr>
                        <tr
                            v-for="linha in linhasDoFunil"
                            :key="linha.id"
                            class="cursor-pointer hover:bg-[#FBFAF7]"
                            :class="
                                negociacaoId === linha.id ? 'bg-[#FBFAF7]' : ''
                            "
                            @click="negociacaoId = linha.id"
                        >
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <div class="flex flex-col gap-[3px]">
                                    <span class="text-[13px] text-[#171B21]">
                                        {{ linha.assunto }}
                                    </span>
                                    <span class="text-[11px] text-[#77808E]">
                                        {{ linha.responsavel }} ·
                                        {{ linha.funilNome }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-[#3C4450]"
                            >
                                {{ linha.conta }}
                            </td>
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <span
                                    class="rounded-full bg-[#F1EEE7] px-[9px] py-[3px] text-[11px] text-[#3C4450]"
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
                                class="border-t border-[#F2EFE8] px-4 py-3 font-[family-name:var(--font-crm-mono)] text-xs text-[#3C4450]"
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
        </div>

        <Teleport defer to="#crm-drawer-root">
            <CrmNegociacaoDrawer
                v-if="negociacaoAberta"
                :modelo="negociacaoAberta.drawer"
                @close="negociacaoId = null"
            />
        </Teleport>
    </div>
</template>
