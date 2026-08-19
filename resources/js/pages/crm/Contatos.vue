<script setup lang="ts">
import { computed, ref } from 'vue';
import CrmContactDrawer from '@/components/crm/CrmContactDrawer.vue';
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
    <div>
        <div class="flex max-w-[1180px] flex-col gap-3.5">
            <div
                class="flex items-center gap-2.5 rounded-[10px] border border-[#E3DFD6] bg-white px-3.5 py-[11px]"
            >
                <span class="text-[12.5px] text-[#77808E]">Busca unificada</span>
                <input
                    v-model="busca"
                    type="search"
                    placeholder="nome, CPF/CNPJ, telefone ou e-mail"
                    class="min-w-0 flex-1 border-0 bg-transparent text-[13px] text-[#171B21] outline-none placeholder:text-[#A9A296]"
                />
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[11.5px] text-[#77808E]"
                >
                    {{ linhas.length }} registros
                </span>
            </div>

            <div
                class="overflow-hidden rounded-[10px] border border-[#E3DFD6] bg-white"
            >
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[#FBFAF7]">
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Contato
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Empresa / cargo
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Canal preferido
                            </th>
                            <th
                                class="px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Consentimento
                            </th>
                            <th
                                class="px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] text-[#77808E] uppercase"
                            >
                                Negociações
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="linhas.length === 0">
                            <td
                                colspan="5"
                                class="border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px] text-[#77808E]"
                            >
                                Nenhum contato encontrado.
                            </td>
                        </tr>
                        <tr
                            v-for="linha in linhas"
                            :key="linha.id"
                            class="cursor-pointer hover:bg-[#FBFAF7]"
                            :class="contatoId === linha.id ? 'bg-[#FBFAF7]' : ''"
                            @click="contatoId = linha.id"
                        >
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <div class="flex flex-col gap-[3px]">
                                    <span class="text-[13px] text-[#171B21]">
                                        {{ linha.nome }}
                                    </span>
                                    <span class="text-[11px] text-[#77808E]">
                                        {{ linha.dedupe }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-[#3C4450]"
                            >
                                {{ linha.contexto }}
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-[12.5px] text-[#3C4450]"
                            >
                                {{ linha.canal }}
                            </td>
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <span
                                    class="rounded-full px-[9px] py-[3px] text-[11px]"
                                    :style="{
                                        background: linha.consentBg,
                                        color: linha.consentCor,
                                    }"
                                >
                                    {{ linha.consent }}
                                </span>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-right font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                            >
                                {{ linha.negocios }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport defer to="#crm-drawer-root">
            <CrmContactDrawer
                v-if="contatoAberto"
                :modelo="contatoAberto.drawer"
                @close="contatoId = null"
            />
        </Teleport>
    </div>
</template>
