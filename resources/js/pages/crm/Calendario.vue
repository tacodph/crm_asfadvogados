<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import { edit as editNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';

type TipoEvento = 'tarefa' | 'previsao';

type StatusVisual =
    | 'tarefa'
    | 'tarefa_hoje'
    | 'tarefa_atrasada'
    | 'previsao';

type CalendarioEvento = {
    id: string;
    tipo: TipoEvento;
    data: string;
    hora: string;
    titulo: string;
    lead: string;
    contatoNome: string;
    etapaNome: string;
    responsavelNome: string;
    funilId: number;
    funilNome: string;
    negociacaoId: number;
};

type FunilOpcao = {
    id: number;
    slug: string;
    nome: string;
};

const props = defineProps<{
    hoje: string;
    funis: FunilOpcao[];
    eventos: CalendarioEvento[];
}>();

type FiltroCal = 'todos' | TipoEvento;

/**
 * Tailwick FullCalendar pastels:
 * custom/blue, green, yellow, sky, purple.
 */
const estilos = {
    tarefa: {
        chip: 'bg-sky-100 text-sky-600',
        card: 'bg-sky-100 text-sky-700 border-sky-200',
        badge: 'bg-sky-500 text-white',
        rotulo: 'Tarefa',
    },
    tarefa_hoje: {
        chip: 'bg-green-100 text-green-600',
        card: 'bg-green-100 text-green-700 border-green-200',
        badge: 'bg-green-500 text-white',
        rotulo: 'Tarefa · hoje',
    },
    tarefa_atrasada: {
        chip: 'bg-purple-100 text-purple-600',
        card: 'bg-purple-100 text-purple-700 border-purple-200',
        badge: 'bg-purple-500 text-white',
        rotulo: 'Tarefa · atrasada',
    },
    previsao: {
        chip: 'bg-yellow-100 text-yellow-600',
        card: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        badge: 'bg-yellow-500 text-white',
        rotulo: 'Previsão de fechamento',
    },
} as const;

const filtro = ref<FiltroCal>('todos');
const funilFiltro = ref<number | 'todos'>('todos');
const hojeDate = parseIsoDate(props.hoje);
const cursor = ref(
    new Date(hojeDate.getFullYear(), hojeDate.getMonth(), 1),
);
const selecionado = ref(new Date(hojeDate));

function statusVisual(tipo: TipoEvento, data: Date): StatusVisual {
    if (tipo === 'previsao') {
        return 'previsao';
    }

    if (sameDay(data, hojeDate)) {
        return 'tarefa_hoje';
    }

    if (data.getTime() < hojeDate.getTime()) {
        return 'tarefa_atrasada';
    }

    return 'tarefa';
}

const eventos = computed(() =>
    props.eventos.map((e) => {
        const data = parseIsoDate(e.data);
        const status = statusVisual(e.tipo, data);

        return {
            ...e,
            data,
            status,
            ...estilos[status],
        };
    }),
);

const mesLabel = computed(() =>
    cursor.value.toLocaleDateString('pt-BR', {
        month: 'long',
        year: 'numeric',
    }),
);

const semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

function parseIsoDate(iso: string): Date {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y, m - 1, d);
}

function sameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function passaFiltros(e: (typeof eventos.value)[number]): boolean {
    const tipoOk = filtro.value === 'todos' || e.tipo === filtro.value;
    const funilOk =
        funilFiltro.value === 'todos' || e.funilId === funilFiltro.value;

    return tipoOk && funilOk;
}

function rotuloHora(hora: string): string {
    if (!hora || hora === '—') {
        return '';
    }

    return `${hora} `;
}

const dias = computed(() => {
    const year = cursor.value.getFullYear();
    const month = cursor.value.getMonth();
    const first = new Date(year, month, 1);
    const startPad = first.getDay();
    const cells: {
        date: Date;
        numero: number;
        fora: boolean;
        hoje: boolean;
        sel: boolean;
        eventos: typeof eventos.value;
        temExtra: boolean;
        extra: string;
    }[] = [];

    for (let i = 0; i < 42; i++) {
        const dayNum = i - startPad + 1;
        const date = new Date(year, month, dayNum);
        const fora = date.getMonth() !== month;
        const dayEvents = eventos.value
            .filter((e) => sameDay(e.data, date))
            .filter(passaFiltros);
        const shown = dayEvents.slice(0, 3);

        cells.push({
            date,
            numero: date.getDate(),
            fora,
            hoje: sameDay(date, hojeDate),
            sel: sameDay(date, selecionado.value),
            eventos: shown,
            temExtra: dayEvents.length > 3,
            extra: `+${dayEvents.length - 3}`,
        });
    }

    return cells;
});

const listaSel = computed(() =>
    eventos.value
        .filter((e) => sameDay(e.data, selecionado.value))
        .filter(passaFiltros),
);

