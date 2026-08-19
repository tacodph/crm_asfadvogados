<script setup lang="ts">
import { computed, ref } from 'vue';
import CrmContactDrawer from '@/components/crm/CrmContactDrawer.vue';
import CrmEmpresaDrawer from '@/components/crm/CrmEmpresaDrawer.vue';
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
    <div>
        <div class="flex max-w-[1180px] flex-col gap-3.5">
            <div
                class="overflow-hidden rounded-[10px] border border-[#E3DFD6] bg-white"
            >
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[#FBFAF7]">
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Empresa
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Setor / porte
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Contatos
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Conflito de interesses
                            </th>
                            <th
                                class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Em negociação
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="empresas.length === 0">
                            <td
                                colspan="5"
                                class="border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px] text-[#77808E]"
                            >
                                Nenhuma empresa cadastrada.
                            </td>
                        </tr>
                        <tr
                            v-for="linha in empresas"
                            :key="linha.id"
                            class="cursor-pointer hover:bg-[#FBFAF7]"
                            :class="empresaId === linha.id ? 'bg-[#FBFAF7]' : ''"
                            @click="abrirEmpresa(linha.id)"
                        >
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <div class="flex flex-col gap-[3px]">
                                    <span class="text-[13px] text-[#171B21]">
                                        {{ linha.nome }}
                                    </span>
                                    <span
                                        class="font-[family-name:var(--font-crm-mono)] text-[11px] text-[#77808E]"
                                    >
                                        {{ linha.cnpj }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-[#3C4450]"
                            >
                                {{ linha.setor }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-[#3C4450]"
                            >
                                {{ linha.contatosResumo }}
                            </td>
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <span
                                    class="rounded-full px-[9px] py-[3px] text-[11px]"
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
                            >
                                <div class="flex flex-col items-end gap-0.5">
                                    <span
                                        class="font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                                    >
                                        {{ linha.valorFmt }}
                                    </span>
                                    <span class="text-[11px] text-[#77808E]">
                                        {{ linha.abertas }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                class="m-0 max-w-[780px] text-[11.5px] leading-[1.55] text-[#77808E]"
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
                @close="fecharPainel"
                @open-contato="abrirContato"
            />
            <CrmContactDrawer
                v-else-if="contatoAberto"
                :modelo="contatoAberto.drawer"
                @close="fecharPainel"
            />
        </Teleport>
    </div>
</template>
