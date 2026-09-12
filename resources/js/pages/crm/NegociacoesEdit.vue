<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { update } from '@/actions/App/Http/Controllers/NegociacaoController';
import NegociacaoTarefaController from '@/actions/App/Http/Controllers/NegociacaoTarefaController';
import CrmFieldCards from '@/components/crm/CrmFieldCards.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as negociacoesIndex } from '@/routes/negociacoes';
import type { DrawerField } from '@/types/crm';

type Opcao = { id: number; nome: string };
type ContatoOpcao = Opcao & {
    empresa_id: number | null;
    canal_contato_id: number;
};
type FunilOpcao = Opcao & { etapas: Opcao[]; distribuicao: string };
type StatusTarefaOpcao = { value: string; label: string };

type TarefaItem = {
    id: number;
    descricao: string;
    data: string;
    hora: string;
    status: string;
    statusLabel: string;
};

type HistoricoItem = {
    id: number;
    titulo: string;
    descricao: string;
    quando: string;
    autor: string;
    cor: string;
};

type NegociacaoEdit = {
    id: number;
    funil_id: number;
    etapa_funil_id: number;
    contato_id: number;
    empresa_id: number | null;
    canal_contato_id: number;
    status_atendimento_id: number | null;
    status_qualificacao_id: number | null;
    motivo_desqualificacao: string;
    continuidade_atendimento: string;
    observacoes_complementares: string;
    responsavel_user_id: number | null;
    assunto: string;
    valor: string;
    previsao_fechamento: string;
    tarefas: TarefaItem[];
    historicos: HistoricoItem[];
};

type NegociacaoResumo = {
    nome: string;
    subtitulo: string;
    canal: string;
    canalCor: string;
    responsavel: string;
    consent: string;
    consentBg: string;
    consentCor: string;
    campos: DrawerField[];
};

const props = defineProps<{
    negociacao: NegociacaoEdit;
    resumo: NegociacaoResumo;
    opcoes: {
        funis: FunilOpcao[];
        contatos: ContatoOpcao[];
        empresas: Opcao[];
        canais: Opcao[];
        responsaveis: Opcao[];
        statusAtendimentos: Opcao[];
        statusQualificacoes: Opcao[];
        regrasDistribuicao: string[];
        statusTarefa: StatusTarefaOpcao[];
    };
}>();

const form = useForm({
    funil_id: props.negociacao.funil_id,
    etapa_funil_id: props.negociacao.etapa_funil_id,
    contato_id: props.negociacao.contato_id,
    empresa_id: props.negociacao.empresa_id,
    canal_contato_id: props.negociacao.canal_contato_id,
    status_atendimento_id: props.negociacao.status_atendimento_id,
    status_qualificacao_id: props.negociacao.status_qualificacao_id,
    motivo_desqualificacao: props.negociacao.motivo_desqualificacao,
    continuidade_atendimento: props.negociacao.continuidade_atendimento,
    observacoes_complementares: props.negociacao.observacoes_complementares,
    responsavel_user_id: props.negociacao.responsavel_user_id,
    assunto: props.negociacao.assunto,
    valor: props.negociacao.valor,
    previsao_fechamento: props.negociacao.previsao_fechamento,
});

const modalAberto = ref(false);
const tarefaEditando = ref<TarefaItem | null>(null);

const tarefaForm = useForm({
    descricao: '',
    data: '',
    hora: '',
    status: 'pendente',
});

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2.5 text-[13px] text-foreground outline-none focus:border-primary';

const labelClass =
    'font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase';

const etapasDoFunil = computed(() => {
    const funil = props.opcoes.funis.find(
        (item) => item.id === Number(form.funil_id),
    );

    return funil?.etapas ?? [];
});

const regraDistribuicao = computed(() => {
    const funil = props.opcoes.funis.find(
        (item) => item.id === Number(form.funil_id),
    );

    return funil?.distribuicao ?? '—';
});

const tituloModal = computed(() =>
    tarefaEditando.value ? 'Editar tarefa' : 'Nova tarefa',
);

watch(
    () => form.funil_id,
    (funilId) => {
        const etapas =
            props.opcoes.funis.find((item) => item.id === Number(funilId))
                ?.etapas ?? [];
        const etapaAtual = etapas.find(
            (etapa) => etapa.id === Number(form.etapa_funil_id),
        );

        if (!etapaAtual) {
            form.etapa_funil_id = etapas[0]?.id ?? null;
        }
    },
);

