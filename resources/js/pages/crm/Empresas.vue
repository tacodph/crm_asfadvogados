<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    create as createEmpresa,
    edit as editEmpresa,
} from '@/actions/App/Http/Controllers/EmpresaController';
import { create as createNegociacao } from '@/actions/App/Http/Controllers/NegociacaoController';
import CrmContactDrawer from '@/components/crm/CrmContactDrawer.vue';
import CrmEmpresaDrawer from '@/components/crm/CrmEmpresaDrawer.vue';
import { index as negociacoesIndex } from '@/routes/negociacoes';
import type { EmpresaLinha } from '@/types/crm';

const { empresas } = defineProps<{
    empresas: EmpresaLinha[];
}>();

const busca = ref('');
const filtroSetor = ref('todos');
const filtroUf = ref('todos');
const filtroStatus = ref('todos');
const filtroConflito = ref('todos');

const empresaId = ref<number | null>(null);
const contatoId = ref<number | null>(null);

const nomeSetor = (empresa: EmpresaLinha): string => {
    const campo = empresa.drawer.campos.find((item) => item.label === 'Setor');

    if (campo?.valor) {
        return campo.valor;
    }

    const [setor] = empresa.setor.split(' · ');

    return setor?.trim() || empresa.setor;
};

const opcoesSetor = computed(() =>
    [...new Set(empresas.map((empresa) => nomeSetor(empresa)))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const opcoesUf = computed(() =>
    [...new Set(empresas.map((empresa) => empresa.uf))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const opcoesStatus = computed(() =>
    [...new Set(empresas.map((empresa) => empresa.statusComercial))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const opcoesConflito = computed(() =>
    [...new Set(empresas.map((empresa) => empresa.conflito))]
        .filter((valor) => valor !== '')
        .sort((a, b) => a.localeCompare(b, 'pt-BR')),
);

const filtrosAtivos = computed(
    () =>
        busca.value.trim() !== '' ||
        filtroSetor.value !== 'todos' ||
        filtroUf.value !== 'todos' ||
        filtroStatus.value !== 'todos' ||
        filtroConflito.value !== 'todos',
);

const linhas = computed(() => {
    const termo = busca.value.trim().toLowerCase();
    const digits = termo.replace(/\D/g, '');

    return empresas.filter((empresa) => {
        if (
            filtroSetor.value !== 'todos' &&
            nomeSetor(empresa) !== filtroSetor.value
        ) {
            return false;
        }

        if (filtroUf.value !== 'todos' && empresa.uf !== filtroUf.value) {
            return false;
        }

        if (
            filtroStatus.value !== 'todos' &&
            empresa.statusComercial !== filtroStatus.value
        ) {
            return false;
        }

        if (
            filtroConflito.value !== 'todos' &&
            empresa.conflito !== filtroConflito.value
        ) {
            return false;
        }

        if (!termo) {
            return true;
        }

        const cnpj = (empresa.cnpj ?? '').replace(/\D/g, '');

        return (
            empresa.nome.toLowerCase().includes(termo) ||
            empresa.setor.toLowerCase().includes(termo) ||
            empresa.municipio.toLowerCase().includes(termo) ||
            empresa.uf.toLowerCase().includes(termo) ||
            empresa.statusComercial.toLowerCase().includes(termo) ||
            empresa.conflito.toLowerCase().includes(termo) ||
            empresa.contatosResumo.toLowerCase().includes(termo) ||
            (digits !== '' && cnpj.includes(digits)) ||
            (empresa.cnpj ?? '').toLowerCase().includes(termo)
        );
    });
});

const limparFiltros = (): void => {
    busca.value = '';
    filtroSetor.value = 'todos';
    filtroUf.value = 'todos';
    filtroStatus.value = 'todos';
    filtroConflito.value = 'todos';
};

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
    <div class="w-full min-w-0">
        <div class="flex w-full max-w-[1180px] flex-col gap-3.5">
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
                            placeholder="nome, CNPJ, município, contato ou status"
                            class="text-foreground placeholder:text-muted-foreground min-w-0 flex-1 border-0 bg-transparent text-[13px] outline-none"
                        />
                        <span
                            class="text-muted-foreground shrink-0 font-[family-name:var(--font-crm-mono)] text-[11.5px]"
                        >
                            {{ linhas.length }}
                            {{ linhas.length === 1 ? 'registro' : 'registros' }}
                        </span>
                    </div>
                    <Link
                        :href="createEmpresa.url()"
                        class="border-primary bg-primary text-primary-foreground shrink-0 cursor-pointer rounded-[7px] border px-[13px] py-[10px] text-[12.5px]"
                    >
                        Nova empresa
                    </Link>
                </div>

                <div
                    class="border-border bg-card flex flex-wrap items-center gap-2 rounded-[10px] border px-3 py-2.5"
                >
                    <span
                        class="text-muted-foreground font-[family-name:var(--font-crm-mono)] text-[10px] uppercase tracking-[0.1em]"
                    >
                        Filtros
                    </span>

                    <select
                        v-model="filtroSetor"
                        class="border-border text-foreground focus:border-primary min-w-[140px] flex-1 cursor-pointer rounded-md border bg-white px-2.5 py-1.5 text-[12.5px] outline-none"
                    >
                        <option value="todos">Todos os setores</option>
                        <option
                            v-for="setor in opcoesSetor"
                            :key="setor"
                            :value="setor"
                        >
                            {{ setor }}
                        </option>
                    </select>

                    <select
                        v-model="filtroUf"
                        class="border-border text-foreground focus:border-primary min-w-[100px] cursor-pointer rounded-md border bg-white px-2.5 py-1.5 text-[12.5px] outline-none"
                    >
                        <option value="todos">Todas as UFs</option>
                        <option v-for="uf in opcoesUf" :key="uf" :value="uf">
                            {{ uf }}
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
                        v-model="filtroConflito"
                        class="border-border text-foreground focus:border-primary min-w-[150px] flex-1 cursor-pointer rounded-md border bg-white px-2.5 py-1.5 text-[12.5px] outline-none"
                    >
                        <option value="todos">Todos os conflitos</option>
                        <option
                            v-for="conflito in opcoesConflito"
                            :key="conflito"
                            :value="conflito"
                        >
                            {{ conflito }}
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
                class="border-border bg-card overflow-x-auto rounded-[10px] border"
            >
                <table class="w-full min-w-[980px] table-fixed border-collapse">
                    <colgroup>
                        <col class="w-[20%]" />
                        <col class="w-[16%]" />
                        <col class="w-[7%]" />
                        <col class="w-[13%]" />
                        <col class="w-[11%]" />
                        <col class="w-[13%]" />
                        <col class="w-[11%]" />
                        <col class="w-[9%]" />
                    </colgroup>
                    <thead>
                        <tr class="bg-card">
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Empresa
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Setor / porte
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                UF
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Município
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Contatos
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Status
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-left font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Conflito
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Em negociação
                            </th>
                            <th
                                class="text-muted-foreground px-4 py-[11px] text-right font-[family-name:var(--font-crm-mono)] text-[10px] font-normal uppercase tracking-[0.1em]"
                            >
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="empresas.length === 0">
                            <td
                                colspan="9"
                                class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px]"
                            >
                                Nenhuma empresa cadastrada.
                            </td>
                        </tr>
                        <tr v-else-if="linhas.length === 0">
                            <td
                                colspan="9"
                                class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-6 text-center text-[13px]"
                            >
                                Nenhuma empresa encontrada com os filtros
                                aplicados.
                                <button
                                    type="button"
                                    class="ml-1 cursor-pointer text-[#9B3B2F] underline"
                                    @click="limparFiltros"
                                >
                                    Limpar filtros
                                </button>
                            </td>
                        </tr>
                        <tr
                            v-for="linha in linhas"
                            :key="linha.id"
                            class="hover:bg-card cursor-pointer"
                            :class="empresaId === linha.id ? 'bg-card' : ''"
                            @click="abrirEmpresa(linha.id)"
                        >
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <div class="flex min-w-0 flex-col gap-[3px]">
                                    <span
                                        class="text-foreground truncate text-[13px]"
                                    >
                                        {{ linha.nome }}
                                    </span>
                                    <span
                                        class="text-muted-foreground truncate font-[family-name:var(--font-crm-mono)] text-[11px]"
                                    >
                                        {{ linha.cnpj ?? '—' }}
                                    </span>
                                </div>
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                            >
                                <span class="line-clamp-2">{{
                                    linha.setor
                                }}</span>
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-3 font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                            >
                                {{ linha.uf }}
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                            >
                                <span class="truncate">{{
                                    linha.municipio
                                }}</span>
                            </td>
                            <td
                                class="text-muted-foreground border-t border-[#F2EFE8] px-4 py-3 text-[12.5px]"
                            >
                                <span class="truncate">{{
                                    linha.contatosResumo
                                }}</span>
                            </td>
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
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
                            <td class="border-t border-[#F2EFE8] px-4 py-3">
                                <span
                                    class="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px]"
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
                                @click.stop
                            >
                                <div class="flex flex-col items-end gap-1.5">
                                    <div
                                        class="flex flex-col items-end gap-0.5"
                                    >
                                        <span
                                            class="font-[family-name:var(--font-crm-mono)] text-[12.5px]"
                                        >
                                            {{ linha.valorFmt }}
                                        </span>
                                        <span
                                            class="text-muted-foreground text-[11px]"
                                        >
                                            {{ linha.abertas }}
                                        </span>
                                    </div>
                                    <Link
                                        v-if="linha.drawer.negociacoes[0]"
                                        :href="
                                            negociacoesIndex.url(
                                                {},
                                                {
                                                    query: {
                                                        negociacao:
                                                            linha.drawer
                                                                .negociacoes[0]
                                                                .id,
                                                    },
                                                },
                                            )
                                        "
                                        class="border-border bg-card text-primary hover:border-primary inline-block rounded-[7px] border px-[11px] py-1.5 text-[12px]"
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
                                                        empresa_id: linha.id,
                                                    },
                                                },
                                            )
                                        "
                                        class="border-primary bg-primary text-primary-foreground inline-block rounded-[7px] border px-[11px] py-1.5 text-[12px]"
                                    >
                                        Nova
                                    </Link>
                                </div>
                            </td>
                            <td
                                class="border-t border-[#F2EFE8] px-4 py-3 text-right"
                                @click.stop
                            >
                                <Link
                                    :href="
                                        editEmpresa.url({ empresa: linha.id })
                                    "
                                    class="border-border bg-card text-primary hover:border-primary inline-block rounded-[7px] border px-[11px] py-1.5 text-[12px]"
                                >
                                    Editar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                class="text-muted-foreground m-0 max-w-[780px] text-[11.5px] leading-[1.55]"
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
                :empresa-id="empresaAberta.id"
                @close="fecharPainel"
                @open-contato="abrirContato"
            />
            <CrmContactDrawer
                v-else-if="contatoAberto"
                :contato="contatoAberto"
                @close="fecharPainel"
            />
        </Teleport>
    </div>
</template>