const diaSelecionado = computed(() =>
    selecionado.value.toLocaleDateString('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

const proximos = computed(() =>
    [...eventos.value]
        .filter((e) => e.data >= hojeDate)
        .filter(passaFiltros)
        .sort((a, b) => a.data.getTime() - b.data.getTime())
        .slice(0, 5)
        .map((e) => ({
            ...e,
            quando: `${String(e.data.getDate()).padStart(2, '0')}/${String(e.data.getMonth() + 1).padStart(2, '0')}`,
        })),
);

const filtros: { k: FiltroCal; label: string }[] = [
    { k: 'todos', label: 'todos' },
    { k: 'tarefa', label: 'tarefas' },
    { k: 'previsao', label: 'previsões' },
];

const legenda = [
    { key: 'tarefa', label: 'Tarefa futura', chip: estilos.tarefa.chip },
    { key: 'tarefa_hoje', label: 'Tarefa hoje', chip: estilos.tarefa_hoje.chip },
    {
        key: 'tarefa_atrasada',
        label: 'Tarefa atrasada',
        chip: estilos.tarefa_atrasada.chip,
    },
    { key: 'previsao', label: 'Previsão', chip: estilos.previsao.chip },
] as const;

function mesAnterior(): void {
    cursor.value = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth() - 1,
        1,
    );
}

function mesProximo(): void {
    cursor.value = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth() + 1,
        1,
    );
}

function voltarHoje(): void {
    cursor.value = new Date(hojeDate.getFullYear(), hojeDate.getMonth(), 1);
    selecionado.value = new Date(hojeDate);
}
</script>

