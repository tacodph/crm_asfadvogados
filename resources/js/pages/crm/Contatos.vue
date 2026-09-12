<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { create as createContato } from '@/actions/App/Http/Controllers/ContatoController';
import { create as createNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmContactDrawer from '@/components/crm/CrmContactDrawer.vue';
import { index as negociacoesIndex } from '@/routes/negociacoes';
import type { ContatoLinha } from '@/types/crm';

const { contatos } = defineProps<{
    contatos: ContatoLinha[];
}>();

const busca = ref('');
const contatoId = ref<number | null>(null);

const linhas = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    const digits = termo.replace(/\D/g, '');

    if (!termo) {
        return contatos;
    }

    return contatos.filter((contato) => {
        const telefone = contato.telefone.replace(/\D/g, '');
        const cpf = (contato.cpf ?? '').replace(/\D/g, '');
        const cnpj = (contato.cnpj ?? '').replace(/\D/g, '');

        return (
            contato.nome.toLowerCase().includes(termo) ||
            contato.contexto.toLowerCase().includes(termo) ||
            contato.email.toLowerCase().includes(termo) ||
            contato.municipio.toLowerCase().includes(termo) ||
            contato.uf.toLowerCase().includes(termo) ||
            contato.canal.toLowerCase().includes(termo) ||
            (digits !== '' &&
                (telefone.includes(digits) ||
                    cpf.includes(digits) ||
                    cnpj.includes(digits)))
        );
    });
});

const contatoAberto = computed(
    () => contatos.find((contato) => contato.id === contatoId.value) ?? null,
);
</script>

<template>
    <div class="min-w-0 w-full max-w-full">
        <div class="flex min-w-0 w-full max-w-[1180px] flex-col gap-3.5">
            <div class="flex min-w-0 items-center justify-between gap-3">
                <div
                    class="flex min-w-0 flex-1 items-center gap-2.5 rounded-[10px] border border-border bg-card px-3.5 py-[11px]"
                >
                    <span class="text-[12.5px] text-muted-foreground"
                        >Busca unificada</span
                    >
                    <input
                        v-model="busca"
                        type="search"
                        placeholder="nome, CPF/CNPJ, telefone, e-mail ou município"
                        class="min-w-0 flex-1 border-0 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted-foreground"
                    />
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[11.5px] text-muted-foreground"
                    >
                        {{ linhas.length }} registros
                    </span>
                </div>
                <Link
                    :href="createContato.url()"
                    class="shrink-0 cursor-pointer rounded-[7px] border border-primary bg-primary px-[13px] py-[10px] text-[12.5px] text-primary-foreground"
                >
                    Novo contato
                </Link>
            </div>

            <div
                class="min-w-0 max-w-full overflow-x-auto rounded-[10px] border border-border bg-card"
            >
                <table class="w-full table-fixed border-collapse">
                    <colgroup>
                        <col class="w-[16%]" />
                        <col class="w-[15%]" />
                        <col class="w-[14%]" />
                        <col class="w-[4%]" />
                        <col class="w-[11%]" />
                        <col class="w-[9%]" />
                        <col class="w-[10%]" />
                        <col class="w-[10%]" />
                        <col class="w-[11%]" />
                    </colgroup>
                    <thead>
                        <tr class="bg-card">
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Contato
                            </th>
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Empresa / cargo
                            </th>
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Telefone / e-mail
                            </th>
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                UF
                            </th>
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Município
                            </th>
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Canal
                            </th>
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Consentimento
                            </th>
                            <th
                                class="px-3 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Neg.
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="linhas.length === 0">
                            <td
                                colspan="9"
                                class="border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px] text-muted-foreground"
                            >
                                Nenhum contato encontrado.
                            </td>
                        </tr>
                        <tr
                            v-for="linha in linhas"
                            :key="linha.id"
                            class="cursor-pointer hover:bg-card"
                            :class="contatoId === linha.id ? 'bg-card' : ''"
                            @click="contatoId = linha.id"
                        >
                            <td class="border-t border-[#F2EFE8] px-3 py-3">
                                <div class="flex min-w-0 flex-col gap-[3px]">
                                    <span
                                        class="truncate text-[13px] text-foreground"
                                    >
                                        {{ linha.nome }}
                                    </span>
                                    <div
                                        class="flex min-w-0 flex-wrap items-center gap-1.5"
                                    >
                                        <span
                                            v-if="linha.dedupeMesclado"
                                            class="rounded border border-[#E8D9B8] bg-[#F7F1E4] px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.06em] text-primary uppercase"
                                        >
                                            mesclado
                                        </span>
                                        <span
                                            class="truncate text-[11px] text-muted-foreground"
                                        >
                                            {{ linha.dedupe }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-3 py-3 text-[12.5px] text-muted-foreground"
                            >
                                <span class="line-clamp-2">{{
                                    linha.contexto
                                }}</span>
                            </td>
                            <td class="border-t border-[#F2EFE8] px-3 py-3">
                                <div class="flex min-w-0 flex-col gap-[3px]">
                                    <span
                                        class="truncate font-[family-name:var(--font-crm-mono)] text-[12px] text-foreground"
                                    >
                                        {{ linha.telefone }}
                                    </span>
                                    <span
                                        class="truncate text-[11.5px] text-muted-foreground"
                                    >
                                        {{ linha.email }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-3 py-3 font-[family-name:var(--font-crm-mono)] text-[12.5px] text-muted-foreground"
                            >
                                {{ linha.uf }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-3 py-3 text-[12.5px] text-muted-foreground"
                            >
                                <span class="truncate">{{
                                    linha.municipio
                                }}</span>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-3 py-3 text-[12.5px] text-muted-foreground"
                            >
                                <span class="truncate">{{ linha.canal }}</span>
                            </td>
                            <td class="border-t border-[#F2EFE8] px-3 py-3">
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
                            <td class="border-t border-[#F2EFE8] px-3 py-3">
                                <span
                                    class="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px]"
                                    :style="{
                                        background: linha.consentBg,
                                        color: linha.consentCor,
                                    }"
                                >
                                    {{ linha.consent }}
                                </span>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-3 py-3 text-right"
                                @click.stop
                            >
                                <Link
                                    v-if="linha.drawer.negociacoes[0]"
                                    :href="
                                        negociacoesIndex.url(
                                            {},
                                            {
                                                query: {
                                                    negociacao:
                                                        linha.drawer
                                                            .negociacoes[0].id,
                                                },
                                            },
                                        )
                                    "
                                    class="inline-block rounded-[7px] border border-border bg-card px-2 py-1 text-[11px] text-primary hover:border-primary"
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
                                                    contato_id: linha.id,
                                                },
                                            },
                                        )
                                    "
                                    class="inline-block rounded-[7px] border border-primary bg-primary px-2 py-1 text-[11px] text-primary-foreground"
                                >
                                    Nova
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport defer to="#crm-drawer-root">
            <CrmContactDrawer
                v-if="contatoAberto"
                :contato="contatoAberto"
                @close="contatoId = null"
            />
        </Teleport>
    </div>
</template>
