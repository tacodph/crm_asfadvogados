<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/routes/propostas';

type Kpi = { label: string; value: string };

type PropostaLinha = {
    id: number;
    codigo: string;
    cliente: string;
    versao: string;
    status: string;
    statusBg: string;
    statusCor: string;
    prazo: string;
    prazoCor: string;
    valor: string;
};

defineProps<{
    kpis: Kpi[];
    propostas: PropostaLinha[];
}>();
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-3.5">
        <div class="grid gap-3.5 sm:grid-cols-3">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="flex flex-col gap-1.5 rounded-[10px] border border-border bg-card px-[17px] py-[15px]"
            >
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase"
                >
                    {{ kpi.label }}
                </span>
                <span
                    class="font-[family-name:var(--font-crm-display)] text-[26px] leading-none font-medium"
                >
                    {{ kpi.value }}
                </span>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-[10px] border border-border bg-card"
        >
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-card">
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Proposta
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Cliente
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Versão
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Status
                        </th>
                        <th
                            class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Validade
                        </th>
                        <th
                            class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Honorários
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in propostas"
                        :key="p.id"
                        class="hover:bg-card"
                    >
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 font-[family-name:var(--font-crm-mono)] text-[12px]"
                        >
                            <Link
                                :href="show(p.id)"
                                class="text-primary underline-offset-2 hover:underline"
                            >
                                {{ p.codigo }}
                            </Link>
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[13px]"
                        >
                            {{ p.cliente }}
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                        >
                            {{ p.versao }}
                        </td>
                        <td class="border-t border-[#F2EFE8] px-4 py-3">
                            <span
                                class="rounded-full px-[9px] py-[3px] text-[11px]"
                                :style="{
                                    background: p.statusBg,
                                    color: p.statusCor,
                                }"
                            >
                                {{ p.status }}
                            </span>
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                            :style="{ color: p.prazoCor }"
                        >
                            {{ p.prazo }}
                        </td>
                        <td
                            class="border-t border-[#F2EFE8] px-4 py-3 text-right font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                        >
                            {{ p.valor }}
                        </td>
                    </tr>
                    <tr v-if="propostas.length === 0">
                        <td
                            colspan="6"
                            class="border-t border-[#F2EFE8] px-4 py-8 text-center text-[13px] text-muted-foreground"
                        >
                            Nenhuma proposta cadastrada.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="flex items-start gap-3.5 rounded-[10px] border border-primary/30 bg-card px-[18px] py-[15px]"
        >
            <span
                class="pt-0.5 font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-primary uppercase"
            >
                Regra
            </span>
            <p class="m-0 max-w-[780px] text-[12.5px] leading-relaxed text-muted-foreground">
                Toda minuta é gerada a partir de modelo aprovado pelo comitê, com
                honorários dentro da tabela mínima da OAB/seccional. Versões são
                imutáveis: uma alteração cria a v+1 e registra autor, data e
                diff na trilha de auditoria.
            </p>
        </div>
    </div>
</template>
