<script setup lang="ts">
import CrmDrawer from '@/components/crm/CrmDrawer.vue';
import CrmFieldCards from '@/components/crm/CrmFieldCards.vue';
import type { EmpresaDrawer } from '@/types/crm';

defineProps<{
    modelo: EmpresaDrawer;
}>();

const emit = defineEmits<{
    close: [];
    openContato: [id: number];
}>();
</script>

<template>
    <CrmDrawer
        :title="modelo.nome"
        :subtitle="`${modelo.cnpj} · ${modelo.cidade}`"
        subtitle-mono
        @close="emit('close')"
    >
        <CrmFieldCards :campos="modelo.campos" />

        <div
            class="flex flex-col gap-1.5 rounded-[9px] px-[15px] py-[13px]"
            :style="{
                border: `1px solid ${modelo.conflitoBorda}`,
                background: modelo.conflitoBg,
            }"
        >
            <span
                class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] uppercase"
                :style="{ color: modelo.conflitoCor }"
            >
                Conflito de interesses
            </span>
            <p class="m-0 text-[12.5px] leading-[1.55] text-[#3C4450]">
                {{ modelo.conflitoTexto }}
            </p>
        </div>

        <div class="flex flex-col gap-2.5">
            <h3
                class="m-0 font-[family-name:var(--font-crm-display)] text-base font-medium"
            >
                Contatos vinculados
            </h3>
            <button
                v-for="contato in modelo.contatos"
                :key="contato.id"
                type="button"
                class="flex cursor-pointer items-center justify-between gap-3 rounded-[9px] border border-[#E3DFD6] bg-white px-[13px] py-[11px] text-left hover:border-[#C79A4E]"
                @click="emit('openContato', contato.id)"
            >
                <span class="flex flex-col gap-0.5">
                    <span class="text-[13px] text-[#171B21]">
                        {{ contato.nome }}
                    </span>
                    <span class="text-[11.5px] text-[#77808E]">
                        {{ contato.cargo }} · {{ contato.email }}
                    </span>
                </span>
                <span
                    class="rounded-full px-[9px] py-[3px] text-[11px]"
                    :style="{
                        background: contato.consentBg,
                        color: contato.consentCor,
                    }"
                >
                    {{ contato.consent }}
                </span>
            </button>
        </div>

        <div class="flex flex-col gap-2.5">
            <h3
                class="m-0 font-[family-name:var(--font-crm-display)] text-base font-medium"
            >
                Negociações
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

        <template #footer>
            <button
                type="button"
                class="flex-1 cursor-pointer rounded-lg border border-[#171B21] bg-[#171B21] py-2.5 text-[13px] font-medium text-[#FBF9F4]"
            >
                Nova negociação para esta empresa
            </button>
        </template>
    </CrmDrawer>
</template>
