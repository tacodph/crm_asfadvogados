<script setup lang="ts">
import { useDebounceFn, useEventListener } from '@vueuse/core';
import { Search, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { ResultadoBusca } from '@/types/crm';
import NegociacaoBuscaController from '@/actions/App/Http/Controllers/NegociacaoBuscaController';

type Opcao = { id: number; nome: string };

type FunilBusca = {
    id: number;
    nome: string;
    etapas: Opcao[];
};

const props = defineProps<{
    funis: FunilBusca[];
    opcoes: {
        responsaveis: Opcao[];
        statusAtendimentos: Opcao[];
        statusQualificacoes: Opcao[];
    };
}>();

const emit = defineEmits<{
    selecionar: [resultado: ResultadoBusca];
}>();

const campo = ref<HTMLInputElement | null>(null);
const termo = ref('');
const responsavelId = ref<number | ''>('');
const etapaId = ref<number | ''>('');
/** "resultado:aberta" | "atendimento:3" | "qualificacao:5" */
const status = ref('');
const aberto = ref(false);
const carregando = ref(false);
const erro = ref(false);
const resultados = ref<ResultadoBusca[]>([]);
const total = ref(0);

const temFiltro = computed(
    () =>
        responsavelId.value !== '' ||
        etapaId.value !== '' ||
        status.value !== '',
);
const podeBuscar = computed(
    () => termo.value.trim().length >= 2 || temFiltro.value,
);

let ultimaBusca = 0;

async function buscar(): Promise<void> {
    if (!podeBuscar.value) {
        resultados.value = [];
        total.value = 0;

        return;
    }

    const [tipoStatus, valorStatus] = status.value.split(':');
    const query: Record<string, string | number> = {};

    if (termo.value.trim() !== '') {
        query.q = termo.value.trim();
    }

    if (responsavelId.value !== '') {
        query.responsavel_id = responsavelId.value;
    }

    if (etapaId.value !== '') {
        query.etapa_id = etapaId.value;
    }

    if (tipoStatus === 'resultado') {
        query.resultado = valorStatus;
    } else if (tipoStatus === 'atendimento') {
        query.status_atendimento_id = valorStatus;
    } else if (tipoStatus === 'qualificacao') {
        query.status_qualificacao_id = valorStatus;
    }

    const id = ++ultimaBusca;
    carregando.value = true;
    erro.value = false;

    try {
        const resposta = await fetch(NegociacaoBuscaController.url({ query }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!resposta.ok) {
            throw new Error(String(resposta.status));
        }

        const dados = (await resposta.json()) as {
            resultados: ResultadoBusca[];
            total: number;
        };

        // Ignora respostas que chegam fora de ordem.
        if (id === ultimaBusca) {
            resultados.value = dados.resultados;
            total.value = dados.total;
        }
    } catch {
        if (id === ultimaBusca) {
            erro.value = true;
        }
    } finally {
        if (id === ultimaBusca) {
            carregando.value = false;
        }
    }
}

const buscarComAtraso = useDebounceFn(buscar, 300);

watch(termo, () => {
    aberto.value = true;
    buscarComAtraso();
});

watch([responsavelId, etapaId, status], () => {
    aberto.value = true;
    buscar();
});

function selecionar(resultado: ResultadoBusca): void {
    aberto.value = false;
    emit('selecionar', resultado);
}

function limpar(): void {
    termo.value = '';
    responsavelId.value = '';
    etapaId.value = '';
    status.value = '';
    resultados.value = [];
    total.value = 0;
    aberto.value = false;
}

useEventListener(document, 'keydown', (event: KeyboardEvent) => {
    const alvo = event.target as HTMLElement | null;
    const digitando =
        alvo?.tagName === 'INPUT' ||
        alvo?.tagName === 'TEXTAREA' ||
        alvo?.tagName === 'SELECT' ||
        alvo?.isContentEditable;

    if (
        (event.key === 'k' && (event.ctrlKey || event.metaKey)) ||
        (event.key === '/' && !digitando)
    ) {
        event.preventDefault();
        campo.value?.focus();
        aberto.value = true;
    }

    if (event.key === 'Escape') {
        aberto.value = false;
    }
});

const seletorClass =
    'h-9 rounded-md border border-border bg-card px-2 text-[12.5px] text-foreground outline-none focus:border-primary';
</script>

<template>
    <div class="relative flex flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[240px] flex-1">
                <Search
                    class="text-muted-foreground pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2"
                />
                <input
                    ref="campo"
                    v-model="termo"
                    type="search"
                    placeholder="Pesquisar lead por nome, telefone, e-mail ou assunto  ( / )"
                    class="border-border bg-card text-foreground focus:border-primary h-9 w-full rounded-md border pl-8 pr-8 text-[13px] outline-none placeholder:text-slate-400"
                    @focus="aberto = podeBuscar"
                />
                <button
                    v-if="termo || temFiltro"
                    type="button"
                    class="text-muted-foreground absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer border-0 bg-transparent p-0"
                    title="Limpar pesquisa"
                    @click="limpar"
                >
                    <X class="size-4" />
                </button>
            </div>

            <select v-model="responsavelId" :class="seletorClass">
                <option value="">Qualquer responsável</option>
                <option
                    v-for="responsavel in opcoes.responsaveis"
                    :key="responsavel.id"
                    :value="responsavel.id"
                >
                    {{ responsavel.nome }}
                </option>
            </select>

            <select v-model="etapaId" :class="seletorClass">
                <option value="">Qualquer etapa</option>
                <optgroup
                    v-for="funil in props.funis"
                    :key="funil.id"
                    :label="funil.nome"
                >
                    <option
                        v-for="etapa in funil.etapas"
                        :key="etapa.id"
                        :value="etapa.id"
                    >
                        {{ etapa.nome }}
                    </option>
                </optgroup>
            </select>

            <select v-model="status" :class="seletorClass">
                <option value="">Qualquer status</option>
                <optgroup label="Resultado">
                    <option value="resultado:aberta">Em aberto</option>
                    <option value="resultado:ganho">Ganha</option>
                    <option value="resultado:perdido">Perdida</option>
                </optgroup>
                <optgroup
                    v-if="opcoes.statusAtendimentos.length > 0"
                    label="Status de atendimento"
                >
                    <option
                        v-for="item in opcoes.statusAtendimentos"
                        :key="item.id"
                        :value="`atendimento:${item.id}`"
                    >
                        {{ item.nome }}
                    </option>
                </optgroup>
                <optgroup
                    v-if="opcoes.statusQualificacoes.length > 0"
                    label="Qualificação"
                >
                    <option
                        v-for="item in opcoes.statusQualificacoes"
                        :key="item.id"
                        :value="`qualificacao:${item.id}`"
                    >
                        {{ item.nome }}
                    </option>
                </optgroup>
            </select>
        </div>

        <div
            v-if="aberto && podeBuscar"
            class="border-border bg-popover absolute left-0 right-0 top-full z-30 mt-1 max-h-[420px] overflow-y-auto rounded-[10px] border p-1 shadow-lg"
        >
            <p
                v-if="carregando && resultados.length === 0"
                class="text-muted-foreground m-0 px-3 py-3 text-[12.5px]"
            >
                Pesquisando…
            </p>
            <p
                v-else-if="erro"
                class="m-0 px-3 py-3 text-[12.5px] text-[#9B3B2F]"
            >
                Não foi possível pesquisar agora. Tente de novo.
            </p>
            <p
                v-else-if="resultados.length === 0"
                class="text-muted-foreground m-0 px-3 py-3 text-[12.5px]"
            >
                Nenhuma negociação encontrada em nenhum funil.
            </p>
            <template v-else>
                <button
                    v-for="resultado in resultados"
                    :key="resultado.id"
                    type="button"
                    class="dark:hover:bg-sidebar-accent flex w-full cursor-pointer flex-col gap-1 rounded-md border-0 bg-transparent px-3 py-2 text-left hover:bg-slate-50"
                    @click="selecionar(resultado)"
                >
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-foreground text-[13px] font-medium">
                            {{ resultado.nome }}
                        </span>
                        <span
                            class="rounded px-1.5 py-0.5 text-[10.5px] font-medium"
                            :style="{
                                background:
                                    resultado.etapaCorFundo ?? '#333A45',
                                color: resultado.etapaCorTexto ?? '#FBF9F4',
                            }"
                        >
                            {{ resultado.etapaNome }}
                        </span>
                        <span class="text-muted-foreground text-[11px]">
                            {{ resultado.funilNome }}
                        </span>
                    </span>
                    <span class="text-muted-foreground text-[12px]">
                        {{ resultado.assunto }}
                        <template v-if="resultado.telefone">
                            · {{ resultado.telefone }}
                        </template>
                        <template v-if="resultado.responsavel">
                            · {{ resultado.responsavel }}
                        </template>
                        <template v-if="resultado.statusAtendimento">
                            · {{ resultado.statusAtendimento }}
                        </template>
                    </span>
                </button>
                <p
                    v-if="total > resultados.length"
                    class="text-muted-foreground m-0 px-3 py-2 text-[11.5px]"
                >
                    Mostrando {{ resultados.length }} de {{ total }}. Refine a
                    pesquisa para ver os demais.
                </p>
            </template>
        </div>
    </div>
</template>
