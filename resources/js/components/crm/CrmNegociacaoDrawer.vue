<script setup lang="ts">
import CrmDrawer from '@/components/crm/CrmDrawer.vue';
import CrmFieldCards from '@/components/crm/CrmFieldCards.vue';
import type { NegociacaoDrawer } from '@/types/crm';

defineProps<{
    modelo: NegociacaoDrawer;
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
        <div class="flex flex-wrap gap-[7px]">
            <span
                class="inline-flex items-center gap-[5px] rounded-full bg-[#F4F2EC] px-[9px] py-1 text-[11px] text-[#3C4450]"
            >
                <span
                    class="h-[5px] w-[5px] rounded-full"
                    :style="{ background: modelo.canalCor }"
                />
                {{ modelo.canal }}
            </span>
            <span
                class="rounded-full bg-[#F4F2EC] px-[9px] py-1 text-[11px] text-[#3C4450]"
            >
                Resp. {{ modelo.responsavel }}
            </span>
            <span
                class="rounded-full px-[9px] py-1 text-[11px]"
                :style="{
                    background: modelo.consentBg,
                    color: modelo.consentCor,
                }"
            >
                {{ modelo.consent }}
            </span>
        </div>

        <CrmFieldCards :campos="modelo.campos" />

        <div
            class="flex flex-col gap-[7px] rounded-[9px] border border-[#E2D3B6] bg-[#FBF7EF] px-[15px] py-[13px]"
        >
            <span
                class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-[#8C6F3F] uppercase"
            >
                Sigilo profissional
            </span>
            <p class="m-0 text-[12.5px] leading-[1.55] text-[#3C4450]">
                Conteúdo do caso restrito a
                <strong class="font-medium">{{ modelo.responsavel }}</strong>
                e à sócia responsável. Este registro comercial guarda apenas
                objeto genérico, canal e histórico de contato — nenhum documento
                ou detalhe fático do caso.
            </p>
            <button
                type="button"
                class="mt-0.5 w-fit cursor-pointer rounded-md border border-[#E2D3B6] bg-white px-[11px] py-1.5 text-[12px] text-[#6F5730]"
            >
                Solicitar acesso registrado
            </button>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h3
                    class="m-0 font-[family-name:var(--font-crm-display)] text-base font-medium"
                >
                    Histórico unificado
                </h3>
                <span class="text-[11.5px] text-[#77808E]">
                    {{ modelo.historicos.length }} interações
                </span>
            </div>
            <div class="flex flex-col">
                <div
                    v-for="item in modelo.historicos"
                    :key="item.titulo"
                    class="grid grid-cols-[14px_1fr] gap-3 pb-[15px]"
                >
                    <div class="flex flex-col items-center gap-1">
                        <span
                            class="mt-1 h-[9px] w-[9px] rounded-full"
                            :style="{ background: item.cor }"
                        />
                        <span class="w-px flex-1 bg-[#EEEBE4]" />
                    </div>
                    <div class="flex flex-col gap-[3px]">
                        <div
                            class="flex items-baseline justify-between gap-2.5"
                        >
                            <span
                                class="text-[12.5px] font-medium text-[#171B21]"
                            >
                                {{ item.titulo }}
                            </span>
                            <span
                                class="font-[family-name:var(--font-crm-mono)] text-[11px] text-[#77808E]"
                            >
                                {{ item.quando }}
                            </span>
                        </div>
                        <span
                            class="text-[12.5px] leading-normal text-[#3C4450]"
                        >
                            {{ item.descricao }}
                        </span>
                        <span class="text-[11px] text-[#77808E]">
                            {{ item.autor }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <button
                type="button"
                class="flex-1 cursor-pointer rounded-lg border border-[#171B21] bg-[#171B21] py-2.5 text-[13px] font-medium text-[#FBF9F4]"
            >
                {{ modelo.avancandoLabel }}
            </button>
            <button
                type="button"
                class="cursor-pointer rounded-lg border border-[#E3DFD6] bg-white px-3.5 py-2.5 text-[13px] text-[#3C4450]"
            >
                Gerar proposta
            </button>
            <button
                type="button"
                class="cursor-pointer rounded-lg border border-[#E6C9C2] bg-white px-3.5 py-2.5 text-[13px] text-[#9B3B2F]"
            >
                Perdido
            </button>
        </template>
    </CrmDrawer>
</template>
