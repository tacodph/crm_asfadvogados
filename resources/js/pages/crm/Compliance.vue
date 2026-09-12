<script setup lang="ts">
const lgpd = [
    {
        titular: 'Aline Cordeiro',
        tipo: 'Revogação de consentimento',
        data: '10/08',
        prazo: 'resolver em 3 dias',
        bg: 'color-mix(in srgb, var(--primary) 12%, transparent)',
        cor: 'var(--primary)',
    },
    {
        titular: 'Marcos V. Leal',
        tipo: 'Acesso aos dados',
        data: '08/08',
        prazo: 'resolver em 7 dias',
        bg: '#F1F6F4',
        cor: 'var(--accent)',
    },
    {
        titular: 'Contato anônimo #4471',
        tipo: 'Exclusão de lead perdido',
        data: '02/08',
        prazo: 'vencido há 1 dia',
        bg: 'color-mix(in srgb, var(--destructive) 18%, transparent)',
        cor: '#9B3B2F',
    },
];

const rbac = [
    { perfil: 'Sócio / gestor', c1: 'E', c2: 'E', c3: 'E', c4: 'L' },
    { perfil: 'Advogado responsável', c1: 'E', c2: 'E', c3: 'L', c4: '—' },
    { perfil: 'Consultor comercial', c1: 'E', c2: '—', c3: '—', c4: '—' },
    { perfil: 'Encarregado (DPO)', c1: 'L', c2: '—', c3: '—', c4: 'E' },
].map((r) => ({
    ...r,
    c1cor: r.c1 === '—' ? '#B8B1A3' : 'var(--foreground)',
    c2cor: r.c2 === '—' ? '#B8B1A3' : 'var(--foreground)',
    c3cor: r.c3 === '—' ? '#B8B1A3' : 'var(--foreground)',
    c4cor: r.c4 === '—' ? '#B8B1A3' : 'var(--foreground)',
}));

const auditoria = [
    {
        quando: '12/08 14:02',
        quem: 'Camila Moraes',
        oque: 'Avançou “Construtora Piedade” de Diagnóstico → Apresentação da solução',
    },
    {
        quando: '12/08 11:47',
        quem: 'Sistema',
        oque: 'Automação #4 criou tarefa de follow-up (sem resposta há 3 dias) para Letícia Bonfim',
    },
    {
        quando: '12/08 10:19',
        quem: 'Diego Alencar',
        oque: 'Tentativa de acesso ao conteúdo do caso de “Rodrigo Tavares” — negado por perfil',
    },
    {
        quando: '11/08 17:33',
        quem: 'Letícia Bonfim',
        oque: 'Gerou versão v2 da proposta PR-2026-118 (alteração de parcelamento)',
    },
    {
        quando: '11/08 09:05',
        quem: 'Encarregado (DPO)',
        oque: 'Exportou dados do titular “Marcos V. Leal” em resposta a pedido de acesso',
    },
];
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-4">
        <div class="grid gap-3.5 lg:grid-cols-2">
            <div
                class="rounded-[10px] border border-border bg-card px-5 pt-[18px] pb-5"
            >
                <h2
                    class="mb-3.5 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Solicitações de titulares (LGPD)
                </h2>
                <div class="flex flex-col gap-[11px]">
                    <div
                        v-for="s in lgpd"
                        :key="s.titular"
                        class="flex items-center justify-between gap-3 border-b border-[#F2EFE8] pb-2.5"
                    >
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[12.5px] text-foreground">{{
                                s.titular
                            }}</span>
                            <span class="text-[11px] text-muted-foreground">
                                {{ s.tipo }} · aberto em {{ s.data }}
                            </span>
                        </div>
                        <span
                            class="rounded-full px-[9px] py-[3px] text-[11px]"
                            :style="{ background: s.bg, color: s.cor }"
                        >
                            {{ s.prazo }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                class="rounded-[10px] border border-border bg-card px-5 pt-[18px] pb-5"
            >
                <h2
                    class="mb-3.5 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Matriz de acesso (RBAC)
                </h2>
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th
                                class="pb-2 text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Perfil
                            </th>
                            <th
                                class="pb-2 text-center font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Comercial
                            </th>
                            <th
                                class="pb-2 text-center font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Caso
                            </th>
                            <th
                                class="pb-2 text-center font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Financeiro
                            </th>
                            <th
                                class="pb-2 text-center font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Auditoria
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in rbac" :key="p.perfil">
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-[12.5px]"
                            >
                                {{ p.perfil }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-center font-[family-name:var(--font-crm-mono)] text-xs"
                                :style="{ color: p.c1cor }"
                            >
                                {{ p.c1 }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-center font-[family-name:var(--font-crm-mono)] text-xs"
                                :style="{ color: p.c2cor }"
                            >
                                {{ p.c2 }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-center font-[family-name:var(--font-crm-mono)] text-xs"
                                :style="{ color: p.c3cor }"
                            >
                                {{ p.c3 }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-center font-[family-name:var(--font-crm-mono)] text-xs"
                                :style="{ color: p.c4cor }"
                            >
                                {{ p.c4 }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-[13px] mb-0 text-[11.5px] leading-normal text-muted-foreground">
                    L = leitura · E = edição · — = sem acesso. O conteúdo jurídico
                    do caso vive fora do CRM comercial; o vínculo é por
                    referência, não por cópia.
                </p>
            </div>
        </div>

        <div
            class="rounded-[10px] border border-border bg-card px-5 pt-[18px] pb-5"
        >
            <h2
                class="mb-3.5 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
            >
                Trilha de auditoria
            </h2>
            <div class="flex flex-col">
                <div
                    v-for="a in auditoria"
                    :key="a.quando + a.oque"
                    class="grid items-baseline gap-3.5 border-t border-[#F2EFE8] py-2.5 md:grid-cols-[148px_132px_1fr]"
                >
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[11.5px] text-muted-foreground"
                    >
                        {{ a.quando }}
                    </span>
                    <span class="text-[12.5px] text-muted-foreground">{{
                        a.quem
                    }}</span>
                    <span class="text-[12.5px] text-foreground">{{
                        a.oque
                    }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
