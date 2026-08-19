<script setup lang="ts">
import { inject, ref, type Ref } from 'vue';

const periodos = ['7 dias', '30 dias', 'trimestre'];
const periodo = inject<Ref<string>>('crmPeriodo', ref('30 dias'));

const kpis = [
    {
        label: 'Leads no período',
        value: '142',
        delta: '+18% vs. período anterior',
        deltaColor: '#14574F',
    },
    {
        label: 'Taxa de conversão',
        value: '31%',
        delta: '+4 p.p. — melhor no B2B',
        deltaColor: '#14574F',
    },
    {
        label: 'Ciclo médio',
        value: '23d',
        delta: '−5d após automação de follow-up',
        deltaColor: '#14574F',
    },
    {
        label: 'Honorários contratados',
        value: 'R$ 612k',
        delta: '9 contratos assinados',
        deltaColor: '#77808E',
    },
];

const conversao = [
    {
        etapa: 'Prospecção / Inbound',
        pct: 100,
        cor: '#3C4450',
        info: '100% · 2 abertos',
    },
    { etapa: 'Diagnóstico', pct: 80, cor: '#4F5A69', info: '80% · 2 abertos' },
    {
        etapa: 'Apresentação da solução',
        pct: 60,
        cor: '#6B6247',
        info: '60% · 1 aberto',
    },
    { etapa: 'Negociação', pct: 39, cor: '#8C6F3F', info: '39% · 1 aberto' },
    { etapa: 'Fechamento', pct: 19, cor: '#14574F', info: '19% · 1 aberto' },
];

const canais = [
    { nome: 'WhatsApp', qtd: 58, pct: 100, cor: '#14574F' },
    { nome: 'Indicação', qtd: 34, pct: 59, cor: '#3F5E8C' },
    { nome: 'Site (formulário)', qtd: 27, pct: 47, cor: '#8C6F3F' },
    { nome: 'Instagram', qtd: 15, pct: 26, cor: '#8C4A6B' },
    { nome: 'Google Maps', qtd: 8, pct: 14, cor: '#7A6E3F' },
];

const perdas = [
    { motivo: 'Honorários acima do orçamento', pct: 31 },
    { motivo: 'Sem resposta após 3 tentativas', pct: 24 },
    { motivo: 'Optou por outro escritório', pct: 19 },
    { motivo: 'Fora da área de atuação', pct: 14 },
    { motivo: 'Resolveu internamente', pct: 12 },
];

