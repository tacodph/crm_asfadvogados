<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AgendaTarefaController from '@/actions/App/Http/Controllers/AgendaTarefaController';
import { edit as editNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';
import { meuDia } from '@/routes';

type ItemTarefa = {
    id: number;
    negociacaoId: number;
    descricao: string;
    tipo: string;
    tipoLabel: string;
    data: string;
    hora: string | null;
    diasAtraso: number;
    horarioPassou: boolean;
    assunto: string;
    lead: string | null;
    contatoNome: string | null;
    telefone: string | null;
    funilNome: string | null;
    etapaNome: string | null;
    responsavelNome: string | null;
    diasSemContato: number | null;
};

type ItemSemProximoPasso = {
    negociacaoId: number;
    assunto: string;
    lead: string | null;
    contatoNome: string | null;
    telefone: string | null;
    funilNome: string | null;
    etapaNome: string | null;
    responsavelNome: string | null;
    diasSemContato: number | null;
};

type GrupoTarefas =
    | 'followUpsPendentes'
    | 'followUpsHoje'
    | 'atrasadas'
    | 'reunioesHoje'
    | 'demaisHoje';

type Agenda = Record<GrupoTarefas, ItemTarefa[]> & {
    data: string;
    semProximoPasso: ItemSemProximoPasso[];
    totais: Record<GrupoTarefas | 'semProximoPasso', number>;
};

const props = defineProps<{
    escopo: 'minhas' | 'equipe';
    podeVerEquipe: boolean;
    agenda: Agenda;
}>();

const SECOES: {
    key: GrupoTarefas | 'semProximoPasso';
    titulo: string;
    vazio: string;
    cor: string;
}[] = [
    {
        key: 'followUpsPendentes',
        titulo: 'Follow-ups pendentes',
        vazio: 'Nenhum follow-up em atraso.',
        cor: '#9B3B2F',
    },
    {
        key: 'followUpsHoje',
        titulo: 'Follow-ups de hoje',
        vazio: 'Nenhum follow-up para hoje.',
        cor: '#B7791F',
    },
    {
        key: 'atrasadas',
        titulo: 'Atividades atrasadas',
        vazio: 'Nenhuma atividade atrasada.',
        cor: '#9B3B2F',
    },
    {
        key: 'reunioesHoje',
        titulo: 'Reuniões e compromissos',
        vazio: 'Nenhuma reunião hoje.',
        cor: '#8C4A6B',
    },
    {
        key: 'demaisHoje',
        titulo: 'Demais tarefas de hoje',
        vazio: 'Nenhuma outra tarefa para hoje.',
        cor: '#3F5E8C',
    },
    {
        key: 'semProximoPasso',
        titulo: 'Negociações sem próximo passo',
        vazio: 'Todas as negociações abertas têm um próximo passo.',
        cor: '#6B7280',
    },
];

const adiandoId = ref<number | null>(null);
const dataPersonalizada = ref('');
const processandoId = ref<number | null>(null);

const dataLabel = computed(() => {
    const [y, m, d] = props.agenda.data.split('-').map(Number);

    return new Date(y, m - 1, d).toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
    });
});

const tudoEmDia = computed(() =>
    SECOES.every((secao) => props.agenda.totais[secao.key] === 0),
);

function somarDias(iso: string, dias: number): string {
    const [y, m, d] = iso.split('-').map(Number);
    const data = new Date(Date.UTC(y, m - 1, d + dias));

    return data.toISOString().slice(0, 10);
}

function proximaSegunda(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    const diaSemana = new Date(Date.UTC(y, m - 1, d)).getUTCDay();
    const ate = (8 - diaSemana) % 7 || 7;

    return somarDias(iso, ate);
}

function formatarData(iso: string): string {
    const [, m, d] = iso.split('-');

    return `${d}/${m}`;
}

