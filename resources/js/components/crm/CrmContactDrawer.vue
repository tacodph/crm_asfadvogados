<script setup lang="ts">
import CrmDrawer from '@/components/crm/CrmDrawer.vue';
import CrmFieldCards from '@/components/crm/CrmFieldCards.vue';
import type { ContatoDrawer } from '@/types/crm';

defineProps<{
    modelo: ContatoDrawer;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <CrmDrawer
        :title="modelo.nome"
        :subtitle="modelo.subtitulo"
        @close="emit('close')"
    >
        <CrmFieldCards :campos="modelo.campos" />

        <div
            class="flex flex-col gap-2 rounded-[9px] border border-[#E2D3B6] bg-[#FBF7EF] px-[15px] py-[13px]"
        >
            <span
                class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-[#8C6F3F] uppercase"
            >
                Consentimento e base legal
            </span>
            <div
                v-for="item in modelo.consentimentos"
                :key="item.finalidade"
                class="flex items-baseline justify-between gap-2.5 text-[12.5px] text-[#3C4450]"
            >
                <span>{{ item.finalidade }}</span>
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[11.5px]"
                    :style="{ color: item.cor }"
                >
                    {{ item.estado }}
                </span>
            </div>
            <div class="flex gap-2 pt-1">
                <button
                    type="button"
                    class="cursor-pointer rounded-md border border-[#E2D3B6] bg-white px-[11px] py-1.5 text-[12px] text-[#6F5730]"
                >
                    Exportar dados do titular
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-md border border-[#E6C9C2] bg-white px-[11px] py-1.5 text-[12px] text-[#9B3B2F]"
                >
                    Registrar revogação
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-2.5">
            <h3
                class="m-0 font-[family-name:var(--font-crm-display)] text-base font-medium"
            >
                Negociações do contato
            </h3>
            <p
                v-if="modelo.negociacoes.length === 0"
                class="m-0 text-[12.5px] text-[#77808E]"
            >
                Nenhuma negociação vinculada.
            </p>
            <div
                v-for="negociacao in modelo.negociacoes"
                :key="negociacao.id"
                class="flex items-center justify-between gap-3 rounded-[9px] border border-[#E3DFD6] bg-white px-[13px] py-[11px]"
            >
                <span class="flex flex-col gap-0.5">
                    <span class="text-[13px] text-[#171B21]">
                        {{ negociacao.titulo }}
                    </span>
                    <span class="text-[11.5px] text-[#77808E]">
                        {{ negociacao.etapa }} · {{ negociacao.responsavel }}
                    </span>
                </span>
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                >
                    {{ negociacao.valorFmt }}
                </span>
            </div>
        </div>
    </CrmDrawer>
</template>