watch(
    () => form.contato_id,
    (contatoId) => {
        const contato = props.opcoes.contatos.find(
            (item) => item.id === Number(contatoId),
        );

        if (!contato) {
            return;
        }

        if (contato.empresa_id) {
            form.empresa_id = contato.empresa_id;
        }

        form.canal_contato_id = contato.canal_contato_id;
    },
);

function formatarData(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}

function abrirNovaTarefa(): void {
    tarefaEditando.value = null;
    tarefaForm.reset();
    tarefaForm.clearErrors();
    tarefaForm.descricao = '';
    tarefaForm.data = new Date().toISOString().slice(0, 10);
    tarefaForm.hora = '09:00';
    tarefaForm.status = 'pendente';
    modalAberto.value = true;
}

function abrirEditarTarefa(tarefa: TarefaItem): void {
    tarefaEditando.value = tarefa;
    tarefaForm.clearErrors();
    tarefaForm.descricao = tarefa.descricao;
    tarefaForm.data = tarefa.data;
    tarefaForm.hora = tarefa.hora;
    tarefaForm.status = tarefa.status;
    modalAberto.value = true;
}

function fecharModal(): void {
    modalAberto.value = false;
    tarefaEditando.value = null;
    tarefaForm.reset();
    tarefaForm.clearErrors();
}

function salvarTarefa(): void {
    const payload = {
        preserveScroll: true,
        onSuccess: () => fecharModal(),
    };

    if (tarefaEditando.value) {
        tarefaForm.patch(
            NegociacaoTarefaController.update.url({
                negociacao: props.negociacao.id,
                tarefa: tarefaEditando.value.id,
            }),
            payload,
        );

        return;
    }

    tarefaForm.post(
        NegociacaoTarefaController.store.url({
            negociacao: props.negociacao.id,
        }),
        payload,
    );
}

function excluirTarefa(tarefa: TarefaItem): void {
    if (!confirm('Remover esta tarefa?')) {
        return;
    }

    router.delete(
        NegociacaoTarefaController.destroy.url({
            negociacao: props.negociacao.id,
            tarefa: tarefa.id,
        }),
        { preserveScroll: true },
    );
}

const submit = (): void => {
    form
        .transform((data) => ({
            ...data,
            funil_id: Number(data.funil_id),
            etapa_funil_id: Number(data.etapa_funil_id),
            contato_id: Number(data.contato_id),
            empresa_id:
                data.empresa_id === null ||
                data.empresa_id === '' ||
                data.empresa_id === 0
                    ? null
                    : Number(data.empresa_id),
            canal_contato_id: Number(data.canal_contato_id),
            responsavel_user_id:
                data.responsavel_user_id === null ||
                data.responsavel_user_id === '' ||
                data.responsavel_user_id === 0
                    ? null
                    : Number(data.responsavel_user_id),
            valor: Number(data.valor),
            previsao_fechamento: data.previsao_fechamento || null,
        }))
        .patch(update.url({ negociacao: props.negociacao.id }), {
            preserveScroll: true,
        });
};
</script>

