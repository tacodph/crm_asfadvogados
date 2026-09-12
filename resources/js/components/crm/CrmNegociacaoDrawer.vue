<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { edit } from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmDrawer from '@/components/crm/CrmDrawer.vue';
import CrmFieldCards from '@/components/crm/CrmFieldCards.vue';
import type { NegociacaoDrawer } from '@/types/crm';

defineProps<{
    modelo: NegociacaoDrawer;
    negociacaoId: number;
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
                class="inline-flex items-center gap-[5px] rounded-full bg-muted px-[9px] py-1 text-[11px] text-muted-foreground"
            >
                <span
                    class="h-[5px] w-[5px] rounded-full"
                    :style="{ background: modelo.canalCor }"
                />
                {{ modelo.canal }}
            </span>
            <span
                class="rounded-full bg-muted px-[9px] py-1 text-[11px] text-muted-foreground"
            >
                Resp. {{ modelo.responsavel }}
            </span>
            <span
                v-if="modelo.capi.estado === 'enviavel'"
                class="rounded-full px-[9px] py-1 text-[11px]"
                :style="{ background: modelo.capi.bg, color: modelo.capi.cor }"
                title="A API de Conversões da Meta recebe os eventos desta negociação"
            >
                {{ modelo.capi.label }}
            </span>
        </div>

        <CrmFieldCards :campos="modelo.campos" />

        <div
            class="flex flex-col gap-[7px] rounded-[9px] border border-primary/30 bg-[#FBF7EF] px-[15px] py-[13px]"
        >
            <span
                class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-primary uppercase"
            >
                Sigilo profissional
            </span>
            <p class="m-0 text-[12.5px] leading-[1.55] text-muted-foreground">
                Conteúdo do caso restrito a
                <strong class="font-medium">{{ modelo.responsavel }}</strong>
                e à sócia responsável. Este registro comercial guarda apenas
                objeto genérico, canal e histórico de contato — nenhum documento
                ou detalhe fático do caso.
            </p>
            <button
                type="button"
                class="mt-0.5 w-fit cursor-pointer rounded-md border border-primary/30 bg-card px-[11px] py-1.5 text-[12px] text-primary"
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
                <span class="text-[11.5px] text-muted-foreground">
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
                        <span class="w-px flex-1 bg-border" />
                    </div>
                    <div class="flex flex-col gap-[3px]">
                        <div
                            class="flex items-baseline justify-between gap-2.5"
                        >
                            <span
                                class="text-[12.5px] font-medium text-foreground"
                            >
                                {{ item.titulo }}
                            </span>
                            <span
                                class="font-[family-name:var(--font-crm-mono)] text-[11px] text-muted-foreground"
                            >
                                {{ item.quando }}
                            </span>
                        </div>
                        <span
                            class="text-[12.5px] leading-normal text-muted-foreground"
                        >
                            {{ item.descricao }}
                        </span>
                        <span class="text-[11px] text-muted-foreground">
                            {{ item.autor }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Link
                :href="edit.url({ negociacao: negociacaoId })"
                class="flex-1 cursor-pointer rounded-lg border border-primary bg-primary py-2.5 text-center text-[13px] font-medium text-primary-foreground"
            >
                Editar negociação
            </Link>
            <button
                type="button"
                class="cursor-pointer rounded-lg border border-border bg-card px-3.5 py-2.5 text-[13px] text-muted-foreground"
            >
                {{ modelo.avancandoLabel }}
            </button>
            <button
                type="button"
                class="cursor-pointer rounded-lg border border-[#E6C9C2] bg-card px-3.5 py-2.5 text-[13px] text-[#9B3B2F]"
            >
                Perdido
            </button>
        </template>
    </CrmDrawer>
</template>