function irPara(key: string): void {
    document
        .getElementById(`secao-${key}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function trocarEscopo(escopo: 'minhas' | 'equipe'): void {
    router.get(meuDia.url(), escopo === 'equipe' ? { escopo } : {}, {
        preserveScroll: true,
    });
}

function concluir(item: ItemTarefa): void {
    processandoId.value = item.id;
    router.patch(
        AgendaTarefaController.concluir.url({ tarefa: item.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => (processandoId.value = null),
        },
    );
}

function adiar(item: ItemTarefa, data: string): void {
    if (!data) {
        return;
    }

    processandoId.value = item.id;
    router.patch(
        AgendaTarefaController.adiar.url({ tarefa: item.id }),
        { data },
        {
            preserveScroll: true,
            onSuccess: () => {
                adiandoId.value = null;
                dataPersonalizada.value = '';
            },
            onFinish: () => (processandoId.value = null),
        },
    );
}

function quandoLabel(item: ItemTarefa): string {
    if (item.diasAtraso > 0) {
        return item.diasAtraso === 1
            ? 'ontem'
            : `${item.diasAtraso} dias de atraso`;
    }

    return item.hora ?? 'sem horário';
}

function semContatoLabel(dias: number | null): string {
    if (dias === null) {
        return 'nenhum contato registrado';
    }

    if (dias === 0) {
        return 'contato hoje';
    }

    return dias === 1 ? 'sem contato há 1 dia' : `sem contato há ${dias} dias`;
}
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p
                class="text-muted-foreground m-0 text-[13px] first-letter:uppercase"
            >
                {{ dataLabel }}
            </p>
            <div
                v-if="podeVerEquipe"
                class="border-border bg-card flex gap-0.5 rounded-lg border p-0.5"
            >
                <button
                    type="button"
                    class="cursor-pointer rounded-md border-0 px-3 py-[5px] text-xs"
                    :class="
                        escopo === 'minhas'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground bg-transparent'
                    "
                    @click="trocarEscopo('minhas')"
                >
                    Minhas
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-md border-0 px-3 py-[5px] text-xs"
                    :class="
                        escopo === 'equipe'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground bg-transparent'
                    "
                    @click="trocarEscopo('equipe')"
                >
                    Equipe
                </button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <button
                v-for="secao in SECOES"
                :key="secao.key"
                type="button"
                class="border-border bg-card hover:border-primary flex cursor-pointer flex-col gap-1.5 rounded-[10px] border px-4 py-3.5 text-left"
                @click="irPara(secao.key)"
            >
                <span
                    class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-[0.12em]"
                >
                    {{ secao.titulo }}
                </span>
                <span
                    class="font-[family-name:var(--font-crm-display)] text-[28px] font-medium leading-none"
                    :style="{
                        color:
                            agenda.totais[secao.key] > 0
                                ? secao.cor
                                : 'var(--muted-foreground)',
                    }"
                >
                    {{ agenda.totais[secao.key] }}
                </span>
            </button>
        </div>

        <div
            v-if="tudoEmDia"
            class="border-border bg-card flex flex-col items-center gap-[7px] rounded-[10px] border px-5 py-[46px]"
        >
            <span
                class="text-muted-foreground font-[family-name:var(--font-crm-display)] text-[17px]"
            >
                Nenhum follow-up pendente
            </span>
            <span class="text-muted-foreground text-xs">
                Tudo em dia por aqui.
            </span>
        </div>

        <template v-else>
            <section
                v-for="secao in SECOES"
                :id="`secao-${secao.key}`"
                :key="secao.key"
                class="border-border bg-card flex scroll-mt-4 flex-col gap-2 rounded-[10px] border px-4 py-4"
            >
                <h2
                    class="text-foreground m-0 flex items-center gap-2 font-[family-name:var(--font-crm-display)] text-[15px] font-medium"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :style="{ background: secao.cor }"
                    />
                    {{ secao.titulo }}
                    <span class="text-muted-foreground text-[12px] font-normal">
                        {{ agenda.totais[secao.key] }}
                    </span>
                </h2>

                <p
                    v-if="agenda.totais[secao.key] === 0"
                    class="text-muted-foreground m-0 text-[12.5px]"
                >
                    {{ secao.vazio }}
                </p>

                <ul
                    v-else-if="secao.key === 'semProximoPasso'"
                    class="m-0 flex list-none flex-col gap-2 p-0"
                >
                    <li
                        v-for="item in agenda.semProximoPasso"
                        :key="item.negociacaoId"
                        class="border-border flex flex-col gap-2 rounded-lg border px-3.5 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <span
                                class="text-foreground text-[13px] font-medium"
                            >
                                {{ item.lead }}
                                <span class="text-muted-foreground font-normal">
                                    · {{ item.assunto }}
                                </span>
                            </span>
                            <span class="text-muted-foreground text-[12px]">
                                {{ item.etapaNome }} · {{ item.funilNome }}
                                <template v-if="item.telefone">
                                    · {{ item.telefone }}
                                </template>
                                · {{ semContatoLabel(item.diasSemContato) }}
                                <template v-if="escopo === 'equipe'">
                                    · {{ item.responsavelNome }}
                                </template>
                            </span>
                        </div>
                        <Link
                            :href="editNegociacao.url(item.negociacaoId)"
                            class="border-primary bg-primary text-primary-foreground shrink-0 rounded-lg border px-3 py-1.5 text-center text-[12px] font-medium no-underline"
                        >
                            Agendar follow-up
                        </Link>
                    </li>
                </ul>

                <ul v-else class="m-0 flex list-none flex-col gap-2 p-0">
                    <li
                        v-for="item in agenda[secao.key as GrupoTarefas]"
                        :key="item.id"
                        class="border-border flex flex-col gap-2 rounded-lg border px-3.5 py-3"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <span
                                    class="text-foreground text-[13px] font-medium"
                                >
                                    <span
                                        class="mr-1.5 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :style="{
                                            background: `color-mix(in srgb, ${secao.cor} 14%, transparent)`,
                                            color: secao.cor,
                                        }"
                                    >
                                        {{ item.tipoLabel }}
                                    </span>
                                    {{ item.descricao }}
                                </span>
                                <span class="text-muted-foreground text-[12px]">
                                    <span
                                        :class="
                                            item.diasAtraso > 0 ||
                                            item.horarioPassou
                                                ? 'font-medium text-[#9B3B2F]'
                                                : ''
                                        "
                                    >
                                        {{ quandoLabel(item) }}
                                    </span>
                                    · {{ item.lead }} · {{ item.assunto }} ·
                                    {{ item.etapaNome }}
                                    <template v-if="item.telefone">
                                        · {{ item.telefone }}
                                    </template>
                                </span>
                                <span
                                    class="text-muted-foreground text-[11.5px]"
                                >
                                    {{ semContatoLabel(item.diasSemContato) }}
                                    <template v-if="escopo === 'equipe'">
                                        · {{ item.responsavelNome }}
                                    </template>
                                </span>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button
                                    type="button"
                                    class="border-primary bg-primary text-primary-foreground cursor-pointer rounded-lg border px-3 py-1.5 text-[12px] font-medium disabled:opacity-60"
                                    :disabled="processandoId === item.id"
                                    @click="concluir(item)"
                                >
                                    Concluir
                                </button>
                                <button
                                    type="button"
                                    class="border-border bg-card text-muted-foreground cursor-pointer rounded-lg border px-3 py-1.5 text-[12px]"
                                    @click="
                                        adiandoId =
                                            adiandoId === item.id
                                                ? null
                                                : item.id
                                    "
                                >
                                    Adiar
                                </button>
                                <Link
                                    :href="
                                        editNegociacao.url(item.negociacaoId)
                                    "
                                    class="border-border bg-card text-muted-foreground rounded-lg border px-3 py-1.5 text-[12px] no-underline"
                                >
                                    Abrir
                                </Link>
                            </div>
                        </div>

                        <div
                            v-if="adiandoId === item.id"
                            class="border-border flex flex-wrap items-center gap-2 border-t pt-2"
                        >
                            <span class="text-muted-foreground text-[12px]">
                                Adiar para:
                            </span>
                            <button
                                v-for="opcao in [
                                    {
                                        label: 'Amanhã',
                                        data: somarDias(agenda.data, 1),
                                    },
                                    {
                                        label: '+3 dias',
                                        data: somarDias(agenda.data, 3),
                                    },
                                    {
                                        label: 'Próxima segunda',
                                        data: proximaSegunda(agenda.data),
                                    },
                                ]"
                                :key="opcao.label"
                                type="button"
                                class="border-border bg-card text-foreground cursor-pointer rounded-full border px-3 py-1 text-[12px] disabled:opacity-60"
                                :disabled="processandoId === item.id"
                                :title="formatarData(opcao.data)"
                                @click="adiar(item, opcao.data)"
                            >
                                {{ opcao.label }}
                            </button>
                            <input
                                v-model="dataPersonalizada"
                                type="date"
                                :min="agenda.data"
                                class="border-border bg-card rounded-lg border px-2 py-1 text-[12px]"
                            />
                            <button
                                type="button"
                                class="border-primary text-primary cursor-pointer rounded-full border px-3 py-1 text-[12px] disabled:opacity-60"
                                :disabled="
                                    !dataPersonalizada ||
                                    processandoId === item.id
                                "
                                @click="adiar(item, dataPersonalizada)"
                            >
                                Confirmar
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
