<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { edit as editEmpresa } from '@/actions/App/Http/Controllers/EmpresaController';
import { create as createNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmDrawer from '@/components/crm/CrmDrawer.vue';
import CrmFieldCards from '@/components/crm/CrmFieldCards.vue';
import { index as negociacoesIndex } from '@/routes/negociacoes';
import type { EmpresaDrawer } from '@/types/crm';

defineProps<{
    modelo: EmpresaDrawer;
    empresaId: number;
}>();

const emit = defineEmits<{
    close: [];
    openContato: [id: number];
}>();
</script>

<template>
    <CrmDrawer
        :title="modelo.nome"
        :subtitle="`${modelo.cnpj ?? 'Sem CNPJ'} · ${modelo.cidade}`"
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
            <p class="m-0 text-[12.5px] leading-[1.55] text-muted-foreground">
                {{ modelo.conflitoTexto }}
            </p>
        </div>

        <div class="flex flex-col gap-2.5">
            <h3
                class="m-0 font-[family-name:var(--font-crm-display)] text-base font-medium"
            >
                Contatos vinculados
            </h3>
            <p
                v-if="modelo.contatos.length === 0"
                class="m-0 text-[12.5px] text-muted-foreground"
            >
                Nenhum contato vinculado.
            </p>
            <div
                v-for="contato in modelo.contatos"
                :key="contato.id"
                class="flex items-center justify-between gap-3 rounded-[9px] border border-border bg-card px-[13px] py-[11px]"
            >
                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span class="truncate text-[13px] text-foreground">
                        {{ contato.nome }}
                    </span>
                    <span class="truncate text-[11.5px] text-muted-foreground">
                        {{ contato.cargo }} · {{ contato.email }}
                    </span>
                </span>
                <span
                    class="shrink-0 rounded-full px-[9px] py-[3px] text-[11px]"
                    :style="{
                        background: contato.consentBg,
                        color: contato.consentCor,
                    }"
                >
                    {{ contato.consent }}
                </span>
                <button
                    type="button"
                    class="shrink-0 cursor-pointer rounded-[7px] border border-border bg-card px-[11px] py-1.5 text-[12px] text-primary hover:border-primary"
                    @click="emit('openContato', contato.id)"
                >
                    Visualizar
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-2.5">
            <div class="flex items-center justify-between gap-2">
                <h3
                    class="m-0 font-[family-name:var(--font-crm-display)] text-base font-medium"
                >
                    Negociações
                </h3>
                <Link
                    v-if="modelo.negociacoes.length === 0"
                    :href="
                        createNegociacao.url(
                            {},
                            { query: { empresa_id: empresaId } },
                        )
                    "
                    class="shrink-0 cursor-pointer rounded-[7px] border border-primary bg-primary px-[11px] py-1.5 text-[12px] text-primary-foreground"
                >
                    Nova negociação
                </Link>
            </div>
            <p
                v-if="modelo.negociacoes.length === 0"
                class="m-0 text-[12.5px] text-muted-foreground"
            >
                Nenhuma negociação vinculada.
            </p>
            <div
                v-for="negociacao in modelo.negociacoes"
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
                :href="editEmpresa.url({ empresa: empresaId })"
                class="flex-1 cursor-pointer rounded-lg border border-primary bg-primary py-2.5 text-center text-[13px] font-medium text-primary-foreground"
            >
                Editar empresa
            </Link>
        </template>
    </CrmDrawer>
</template>
