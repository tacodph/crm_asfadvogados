<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    create as createEmpresa,
    edit as editEmpresa,
} from '@/actions/App/Http/Controllers/EmpresaController';
import { create as createNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmContactDrawer from '@/components/crm/CrmContactDrawer.vue';
import CrmEmpresaDrawer from '@/components/crm/CrmEmpresaDrawer.vue';
import { index as negociacoesIndex } from '@/routes/negociacoes';
import type { EmpresaLinha } from '@/types/crm';

const { empresas } = defineProps<{
    empresas: EmpresaLinha[];
}>();

const empresaId = ref<number | null>(null);
const contatoId = ref<number | null>(null);

const empresaAberta = computed(
    () => empresas.find((empresa) => empresa.id === empresaId.value) ?? null,
);

const contatoAberto = computed(() => {
    if (contatoId.value === null) {
        return null;
    }

    for (const empresa of empresas) {
        const contato = empresa.drawer.contatos.find(
            (item) => item.id === contatoId.value,
        );

        if (contato) {
            return contato;
        }
    }

    return null;
});

const abrirEmpresa = (id: number): void => {
    empresaId.value = id;
    contatoId.value = null;
};

const abrirContato = (id: number): void => {
    contatoId.value = id;
    empresaId.value = null;
};

const fecharPainel = (): void => {
    empresaId.value = null;
    contatoId.value = null;
};
</script>

<template>
    <div class="min-w-0 w-full">
        <div class="flex w-full max-w-[1180px] flex-col gap-3.5">
            <div class="flex items-center justify-end">
                <Link
                    :href="createEmpresa.url()"
                    class="cursor-pointer rounded-[7px] border border-primary bg-primary px-[13px] py-[7px] text-[12.5px] text-primary-foreground"
                >
                    Nova empresa
                </Link>
            </div>
            <div
                class="overflow-x-auto rounded-[10px] border border-border bg-card"
            >
                <table class="w-full min-w-[980px] table-fixed border-collapse">
                    <colgroup>
                        <col class="w-[20%]" />
                        <col class="w-[16%]" />
                        <col class="w-[7%]" />
                        <col class="w-[13%]" />
                        <col class="w-[11%]" />
                        <col class="w-[13%]" />
                        <col class="w-[11%]" />
                        <col class="w-[9%]" />
                    </colgroup>
                    <thead>
                        <tr class="bg-card">
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Empresa
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Setor / porte
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                UF
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Município
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Contatos
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Conflito
                            </th>
                            <th
                                class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Em negociação
                            </th>
                            <th
                                class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="empresas.length === 0">
                            <td
                                colspan="9"
                                class="border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px] text-muted-foreground"
                            >
                                Nenhuma empresa cadastrada.
                            </td>
                        </tr>
                        <tr
                            v-for="linha in empresas"
                            :key="linha.id"
                            class="cursor-pointer hover:bg-card"
                            :class="empresaId === linha.id ? 'bg-card' : ''"
                            @click="abrirEmpresa(linha.id)"
                        >
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <div class="flex min-w-0 flex-col gap-[3px]">
                                    <span
                                        class="truncate text-[13px] text-foreground"
                                    >
                                        {{ linha.nome }}
                                    </span>
                                    <span
                                        class="truncate font-[family-name:var(--font-crm-mono)] text-[11px] text-muted-foreground"
                                    >
                                        {{ linha.cnpj }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                            >
                                <span class="line-clamp-2">{{
                                    linha.setor
                                }}</span>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 font-[family-name:var(--font-crm-mono)] text-[12.5px] text-muted-foreground"
                            >
                                {{ linha.uf }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                            >
                                <span class="truncate">{{
                                    linha.municipio
                                }}</span>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-muted-foreground"
                            >
                                <span class="truncate">{{
                                    linha.contatosResumo
                                }}</span>
                            </td>
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <span
                                    class="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px]"
                                    :style="{
                                        background: linha.statusComercialBg,
                                        color: linha.statusComercialCor,
                                    }"
                                >
                                    {{ linha.statusComercial }}
                                </span>
                            </td>
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <span
                                    class="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px]"
                                    :style="{
                                        background: linha.conflitoBg,
                                        color: linha.conflitoCor,
                                    }"
                                >
                                    {{ linha.conflito }}
                                </span>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-right"
                                @click.stop
                            >
                                <div
                                    class="flex flex-col items-end gap-1.5"
                                >
                                    <div
                                        class="flex flex-col items-end gap-0.5"
                                    >
                                        <span
                                            class="font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                                        >
                                            {{ linha.valorFmt }}
                                        </span>
                                        <span class="text-[11px] text-muted-foreground">
                                            {{ linha.abertas }}
                                        </span>
                                    </div>
                                    <Link
                                        v-if="linha.drawer.negociacoes[0]"
                                        :href="
                                            negociacoesIndex.url(
                                                {},
                                                {
                                                    query: {
                                                        negociacao:
                                                            linha.drawer
                                                                .negociacoes[0]
                                                                .id,
                                                    },
                                                },
                                            )
                                        "
                                        class="inline-block rounded-[7px] border border-border bg-card px-[11px] py-1.5 text-[12px] text-primary hover:border-primary"
                                    >
                                        Abrir
                                    </Link>
                                    <Link
                                        v-else
                                        :href="
                                            createNegociacao.url(
                                                {},
                                                {
                                                    query: {
                                                        empresa_id: linha.id,
                                                    },
                                                },
                                            )
                                        "
                                        class="inline-block rounded-[7px] border border-primary bg-primary px-[11px] py-1.5 text-[12px] text-primary-foreground"
                                    >
                                        Nova
                                    </Link>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-right"
                                @click.stop
                            >
                                <Link
                                    :href="
                                        editEmpresa.url({ empresa: linha.id })
                                    "
                                    class="inline-block rounded-[7px] border border-border bg-card px-[11px] py-1.5 text-[12px] text-primary hover:border-primary"
                                >
                                    Editar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                class="m-0 max-w-[780px] text-[11.5px] leading-[1.55] text-muted-foreground"
            >
                Empresa é a conta: agrupa contatos, negociações e contratos sob
                o mesmo CNPJ. A verificação de conflito de interesses roda
                contra a base de clientes e partes contrárias antes da
                atribuição do responsável — negociação com conflito pendente não
                avança de etapa.
            </p>
        </div>

        <Teleport defer to="#crm-drawer-root">
            <CrmEmpresaDrawer
                v-if="empresaAberta"
                :modelo="empresaAberta.drawer"
                :empresa-id="empresaAberta.id"
                @close="fecharPainel"
                @open-contato="abrirContato"
            />
            <CrmContactDrawer
                v-else-if="contatoAberto"
                :contato="contatoAberto"
                @close="fecharPainel"
            />
        </Teleport>
    </div>
</template>
