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
const filtroUf = ref('todos');
const filtroCanal = ref('todos');
const filtroStatus = ref('todos');
const filtroConsentimento = ref('todos');
const contatoId = ref<number | null>(null);

const opcoesUf = computed(() =>
    [...new Set(contatos.map((contato) => contato.uf))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const opcoesCanal = computed(() =>
    [...new Set(contatos.map((contato) => contato.canal))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const opcoesStatus = computed(() =>
    [...new Set(contatos.map((contato) => contato.statusComercial))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const opcoesConsentimento = computed(() =>
    [...new Set(contatos.map((contato) => contato.consent))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const filtrosAtivos = computed(
    () =>
        busca.value.trim() !== '' ||
        filtroUf.value !== 'todos' ||
        filtroCanal.value !== 'todos' ||
        filtroStatus.value !== 'todos' ||
        filtroConsentimento.value !== 'todos',
);

const linhas = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    const digits = termo.replace(/\D/g, '');

    return contatos.filter((contato) => {
        if (filtroUf.value !== 'todos' && contato.uf !== filtroUf.value) {
            return false;
        }

        if (
            filtroCanal.value !== 'todos' &&
            contato.canal !== filtroCanal.value
        ) {
            return false;
        }

        if (
            filtroStatus.value !== 'todos' &&
            contato.statusComercial !== filtroStatus.value
        ) {
            return false;
        }

        if (
            filtroConsentimento.value !== 'todos' &&
            contato.consent !== filtroConsentimento.value
        ) {
            return false;
        }

        if (!termo) {
            return true;
        }

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
            contato.statusComercial.toLowerCase().includes(termo) ||
            contato.consent.toLowerCase().includes(termo) ||
            (digits !== '' &&
                (telefone.includes(digits) ||
                    cpf.includes(digits) ||
                    cnpj.includes(digits)))
        );
    });
});

const limparFiltros = (): void => {
    busca.value = '';
    filtroUf.value = 'todos';
    filtroCanal.value = 'todos';
    filtroStatus.value = 'todos';
    filtroConsentimento.value = 'todos';
};

const contatoAberto = computed(
    () => contatos.find((contato) => contato.id === contatoId.value) ?? null,
);
</script>

<template>
    <div class="w-full min-w-0 max-w-full">
        <div class="flex w-full min-w-0 max-w-[1180px] flex-col gap-3.5">
            <div class="flex flex-col gap-2.5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div
                        class="border-border bg-card flex min-w-0 flex-1 items-center gap-2.5 rounded-[10px] border px-3.5 py-[11px]"
                    >
                        <span
                            class="text-muted-foreground shrink-0 text-[12.5px]"
                            >Busca</span
                        >
                        <input
                            v-model="busca"
                            type="search"
                            placeholder="nome, CPF/CNPJ, telefone, e-mail ou município"
                            class="text-foreground placeholder:text-muted-foreground min-w-0 flex-1 border-0 bg-transparent text-[13px] outline-none"
                        />
                        <span
                            class="text-muted-foreground shrink-0 font-[family-name:var(--font-crm-mono)] text-[11.5px]"
                        >
                            {{ linhas.length }}
                            {{
                                linhas.length === 1 ? 'registro' : 'registros'
                            }}
                        </span>
                    </div>
                    <Link
                        :href="createContato.url()"
                        class="border-primary bg-primary text-primary-foreground shrink-0 cursor-pointer rounded-[7px] border px-[13px] py-[10px] text-[12.5px]"
                    >
                        Novo contato
                    </Link>
                </div>

                <div
                    class="border-border bg-card flex flex-wrap items-center gap-2 rounded-[10px] border px-3 py-2.5"
                >
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.1em] uppercase"
                    >
                        Filtros
                    </span>

                    <select
                        v-model="filtroUf"
                        class="border-border text-foreground focus:border-primary min-w-[100px] cursor-pointer rounded-md border bg-white px-2.5 py-1.5 text-[12.5px] outline-none"
                    >
                        <option value="todos">Todas as UFs</option>
                        <option
                            v-for="uf in opcoesUf"
                            :key="uf"
                            :value="uf"
                        >
                            {{ uf }}
                        </option>
                    </select>

                    <select
                        v-model="filtroCanal"
                        class="border-border text-foreground focus:border-primary min-w-[140px] flex-1 cursor-pointer rounded-md border bg-white px-2.5 py-1.5 text-[12.5px] outline-none"
                    >
                        <option value="todos">Todos os canais</option>
                        <option
                            v-for="canal in opcoesCanal"
                            :key="canal"
                            :value="canal"
                        >
                            {{ canal }}
                        </option>
                    </select>

                    <select
                        v-model="filtroStatus"
                        class="border-border text-foreground focus:border-primary min-w-[150px] flex-1 cursor-pointer rounded-md border bg-white px-2.5 py-1.5 text-[12.5px] outline-none"
                    >
                        <option value="todos">Todos os status</option>
                        <option
                            v-for="status in opcoesStatus"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </option>
                    </select>

                    <select
                        v-model="filtroConsentimento"
                        class="border-border text-foreground focus:border-primary min-w-[160px] flex-1 cursor-pointer rounded-md border bg-white px-2.5 py-1.5 text-[12.5px] outline-none"
                    >
                        <option value="todos">Todos os consentimentos</option>
                        <option
                            v-for="consentimento in opcoesConsentimento"
                            :key="consentimento"
                            :value="consentimento"
                        >
                            {{ consentimento }}
                        </option>
                    </select>

                    <button
                        v-if="filtrosAtivos"
                        type="button"
                        class="cursor-pointer rounded-md px-2.5 py-1.5 text-[12px] font-medium text-[#9B3B2F] transition-colors hover:bg-[#FDF3F2]"
                        @click="limparFiltros"
                    >
                        Limpar
                    </button>
                </div>
            </div>

            <div
                class="border-border bg-card min-w-0 max-w-full overflow-x-auto rounded-[10px] border"
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
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Contato
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Empresa / cargo
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Telefone / e-mail
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                UF
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Município
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Canal
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Consentimento
                            </th>
                            <th
                                class="text-muted-foreground px-3 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal tracking-[0.1em] uppercase"
                            >
                                Neg.
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="linhas.length === 0">
                            <td
                                colspan="9"
                                class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px]"
                            >
                                Nenhum contato encontrado.
                            </td>
                        </tr>
                        <tr
                            v-for="linha in linhas"
                            :key="linha.id"
                            class="hover:bg-card cursor-pointer"
                            :class="contatoId === linha.id ? 'bg-card' : ''"
                            @click="contatoId = linha.id"
                        >
                            <td class="border-t border-[#F2EFE8] px-3 py-3">
                                <div class="flex min-w-0 flex-col gap-[3px]">
                                    <span
                                        class="text-foreground truncate text-[13px]"
                                    >
                                        {{ linha.nome }}
                                    </span>
                                    <div
                                        class="flex min-w-0 flex-wrap items-center gap-1.5"
                                    >
                                        <span
                                            v-if="linha.dedupeMesclado"
                                            class="text-primary rounded border border-[#E8D9B8] bg-[#F7F1E4] px-1.5 py-0.5 font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.06em] uppercase"
                                        >
                                            mesclado
                                        </span>
                                        <span
                                            class="text-muted-foreground truncate text-[11px]"
                                        >
                                            {{ linha.dedupe }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-3 py-3 text-[12.5px]"
                            >
                                <span class="line-clamp-2">{{
                                    linha.contexto
                                }}</span>
                            </td>
                            <td class="border-t border-[#F2EFE8] px-3 py-3">
                                <div class="flex min-w-0 flex-col gap-[3px]">
                                    <span
                                        class="text-foreground truncate font-[family-name:var(--font-crm-mono)] text-[12px]"
                                    >
                                        {{ linha.telefone }}
                                    </span>
                                    <span
                                        class="text-muted-foreground truncate text-[11.5px]"
                                    >
                                        {{ linha.email }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-3 py-3 font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                            >
                                {{ linha.uf }}
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-3 py-3 text-[12.5px]"
                            >
                                <span class="truncate">{{
                                    linha.municipio
                                }}</span>
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-3 py-3 text-[12.5px]"
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
                                    class="border-border bg-card text-primary hover:border-primary inline-block rounded-[7px] border px-2 py-1 text-[11px]"
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
                                    class="border-primary bg-primary text-primary-foreground inline-block rounded-[7px] border px-2 py-1 text-[11px]"
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
