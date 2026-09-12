<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { CONTATOS, EMPRESAS, LEADS } from '@/data/crm';

type Filtro = 'atuais' | 'atrasadas' | 'concluidas' | 'todas';
type Escopo = 'minhas' | 'time';

const USUARIO = 'Camila Moraes';
const filtro = ref<Filtro>('atuais');
const escopo = ref<Escopo>('minhas');
const feitas = reactive<Record<number, boolean>>({});

const TIPOS = [
    { label: 'Ligação', cor: '#3F5E8C' },
    { label: 'Reunião', cor: '#8C4A6B' },
    { label: 'E-mail', cor: 'var(--primary)' },
    { label: 'Tarefa', cor: '#5F6E52' },
];

const hoje = new Date(2026, 7, 17);

const todas = computed(() =>
    LEADS.map((l) => {
        const t = TIPOS[l.id % 4];
        const off = l.tarefaOff || 0;
        const prazo = new Date(
            hoje.getFullYear(),
            hoje.getMonth(),
            hoje.getDate() + off,
        );
        const dias = Math.round((prazo.getTime() - hoje.getTime()) / 86400000);
        const feita = !!feitas[l.id];
        const contato = CONTATOS.find((c) => c.id === l.contatoId);
        const emp = EMPRESAS.find((e) => e.id === l.empresaId);
        const atrasada = !feita && dias < 0;

        return {
            id: l.id,
            resp: l.resp,
            feita,
            atrasada,
            nome: l.tarefa,
            tipo: t.label,
            tipoCor: t.cor,
            negocio: l.assunto,
            cliente: emp?.nome ?? contato?.nome ?? l.nome,
            prazo:
                String(prazo.getDate()).padStart(2, '0') +
                '/' +
                String(prazo.getMonth() + 1).padStart(2, '0') +
                ' · ' +
                (l.tarefaHora || '09:00'),
            prazoRel: feita
                ? 'concluída'
                : dias < 0
                  ? `${Math.abs(dias)}d em atraso`
                  : dias === 0
                    ? 'hoje'
                    : `em ${dias}d`,
            prazoCor: feita
                ? 'var(--muted-foreground)'
                : dias < 0
                  ? '#9B3B2F'
                  : dias === 0
                    ? 'var(--primary)'
                    : '#3C4450',
            status: feita ? 'Concluída' : atrasada ? 'Atrasada' : 'Pendente',
            statusBg: feita ? '#E7F0EE' : atrasada ? 'color-mix(in srgb, var(--destructive) 18%, transparent)' : '#EEF1F6',
            statusCor: feita ? 'var(--accent)' : atrasada ? '#9B3B2F' : '#3F5E8C',
            marcaBg: feita ? 'var(--accent)' : '#FFFFFF',
            marcaBorda: feita ? 'var(--accent)' : '#D6D1C6',
            marca: feita ? '✓' : '',
            nomeDeco: feita ? 'line-through' : 'none',
            nomeCor: feita ? '#9AA2AE' : 'var(--foreground)',
            prazoSort: prazo.getTime(),
        };
    }),
);

const minhas = computed(() => todas.value.filter((a) => a.resp === USUARIO));

const lista = computed(() => {
    const doEscopo = todas.value.filter(
        (a) => escopo.value === 'time' || a.resp === USUARIO,
    );

    return doEscopo
        .filter((a) => {
            if (filtro.value === 'atuais') {
                return !a.feita;
            }
            if (filtro.value === 'atrasadas') {
                return a.atrasada;
            }
            if (filtro.value === 'concluidas') {
                return a.feita;
            }

            return true;
        })
        .sort((a, b) => a.prazoSort - b.prazoSort);
});

const kpis = computed(() => [
    {
        label: 'Pendentes',
        value: String(minhas.value.filter((a) => !a.feita).length),
        cor: 'var(--foreground)',
    },
    {
        label: 'Em atraso',
        value: String(minhas.value.filter((a) => a.atrasada).length),
        cor: '#9B3B2F',
    },
    {
        label: 'Para hoje',
        value: String(
            minhas.value.filter((a) => !a.feita && a.prazoRel === 'hoje')
                .length,
        ),
        cor: 'var(--primary)',
    },
    {
        label: 'Concluídas',
        value: String(minhas.value.filter((a) => a.feita).length),
        cor: 'var(--accent)',
    },
]);

const filtros: { k: Filtro; label: string }[] = [
    { k: 'atuais', label: 'Atuais' },
    { k: 'atrasadas', label: 'Atrasadas' },
    { k: 'concluidas', label: 'Concluídas' },
    { k: 'todas', label: 'Todas' },
];