<template>
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
        <!-- Calendar card — Tailwick apps-calendar layout -->
        <div class="xl:col-span-9">
            <div
                class="lex-card overflow-hidden rounded-md border border-slate-200 bg-white"
            >
                <div class="p-5">
                    <!-- Toolbar -->
                    <div
                        class="mb-5 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex size-9 cursor-pointer items-center justify-center rounded-md bg-custom-500 text-white transition-colors hover:bg-custom-600"
                                aria-label="Mês anterior"
                                @click="mesAnterior"
                            >
                                <ChevronLeft class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex size-9 cursor-pointer items-center justify-center rounded-md bg-custom-500 text-white transition-colors hover:bg-custom-600"
                                aria-label="Próximo mês"
                                @click="mesProximo"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-md bg-custom-500 px-3.5 py-2 text-[13px] font-medium text-white transition-colors hover:bg-custom-600"
                                @click="voltarHoje"
                            >
                                hoje
                            </button>
                        </div>

                        <h2
                            class="m-0 text-center text-xl font-semibold capitalize tracking-tight text-slate-800"
                        >
                            {{ mesLabel }}
                        </h2>

                        <div
                            class="inline-flex overflow-hidden rounded-md border border-slate-200"
                        >
                            <button
                                v-for="f in filtros"
                                :key="f.k"
                                type="button"
                                class="cursor-pointer border-r border-slate-200 px-3.5 py-2 text-[13px] font-medium capitalize last:border-r-0"
                                :class="
                                    filtro === f.k
                                        ? 'bg-custom-500 text-white'
                                        : 'bg-white text-slate-600 hover:bg-slate-50'
                                "
                                @click="filtro = f.k"
                            >
                                {{ f.label }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="funis.length > 0"
                        class="mb-4 flex flex-wrap gap-1.5"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-md border px-2.5 py-1 text-[11px] font-medium"
                            :class="
                                funilFiltro === 'todos'
                                    ? 'border-custom-500 bg-custom-100 text-custom-600'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-custom-300'
                            "
                            @click="funilFiltro = 'todos'"
                        >
                            Todos os funis
                        </button>
                        <button
                            v-for="f in funis"
                            :key="f.id"
                            type="button"
                            class="cursor-pointer rounded-md border px-2.5 py-1 text-[11px] font-medium"
                            :class="
                                funilFiltro === f.id
                                    ? 'border-custom-500 bg-custom-100 text-custom-600'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-custom-300'
                            "
                            @click="funilFiltro = f.id"
                        >
                            {{ f.nome }}
                        </button>
                    </div>

                    <!-- Grid -->
                    <div class="overflow-hidden rounded-md border border-slate-200">
                        <div
                            class="grid grid-cols-7 border-b border-slate-200 bg-white"
                        >
                            <span
                                v-for="d in semana"
                                :key="d"
                                class="px-2 py-3 text-center text-[12px] font-semibold text-slate-700"
                            >
                                {{ d }}
                            </span>
                        </div>
                        <div class="grid grid-cols-7 bg-white">
                            <button
                                v-for="(d, i) in dias"
                                :key="i"
                                type="button"
                                class="flex min-h-[6.25rem] cursor-pointer flex-col gap-1 border-r border-b border-slate-100 px-1.5 pt-1.5 pb-2 text-left transition-colors hover:bg-slate-50/80"
                                :class="[
                                    d.sel
                                        ? 'bg-[#f8f1e7]'
                                        : d.fora
                                          ? 'bg-slate-50/40'
                                          : 'bg-white',
                                    i % 7 === 6 ? 'border-r-0' : '',
                                ]"
                                @click="selecionado = d.date"
                            >
                                <span
                                    class="ml-auto inline-flex size-6 items-center justify-center rounded-full text-[12px] font-medium"
                                    :class="[
                                        d.fora
                                            ? 'text-slate-300'
                                            : 'text-slate-600',
                                        d.hoje
                                            ? 'bg-custom-500 text-white'
                                            : '',
                                    ]"
                                >
                                    {{ d.numero }}
                                </span>
                                <span
                                    class="flex w-full min-w-0 flex-col gap-0.5"
                                >
                                    <span
                                        v-for="e in d.eventos"
                                        :key="e.id"
                                        class="block rounded-md px-1.5 py-1 text-[11px] leading-tight font-medium"
                                        :class="e.chip"
                                    >
                                        <span class="block truncate">
                                            {{ rotuloHora(e.hora)
                                            }}{{ e.titulo }}
                                        </span>
                                        <span
                                            class="mt-0.5 block truncate text-[10px] font-normal opacity-80"
                                        >
                                            {{ e.contatoNome }} ·
                                            {{ e.etapaNome }}
                                        </span>
                                        <span
                                            class="block truncate text-[10px] font-normal opacity-70"
                                        >
                                            {{ e.responsavelNome }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="d.temExtra"
                                        class="pl-0.5 text-[10px] text-slate-400"
                                    >
                                        {{ d.extra }}
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="flex flex-col gap-5 xl:col-span-3">
            <!-- Selected day — keep card, more color by status -->
            <div
                class="lex-card rounded-md border border-slate-200 bg-white"
            >
                <div class="flex flex-col gap-4 p-5">
                    <div>
                        <h3
                            class="m-0 text-[15px] font-semibold capitalize text-slate-800"
                        >
                            {{ diaSelecionado }}
                        </h3>
                        <p class="mt-1 mb-0 text-[12px] text-slate-500">
                            Eventos do dia selecionado
                        </p>
                    </div>

                    <p
                        v-if="listaSel.length === 0"
                        class="m-0 text-[13px] text-slate-500"
                    >
                        Nenhuma marcação neste dia.
                    </p>

                    <div class="flex flex-col gap-3">
                        <Link
                            v-for="e in listaSel"
                            :key="e.id"
                            :href="editNegociacao.url(e.negociacaoId)"
                            class="block rounded-md border px-3 py-2.5 no-underline transition-all hover:opacity-90"
                            :class="e.card"
                        >
                            <div
                                class="mb-1.5 flex items-center justify-between gap-2"
                            >
                                <span
                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                    :class="e.badge"
                                >
                                    {{ e.rotulo }}
                                </span>
                                <span class="text-[11px] font-medium opacity-80">
                                    {{ e.hora }}
                                </span>
                            </div>
                            <p class="m-0 text-[13px] leading-snug font-semibold">
                                {{ e.titulo }}
                            </p>
                            <p class="mt-1 mb-0 text-[11.5px] opacity-80">
                                {{ e.contatoNome }}
                            </p>
                            <p class="mt-0.5 mb-0 text-[11px] opacity-70">
                                {{ e.etapaNome }}
                                <span v-if="e.funilNome">
                                    · {{ e.funilNome }}
                                </span>
                            </p>
                            <p class="mt-0.5 mb-0 text-[11px] font-medium opacity-80">
                                Resp.: {{ e.responsavelNome }}
                            </p>
                            <p
                                v-if="e.lead !== e.contatoNome"
                                class="mt-0.5 mb-0 text-[11px] opacity-70"
                            >
                                {{ e.lead }}
                            </p>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Legend like Tailwick Draggable Events -->
            <div
                class="lex-card rounded-md border border-slate-200 bg-white"
            >
                <div class="p-5">
                    <h3 class="mb-4 text-[15px] font-semibold text-slate-800">
                        Status
                    </h3>
                    <div class="flex flex-col gap-3">
                        <span
                            v-for="item in legenda"
                            :key="item.key"
                            class="block rounded-md px-3 py-1.5 text-[13px] font-medium"
                            :class="item.chip"
                        >
                            {{ item.label }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                class="lex-card rounded-md border border-slate-200 bg-white"
            >
                <div class="flex flex-col gap-3 p-5">
                    <h3 class="m-0 text-[15px] font-semibold text-slate-800">
                        Próximos compromissos
                    </h3>
                    <p
                        v-if="proximos.length === 0"
                        class="m-0 text-[13px] text-slate-500"
                    >
                        Nenhum compromisso futuro.
                    </p>
                    <Link
                        v-for="e in proximos"
                        :key="e.id"
                        :href="editNegociacao.url(e.negociacaoId)"
                        class="flex items-start gap-2.5 rounded-md border px-2.5 py-2 no-underline transition-opacity hover:opacity-90"
                        :class="e.card"
                    >
                        <span class="shrink-0 text-[11px] font-semibold">
                            {{ e.quando }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-[12.5px] font-medium">
                                {{ e.titulo }}
                            </span>
                            <span class="block truncate text-[11px] opacity-75">
                                {{ e.contatoNome }} · {{ e.etapaNome }}
                            </span>
                            <span class="block truncate text-[11px] opacity-70">
                                {{ e.responsavelNome }}
                            </span>
                        </span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