const equipe = [
    {
        nome: 'Camila Moraes',
        leads: 38,
        fechados: 14,
        conv: '37%',
        sla: '1h12',
        slaCor: '#14574F',
    },
    {
        nome: 'Rafael Prado',
        leads: 31,
        fechados: 9,
        conv: '29%',
        sla: '3h40',
        slaCor: '#14574F',
    },
    {
        nome: 'Letícia Bonfim',
        leads: 44,
        fechados: 16,
        conv: '36%',
        sla: '0h48',
        slaCor: '#14574F',
    },
    {
        nome: 'Diego Alencar',
        leads: 29,
        fechados: 6,
        conv: '21%',
        sla: '9h05',
        slaCor: '#9B3B2F',
    },
];
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-[18px]">
        <div class="flex gap-2">
            <button
                v-for="item in periodos"
                :key="item"
                type="button"
                class="cursor-pointer rounded-full border px-3 py-1.5 text-xs"
                :class="
                    periodo === item
                        ? 'border-[#171B21] bg-[#171B21] text-white'
                        : 'border-[#E3DFD6] bg-white text-[#3C4450]'
                "
                @click="periodo = item"
            >
                {{ item }}
            </button>
        </div>

        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="flex flex-col gap-[7px] rounded-[10px] border border-[#E3DFD6] bg-white px-[17px] py-4"
            >
                <div
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-[#77808E] uppercase"
                >
                    {{ kpi.label }}
                </div>
                <div
                    class="font-[family-name:var(--font-crm-display)] text-[32px] leading-none font-medium tracking-[-0.02em]"
                >
                    {{ kpi.value }}
                </div>
                <div class="text-[11.5px]" :style="{ color: kpi.deltaColor }">
                    {{ kpi.delta }}
                </div>
            </div>
        </div>

        <div class="grid gap-3.5 lg:grid-cols-[1.35fr_1fr]">
            <div
                class="rounded-[10px] border border-[#E3DFD6] bg-white px-5 pt-[18px] pb-5"
            >
                <div class="mb-4 flex items-baseline justify-between">
                    <h2
                        class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                    >
                        Conversão por etapa
                    </h2>
                    <span class="text-[11.5px] text-[#77808E]"
                        >Funil B2B consultivo</span
                    >
                </div>
                <div class="flex flex-col gap-[13px]">
                    <div
                        v-for="item in conversao"
                        :key="item.etapa"
                        class="flex flex-col gap-[5px]"
                    >
                        <div class="flex justify-between text-[12.5px]">
                            <span class="text-[#3C4450]">{{ item.etapa }}</span>
                            <span
                                class="font-[family-name:var(--font-crm-mono)] text-[#77808E]"
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
                class="rounded-[10px] border border-[#E3DFD6] bg-white px-5 pt-[18px] pb-5"
            >
                <h2
                    class="mt-0 mb-4 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Origem dos leads
                </h2>
                <div class="flex flex-col gap-3">
                    <div
                        v-for="canal in canais"
                        :key="canal.nome"
                        class="flex items-center gap-2.5"
                    >
                        <span
                            class="h-2 w-2 rounded-sm"
                            :style="{ background: canal.cor }"
                        />
                        <span class="w-[108px] text-[12.5px] text-[#3C4450]">
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
                            class="w-[30px] text-right font-[family-name:var(--font-crm-mono)] text-[11.5px] text-[#77808E]"
                        >
                            {{ canal.qtd }}
                        </span>
                    </div>
                </div>
                <p
                    class="mt-4 mb-0 border-t border-[#EEEBE4] pt-3 text-[11.5px] leading-normal text-[#77808E]"
                >
                    Todos os canais são de
                    <strong class="font-medium text-[#3C4450]"
                        >entrada passiva</strong
                    >. O CRM não permite importar listas frias — exigência do
                    Provimento 205/2021.
                </p>
            </div>
        </div>

        <div class="grid gap-3.5 lg:grid-cols-[1fr_1.35fr]">
            <div
                class="rounded-[10px] border border-[#E3DFD6] bg-white px-5 pt-[18px] pb-5"
            >
                <h2
                    class="mt-0 mb-3.5 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Motivos de perda
                </h2>
                <div class="flex flex-col gap-2.5">
                    <div
                        v-for="perda in perdas"
                        :key="perda.motivo"
                        class="flex items-center justify-between gap-2.5 border-b border-[#F2EFE8] pb-[9px]"
                    >
                        <span class="text-[12.5px] text-[#3C4450]">
                            {{ perda.motivo }}
                        </span>
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-xs text-[#9B3B2F]"
                        >
                            {{ perda.pct }}%
                        </span>
                    </div>
                </div>
            </div>

            <div
                class="rounded-[10px] border border-[#E3DFD6] bg-white px-5 pt-[18px] pb-5"
            >
                <h2
                    class="mt-0 mb-3.5 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Produtividade por colaborador
                </h2>
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th
                                class="pb-[9px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Colaborador
                            </th>
                            <th
                                class="pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Leads
                            </th>
                            <th
                                class="pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Fechados
                            </th>
                            <th
                                class="pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Conversão
                            </th>
                            <th
                                class="pb-[9px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                SLA 1º contato
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pessoa in equipe" :key="pessoa.nome">
                            <td
                                class="border-t border-[#F2EFE8] py-[9px] text-[12.5px] text-[#171B21]"
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
    </div>
</template>