<template>
    <div class="mx-auto flex max-w-[720px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <Link
                :href="negociacoesIndex()"
                class="text-[12px] text-muted-foreground hover:text-foreground"
            >
                ← Funil
            </Link>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium text-foreground"
            >
                {{ resumo.subtitulo }}
            </h1>
            <p class="m-0 text-[13px] text-muted-foreground">
                {{ resumo.nome }}
            </p>
        </div>

        <section
            class="flex flex-col gap-[18px] rounded-[10px] border border-border bg-card px-5 py-5"
        >
            <div class="flex flex-wrap gap-[7px]">
                <span
                    class="inline-flex items-center gap-[5px] rounded-full bg-muted px-[9px] py-1 text-[11px] text-muted-foreground"
                >
                    <span
                        class="h-[5px] w-[5px] rounded-full"
                        :style="{ background: resumo.canalCor }"
                    />
                    {{ resumo.canal }}
                </span>
                <span
                    class="rounded-full bg-muted px-[9px] py-1 text-[11px] text-muted-foreground"
                >
                    Resp. {{ resumo.responsavel }}
                </span>
            </div>

            <CrmFieldCards :campos="resumo.campos" />

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
                    <strong class="font-medium">{{ resumo.responsavel }}</strong>
                    e à sócia responsável. Este registro comercial guarda apenas
                    objeto genérico, canal e histórico de contato — nenhum
                    documento ou detalhe fático do caso.
                </p>
            </div>
        </section>

        <form
            class="flex flex-col gap-3.5 rounded-[10px] border border-border bg-card px-5 py-5"
            @submit.prevent="submit"
        >
            <div class="grid gap-3.5 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Funil</span>
                    <select
                        v-model="form.funil_id"
                        required
                        :class="fieldClass"
                    >
                        <option
                            v-for="funil in opcoes.funis"
                            :key="funil.id"
                            :value="funil.id"
                        >
                            {{ funil.nome }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.funil_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.funil_id }}
                    </span>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Etapa</span>
                    <select
                        v-model="form.etapa_funil_id"
                        required
                        :class="fieldClass"
                    >
                        <option
                            v-for="etapa in etapasDoFunil"
                            :key="etapa.id"
                            :value="etapa.id"
                        >
                            {{ etapa.nome }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.etapa_funil_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.etapa_funil_id }}
                    </span>
                </label>
            </div>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Contato</span>
                    <select
                        v-model="form.contato_id"
                        required
                        :class="fieldClass"
                    >
                        <option :value="null" disabled>Selecione…</option>
                        <option
                            v-for="contato in opcoes.contatos"
                            :key="contato.id"
                            :value="contato.id"
                        >
                            {{ contato.nome }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.contato_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.contato_id }}
                    </span>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Empresa</span>
                    <select v-model="form.empresa_id" :class="fieldClass">
                        <option :value="null">Nenhuma</option>
                        <option
                            v-for="empresa in opcoes.empresas"
                            :key="empresa.id"
                            :value="empresa.id"
                        >
                            {{ empresa.nome }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.empresa_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.empresa_id }}
                    </span>
                </label>
            </div>

            <label class="flex flex-col gap-1.5">
                <span :class="labelClass">Assunto</span>
                <input
                    v-model="form.assunto"
                    type="text"
                    required
                    maxlength="255"
                    :class="fieldClass"
                />
                <span
                    v-if="form.errors.assunto"
                    class="text-[12px] text-[#9B3B2F]"
                >
                    {{ form.errors.assunto }}
                </span>
            </label>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Valor</span>
                    <input
                        v-model="form.valor"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                        :class="fieldClass"
                    />
                    <span
                        v-if="form.errors.valor"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.valor }}
                    </span>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Previsão de fechamento</span>
                    <input
                        v-model="form.previsao_fechamento"
                        type="date"
                        :class="fieldClass"
                    />
                </label>
            </div>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Canal</span>
                    <select
                        v-model="form.canal_contato_id"
                        required
                        :class="fieldClass"
                    >
                        <option
                            v-for="canal in opcoes.canais"
                            :key="canal.id"
                            :value="canal.id"
                        >
                            {{ canal.nome }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Responsável</span>
                    <select
                        v-model="form.responsavel_user_id"
                        :class="fieldClass"
                    >
                        <option :value="null">
                            Automático ({{ regraDistribuicao }})
                        </option>
                        <option
                            v-for="user in opcoes.responsaveis"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.nome }}
                        </option>
                    </select>
                    <span class="text-[11.5px] text-muted-foreground">
                        Em automático, o funil usa a regra de distribuição
                        configurada. Ausentes e conflito pendente são
                        respeitados.
                    </span>
                    <span
                        v-if="form.errors.responsavel_user_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.responsavel_user_id }}
                    </span>
                </label>
            </div>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Status do atendimento</span>
                    <select
                        v-model="form.status_atendimento_id"
                        :class="fieldClass"
                    >
                        <option :value="null">Não definido</option>
                        <option
                            v-for="status in opcoes.statusAtendimentos"
                            :key="status.id"
                            :value="status.id"
                        >
                            {{ status.nome }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.status_atendimento_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.status_atendimento_id }}
                    </span>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Status da qualificação</span>
                    <select
                        v-model="form.status_qualificacao_id"
                        :class="fieldClass"
                    >
                        <option :value="null">Não definido</option>
                        <option
                            v-for="status in opcoes.statusQualificacoes"
                            :key="status.id"
                            :value="status.id"
                        >
                            {{ status.nome }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.status_qualificacao_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.status_qualificacao_id }}
                    </span>
                </label>
            </div>

            <label class="flex flex-col gap-1.5">
                <span :class="labelClass">Motivo de desqualificação</span>
                <textarea
                    v-model="form.motivo_desqualificacao"
                    rows="2"
                    maxlength="2000"
                    :class="fieldClass"
                />
                <span
                    v-if="form.errors.motivo_desqualificacao"
                    class="text-[12px] text-[#9B3B2F]"
                >
                    {{ form.errors.motivo_desqualificacao }}
                </span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span :class="labelClass">Continuidade do atendimento</span>
                <textarea
                    v-model="form.continuidade_atendimento"
                    rows="2"
                    maxlength="2000"
                    :class="fieldClass"
                />
                <span
                    v-if="form.errors.continuidade_atendimento"
                    class="text-[12px] text-[#9B3B2F]"
                >
                    {{ form.errors.continuidade_atendimento }}
                </span>
            </label>

            <label class="flex flex-col gap-1.5">
                <span :class="labelClass">Observações complementares</span>
                <textarea
                    v-model="form.observacoes_complementares"
                    rows="3"
                    maxlength="5000"
                    :class="fieldClass"
                />
                <span
                    v-if="form.errors.observacoes_complementares"
                    class="text-[12px] text-[#9B3B2F]"
                >
                    {{ form.errors.observacoes_complementares }}
                </span>
            </label>

            <div class="flex gap-2.5 pt-1">
                <Link
                    :href="negociacoesIndex()"
                    class="flex-1 cursor-pointer rounded-lg border border-border bg-card py-2.5 text-center text-[13px] text-muted-foreground"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    class="flex-1 cursor-pointer rounded-lg border border-primary bg-primary py-2.5 text-[13px] font-medium text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Salvar alterações
                </button>
            </div>
        </form>

        <section
            class="flex flex-col gap-3 rounded-[10px] border border-border bg-card px-5 py-5"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="flex flex-col gap-0.5">
                    <h2
                        class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium text-foreground"
                    >
                        Tarefas
                    </h2>
                    <p class="m-0 text-[12px] text-muted-foreground">
                        Cadastre descrições, datas, horários e status.
                    </p>
                </div>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-primary bg-primary px-3 py-2 text-[12.5px] font-medium text-primary-foreground"
                    @click="abrirNovaTarefa"
                >
                    Nova tarefa
                </button>
            </div>

            <p
                v-if="negociacao.tarefas.length === 0"
                class="m-0 text-[13px] text-muted-foreground"
            >
                Nenhuma tarefa cadastrada ainda.
            </p>

            <ul
                v-else
                class="m-0 flex list-none flex-col gap-2 p-0"
            >
                <li
                    v-for="tarefa in negociacao.tarefas"
                    :key="tarefa.id"
                    class="flex flex-col gap-2 rounded-lg border border-border px-3.5 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-[13px] font-medium text-foreground">{{
                            tarefa.descricao
                        }}</span>
                        <span class="text-[12px] text-muted-foreground">
                            {{ formatarData(tarefa.data)
                            }}{{ tarefa.hora ? ` · ${tarefa.hora}` : '' }}
                            · {{ tarefa.statusLabel }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-border bg-card px-3 py-1.5 text-[12px] text-muted-foreground"
                            @click="abrirEditarTarefa(tarefa)"
                        >
                            Editar
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-border bg-card px-3 py-1.5 text-[12px] text-[#9B3B2F]"
                            @click="excluirTarefa(tarefa)"
                        >
                            Remover
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <section
            class="flex flex-col gap-3 rounded-[10px] border border-border bg-card px-5 py-5"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="flex flex-col gap-0.5">
                    <h2
                        class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium text-foreground"
                    >
                        Histórico unificado
                    </h2>
                    <p class="m-0 text-[12px] text-muted-foreground">
                        Interações e alterações registradas nesta negociação.
                    </p>
                </div>
                <span class="text-[11.5px] text-muted-foreground">
                    {{ negociacao.historicos.length }}
                    {{
                        negociacao.historicos.length === 1
                            ? 'interação'
                            : 'interações'
                    }}
                </span>
            </div>

            <p
                v-if="negociacao.historicos.length === 0"
                class="m-0 text-[13px] text-muted-foreground"
            >
                Nenhuma interação registrada ainda.
            </p>

            <div
                v-else
                class="flex flex-col"
            >
                <div
                    v-for="item in negociacao.historicos"
                    :key="item.id"
                    class="grid grid-cols-[14px_1fr] gap-3 pb-[15px] last:pb-0"
                >
                    <div class="flex flex-col items-center gap-1">
                        <span
                            class="mt-1 h-[9px] w-[9px] shrink-0 rounded-full"
                            :style="{ background: item.cor }"
                        />
                        <span class="w-px flex-1 bg-border" />
                    </div>
                    <div class="flex min-w-0 flex-col gap-[3px]">
                        <div
                            class="flex items-baseline justify-between gap-2.5"
                        >
                            <span
                                class="text-[12.5px] font-medium text-foreground"
                            >
                                {{ item.titulo }}
                            </span>
                            <span
                                class="shrink-0 font-[family-name:var(--font-crm-mono)] text-[11px] text-muted-foreground"
                            >
                                {{ item.quando }}
                            </span>
                        </div>
                        <span
                            class="whitespace-pre-line text-[12.5px] leading-normal text-muted-foreground"
                        >
                            {{ item.descricao }}
                        </span>
                        <span class="text-[11px] text-muted-foreground">
                            {{ item.autor }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <Dialog
            :open="modalAberto"
            @update:open="(open) => (!open ? fecharModal() : (modalAberto = open))"
        >
            <DialogContent class="max-w-md border-border bg-[var(--primary-foreground)] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        class="font-[family-name:var(--font-crm-display)] text-foreground"
                    >
                        {{ tituloModal }}
                    </DialogTitle>
                    <DialogDescription class="text-muted-foreground">
                        Informe descrição, data, hora e status da tarefa.
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="flex flex-col gap-3.5"
                    @submit.prevent="salvarTarefa"
                >
                    <label class="flex flex-col gap-1.5">
                        <span :class="labelClass">Descrição</span>
                        <input
                            v-model="tarefaForm.descricao"
                            type="text"
                            required
                            maxlength="255"
                            :class="fieldClass"
                        />
                        <span
                            v-if="tarefaForm.errors.descricao"
                            class="text-[12px] text-[#9B3B2F]"
                        >
                            {{ tarefaForm.errors.descricao }}
                        </span>
                    </label>

                    <div class="grid gap-3.5 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5">
                            <span :class="labelClass">Data</span>
                            <input
                                v-model="tarefaForm.data"
                                type="date"
                                required
                                :class="fieldClass"
                            />
                            <span
                                v-if="tarefaForm.errors.data"
                                class="text-[12px] text-[#9B3B2F]"
                            >
                                {{ tarefaForm.errors.data }}
                            </span>
                        </label>

                        <label class="flex flex-col gap-1.5">
                            <span :class="labelClass">Hora</span>
                            <input
                                v-model="tarefaForm.hora"
                                type="time"
                                :class="fieldClass"
                            />
                            <span
                                v-if="tarefaForm.errors.hora"
                                class="text-[12px] text-[#9B3B2F]"
                            >
                                {{ tarefaForm.errors.hora }}
                            </span>
                        </label>
                    </div>

                    <label class="flex flex-col gap-1.5">
                        <span :class="labelClass">Status</span>
                        <select
                            v-model="tarefaForm.status"
                            required
                            :class="fieldClass"
                        >
                            <option
                                v-for="status in opcoes.statusTarefa"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                        <span
                            v-if="tarefaForm.errors.status"
                            class="text-[12px] text-[#9B3B2F]"
                        >
                            {{ tarefaForm.errors.status }}
                        </span>
                    </label>

                    <DialogFooter class="gap-2 sm:justify-end">
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-border bg-card px-4 py-2 text-[13px] text-muted-foreground"
                            @click="fecharModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cursor-pointer rounded-lg border border-primary bg-primary px-4 py-2 text-[13px] font-medium text-primary-foreground disabled:opacity-60"
                            :disabled="tarefaForm.processing"
                        >
                            {{
                                tarefaEditando ? 'Salvar tarefa' : 'Cadastrar tarefa'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
