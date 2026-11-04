<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgendaTarefaController from '@/actions/App/Http/Controllers/AgendaTarefaController';
import { edit as editNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';
import { index as atividadesIndex } from '@/routes/atividades';

type Filtro = 'atuais' | 'atrasadas' | 'concluidas' | 'todas';
type Escopo = 'minhas' | 'equipe';

type Atividade = {
    id: number;
    negociacaoId: number;
    descricao: string;
    tipo: string;
    tipoLabel: string;
    status: string;
    statusLabel: string;
    aberta: boolean;
    data: string;
    hora: string | null;
    assunto: string;
    cliente: string | null;
    responsavelNome: string | null;
};

const props = defineProps<{
    escopo: Escopo;
    filtro: Filtro;
    hoje: string;
    podeVerEquipe: boolean;
    kpis: {
        pendentes: number;
        atrasadas: number;
        hoje: number;
        concluidas: number;
    };
    atividades: Atividade[];
    limite: number;
}>();

const CORES_TIPO: Record<string, string> = {
    follow_up: 'var(--primary)',
    ligacao: '#3F5E8C',
    reuniao: '#8C4A6B',
    tarefa: '#5F6E52',
};

function diasAte(iso: string): number {
    const [y, m, d] = iso.split('-').map(Number);
    const [hy, hm, hd] = props.hoje.split('-').map(Number);

    return Math.round(
        (Date.UTC(y, m - 1, d) - Date.UTC(hy, hm - 1, hd)) / 86400000,
    );
}

const lista = computed(() =>
    props.atividades.map((a) => {
        const dias = diasAte(a.data);
        const feita = a.status === 'concluida';
        const atrasada = a.aberta && dias < 0;
        const [, m, d] = a.data.split('-');

        return {
            ...a,
            feita,
            atrasada,
            tipoCor: CORES_TIPO[a.tipo] ?? 'var(--muted-foreground)',
            prazo: `${d}/${m} · ${a.hora ?? '—'}`,
            prazoRel: !a.aberta
                ? a.statusLabel.toLowerCase()
                : dias < 0
                  ? `${Math.abs(dias)}d em atraso`
                  : dias === 0
                    ? 'hoje'
                    : `em ${dias}d`,
            prazoCor: !a.aberta
                ? 'var(--muted-foreground)'
                : dias < 0
                  ? '#9B3B2F'
                  : dias === 0
                    ? 'var(--primary)'
                    : '#3C4450',
            statusTexto: atrasada ? 'Atrasada' : a.statusLabel,
            statusBg: feita
                ? '#E7F0EE'
                : atrasada
                  ? 'color-mix(in srgb, var(--destructive) 18%, transparent)'
                  : '#EEF1F6',
            statusCor: feita
                ? 'var(--accent)'
                : atrasada
                  ? '#9B3B2F'
                  : '#3F5E8C',
            marcaBg: feita ? 'var(--accent)' : '#FFFFFF',
            marcaBorda: feita ? 'var(--accent)' : '#D6D1C6',
            marca: feita ? '✓' : '',
            nomeDeco: feita ? 'line-through' : 'none',
            nomeCor: feita ? '#9AA2AE' : 'var(--foreground)',
        };
    }),
);

const kpis = computed(() => [
    {
        label: 'Pendentes',
        value: String(props.kpis.pendentes),
        cor: 'var(--foreground)',
    },
    {
        label: 'Em atraso',
        value: String(props.kpis.atrasadas),
        cor: '#9B3B2F',
    },
    {
        label: 'Para hoje',
        value: String(props.kpis.hoje),
        cor: 'var(--primary)',
    },
    {
        label: 'Concluídas',
        value: String(props.kpis.concluidas),
        cor: 'var(--accent)',
    },
]);

const filtros: { k: Filtro; label: string }[] = [
    { k: 'atuais', label: 'Atuais' },
    { k: 'atrasadas', label: 'Atrasadas' },
    { k: 'concluidas', label: 'Concluídas' },
    { k: 'todas', label: 'Todas' },
];

function navegar(filtro: Filtro, escopo: Escopo): void {
    router.get(
        atividadesIndex.url(),
        {
            ...(filtro !== 'atuais' ? { filtro } : {}),
            escopo,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function concluir(a: Atividade): void {
    if (!a.aberta) {
        return;
    }

    router.patch(
        AgendaTarefaController.concluir.url({ tarefa: a.id }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-3.5">
        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="k in kpis"
                :key="k.label"
                class="border-border bg-card flex flex-col gap-1.5 rounded-[10px] border px-[17px] py-[15px]"
            >
                <span
                    class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-[0.12em]"
                >
                    {{ k.label }}
                </span>
                <span
                    class="font-[family-name:var(--font-crm-display)] text-[28px] font-medium leading-none"
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
                    @click="navegar(f.k, escopo)"
                >
                    {{ f.label }}
                </button>
            </div>
            <div class="flex items-center gap-2.5">
                <span
                    class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[11.5px]"
                >
                    {{ lista.length }}
                    {{ lista.length === 1 ? 'atividade' : 'atividades' }}
                </span>
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
                        @click="navegar(filtro, 'minhas')"
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
                        @click="navegar(filtro, 'equipe')"
                    >
                        Equipe
                    </button>
                </div>
            </div>
        </div>

        <p
            v-if="atividades.length >= limite"
            class="text-muted-foreground m-0 text-[12px]"
        >
            Mostrando as primeiras {{ limite }} atividades deste filtro.
        </p>

        <div
            class="border-border bg-card overflow-x-auto rounded-[10px] border"
        >
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-card">
                        <th class="w-[38px] py-[11px] pl-4 pr-0" />
                        <th
                            class="text-muted-foreground py-[11px] pl-0 pr-4 text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                        >
                            Atividade
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                        >
                            Prazo
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                        >
                            Negociação
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                        >
                            Cliente
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                        >
                            Responsável
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                        >
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in lista" :key="a.id" class="hover:bg-card">
                        <td
                            class="border-t border-[#F2EFE8] py-3 pl-4 pr-0 align-top"
                        >
                            <button
                                type="button"
                                title="Concluir atividade"
                                :disabled="!a.aberta"
                                class="text-primary-foreground grid h-[18px] w-[18px] cursor-pointer place-items-center rounded-[5px] border-[1.5px] text-[11px] leading-none"
                                :style="{
                                    borderColor: a.marcaBorda,
                                    background: a.marcaBg,
                                }"
                                @click="concluir(a)"
                            >
                                {{ a.marca }}
                            </button>
                        </td>
                        <td class="border-t border-[#F2EFE8] py-3 pl-0 pr-4">
                            <div class="flex flex-col gap-0.5">
                                <Link
                                    :href="editNegociacao.url(a.negociacaoId)"
                                    class="text-[13px] no-underline hover:underline"
                                    :style="{
                                        color: a.nomeCor,
                                        textDecoration: a.nomeDeco,
                                    }"
                                >
                                    {{ a.descricao }}
                                </Link>
                                <span
                                    class="text-[10.5px]"
                                    :style="{ color: a.tipoCor }"
                                >
                                    {{ a.tipoLabel }}
                                </span>
                            </div>
                        </td>
                        <td class="border-t border-[#F2EFE8] px-4 py-3">
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-xs"
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
                            class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                        >
                            {{ a.assunto }}
                        </td>
                        <td
                            class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                        >
                            {{ a.cliente }}
                        </td>
                        <td
                            class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                        >
                            {{ a.responsavelNome ?? '—' }}
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-right"
                        >
                            <span
                                class="rounded-full px-[9px] py-[3px] text-[11px]"
                                :style="{
                                    background: a.statusBg,
                                    color: a.statusCor,
                                }"
                            >
                                {{ a.statusTexto }}
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
                    class="text-muted-foreground font-[family-name:var(--font-crm-display)] text-[17px]"
                >
                    Nenhuma atividade neste filtro
                </span>
                <span class="text-muted-foreground text-xs"
                    >Tudo em dia por aqui.</span
                >
            </div>
        </div>
    </div>
</template>
