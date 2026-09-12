<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { edit as editContato } from '@/actions/App/Http/Controllers/ContatoController';
import { create as createNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmDrawer from '@/components/crm/CrmDrawer.vue';
import CrmFieldCards from '@/components/crm/CrmFieldCards.vue';
import { index as negociacoesIndex } from '@/routes/negociacoes';
import type { ContatoLinha } from '@/types/crm';

defineProps<{
    contato: ContatoLinha;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <CrmDrawer
        :title="contato.drawer.nome"
        :subtitle="contato.drawer.subtitulo"
        @close="emit('close')"
    >
        <CrmFieldCards :campos="contato.drawer.campos" />

        <div
            class="flex flex-col gap-2 rounded-[9px] border border-primary/30 bg-[#FBF7EF] px-[15px] py-[13px]"
        >
            <span
                class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-primary uppercase"
            >
                Consentimento e base legal
            </span>
            <div
                v-for="item in contato.drawer.consentimentos"
                :key="item.finalidade"
                class="flex items-baseline justify-between gap-2.5 text-[12.5px] text-muted-foreground"
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
                    class="cursor-pointer rounded-md border border-primary/30 bg-card px-[11px] py-1.5 text-[12px] text-primary"
                >
                    Exportar dados do titular
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-md border border-[#E6C9C2] bg-card px-[11px] py-1.5 text-[12px] text-[#9B3B2F]"
                >
                    Registrar revogação
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-2.5">
            <div class="flex items-center justify-between gap-2">
                <h3
                    class="m-0 font-[family-name:var(--font-crm-display)] text-base font-medium"
                >
                    Negociações do contato
                </h3>
                <Link
                    v-if="contato.drawer.negociacoes.length === 0"
                    :href="
                        createNegociacao.url(
                            {},
                            { query: { contato_id: contato.id } },
                        )
                    "
                    class="shrink-0 cursor-pointer rounded-[7px] border border-primary bg-primary px-[11px] py-1.5 text-[12px] text-primary-foreground"
                >
                    Nova negociação
                </Link>
            </div>
            <p
                v-if="contato.drawer.negociacoes.length === 0"
                class="m-0 text-[12.5px] text-muted-foreground"
            >
                Nenhuma negociação vinculada.
            </p>
            <div
                v-for="negociacao in contato.drawer.negociacoes"
                :key="negociacao.id"
                class="flex items-center justify-between gap-3 rounded-[9px] border border-border bg-card px-[13px] py-[11px]"
            >
                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span class="truncate text-[13px] text-foreground">
                        {{ negociacao.titulo }}
                    </span>
                    <span class="truncate text-[11.5px] text-muted-foreground">
                        {{ negociacao.etapa }} · {{ negociacao.responsavel }}
                    </span>
                </span>
                <span
                    class="shrink-0 font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                >
                    {{ negociacao.valorFmt }}
                </span>
                <Link
                    :href="
                        negociacoesIndex.url(
                            {},
                            { query: { negociacao: negociacao.id } },
                        )
                    "
                    class="shrink-0 cursor-pointer rounded-[7px] border border-border bg-card px-[11px] py-1.5 text-[12px] text-primary hover:border-primary"
                >
                    Abrir
                </Link>
            </div>
        </div>

        <template #footer>
            <Link
                :href="editContato.url({ contato: contato.id })"
                class="flex-1 cursor-pointer rounded-lg border border-primary bg-primary py-2.5 text-center text-[13px] font-medium text-primary-foreground"
            >
                Editar contato
            </Link>
        </template>
    </CrmDrawer>
</template>