function toggleFeita(id: number): void {
    feitas[id] = !feitas[id];
}
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-3.5">
        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="k in kpis"
                :key="k.label"
                class="flex flex-col gap-1.5 rounded-[10px] border border-border bg-card px-[17px] py-[15px]"
            >
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase"
                >
                    {{ k.label }}
                </span>
                <span
                    class="font-[family-name:var(--font-crm-display)] text-[28px] leading-none font-medium"
                    :style="{ color: k.cor }"
                >
                    {{ k.value }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-1.5">
                <button
                    v-for="f in filtros"
                    :key="f.k"
                    type="button"
                    class="cursor-pointer rounded-full border px-[13px] py-1.5 text-xs"
                    :class="
                        filtro === f.k
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-card text-muted-foreground'
                    "
                    @click="filtro = f.k"
                >
                    {{ f.label }}
                </button>
            </div>
            <div class="flex items-center gap-2.5">
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[11.5px] text-muted-foreground"
                >
                    {{ lista.length }}
                    {{ lista.length === 1 ? 'atividade' : 'atividades' }}
                </span>
                <div
                    class="flex gap-0.5 rounded-lg border border-border bg-card p-0.5"
                >
                    <button
                        type="button"
                        class="cursor-pointer rounded-md border-0 px-3 py-[5px] text-xs"
                        :class="
                            escopo === 'minhas'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-transparent text-muted-foreground'
                        "
                        @click="escopo = 'minhas'"
                    >
                        Minhas
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-md border-0 px-3 py-[5px] text-xs"
                        :class="
                            escopo === 'time'
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-transparent text-muted-foreground'
                        "
                        @click="escopo = 'time'"
                    >
                        Do time
                    </button>
                </div>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-[10px] border border-border bg-card"
        >
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-card">
                        <th class="w-[38px] py-[11px] pr-0 pl-4" />
                        <th
                            class="py-[11px] pr-4 pl-0 text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Atividade
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Prazo
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Negociação
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Cliente
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Responsável
                        </th>
                        <th
                            class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="a in lista"
                        :key="a.id"
                        class="hover:bg-card"
                    >
                        <td
                            class="border-t border-[#F2EFE8] py-3 pr-0 pl-4 align-top"
                        >
                            <button
                                type="button"
                                title="Concluir atividade"
                                class="grid h-[18px] w-[18px] cursor-pointer place-items-center rounded-[5px] border-[1.5px] text-[11px] leading-none text-primary-foreground"
                                :style="{
                                    borderColor: a.marcaBorda,
                                    background: a.marcaBg,
                                }"
                                @click="toggleFeita(a.id)"
                            >
                                {{ a.marca }}
                            </button>
                        </td>
                        <td class="border-t border-[#F2EFE8] py-3 pr-4 pl-0">
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-[13px]"
                                    :style="{
                                        color: a.nomeCor,
                                        textDecoration: a.nomeDeco,
                                    }"
                                >
                                    {{ a.nome }}
                                </span>
                                <span
                                    class="text-[10.5px]"
                                    :style="{ color: a.tipoCor }"
                                >
                                    {{ a.tipo }}
                                </span>
                            </div>
                        </td>
                        <td class="border-t border-[#F2EFE8] px-4 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="font-[family-name:var(--font-crm-mono)] text-xs text-muted-foreground"
                                >
                                    {{ a.prazo }}
                                </span>
                                <span
                                    class="text-[10.5px]"
                                    :style="{ color: a.prazoCor }"
                                >
                                    {{ a.prazoRel }}
                                </span>
                            </div>
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                        >
                            {{ a.negocio }}
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                        >
                            {{ a.cliente }}
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                        >
                            {{ a.resp }}
                        </td>
                        <td class="border-t border-[#F2EFE8] px-4 py-3 text-right">
                            <span
                                class="rounded-full px-[9px] py-[3px] text-[11px]"
                                :style="{
                                    background: a.statusBg,
                                    color: a.statusCor,
                                }"
                            >
                                {{ a.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div
                v-if="lista.length === 0"
                class="flex flex-col items-center gap-[7px] px-5 py-[46px]"
            >
                <span
                    class="font-[family-name:var(--font-crm-display)] text-[17px] text-muted-foreground"
                >
                    Nenhuma atividade neste filtro
                </span>
                <span class="text-xs text-muted-foreground">Tudo em dia por aqui.</span>
            </div>
        </div>
    </div>
</template>
