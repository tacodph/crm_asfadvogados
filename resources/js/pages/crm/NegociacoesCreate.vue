<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/NegociacaoController';
import { index as negociacoesIndex } from '@/routes/negociacoes';

type Opcao = { id: number; nome: string };
type ContatoOpcao = Opcao & {
    empresa_id: number | null;
    canal_contato_id: number;
};
type FunilOpcao = Opcao & { etapas: Opcao[]; distribuicao: string };

const props = defineProps<{
    defaults: {
        funil_id: number | null;
        etapa_funil_id: number | null;
        contato_id: number | null;
        empresa_id: number | null;
        canal_contato_id: number | null;
        responsavel_user_id: number | null;
        assunto: string;
        valor: string;
        previsao_fechamento: string;
        proxima_tarefa: string;
        proxima_tarefa_em: string;
        proxima_tarefa_hora: string;
        utm_source: string;
        utm_medium: string;
        utm_campaign: string;
        utm_content: string;
        utm_term: string;
        fbclid: string;
        meta_ad_id: string;
    };
    opcoes: {
        funis: FunilOpcao[];
        contatos: ContatoOpcao[];
        empresas: Opcao[];
        canais: Opcao[];
        responsaveis: Opcao[];
        regrasDistribuicao: string[];
    };
    origem: {
        contato_id: number | null;
        empresa_id: number | null;
        contato_nome: string | null;
        empresa_nome: string | null;
    };
}>();

const form = useForm({
    funil_id: props.defaults.funil_id,
    etapa_funil_id: props.defaults.etapa_funil_id,
    contato_id: props.defaults.contato_id,
    empresa_id: props.defaults.empresa_id,
    canal_contato_id: props.defaults.canal_contato_id,
    responsavel_user_id: props.defaults.responsavel_user_id,
    assunto: props.defaults.assunto,
    valor: props.defaults.valor,
    previsao_fechamento: props.defaults.previsao_fechamento,
    proxima_tarefa: props.defaults.proxima_tarefa,
    proxima_tarefa_em: props.defaults.proxima_tarefa_em,
    proxima_tarefa_hora: props.defaults.proxima_tarefa_hora,
    utm_source: props.defaults.utm_source,
    utm_medium: props.defaults.utm_medium,
    utm_campaign: props.defaults.utm_campaign,
    utm_content: props.defaults.utm_content,
    utm_term: props.defaults.utm_term,
    fbclid: props.defaults.fbclid,
    meta_ad_id: props.defaults.meta_ad_id,
});

// Abre já expandido quando o link do anúncio pré-preencheu algo.
const origemExpandida = ref(
    Boolean(
        props.defaults.utm_campaign ||
            props.defaults.utm_content ||
            props.defaults.utm_source ||
            props.defaults.fbclid ||
            props.defaults.meta_ad_id,
    ),
);

const camposOrigem = [
    { key: 'utm_source', label: 'utm_source', hint: 'ex.: facebook, instagram' },
    { key: 'utm_medium', label: 'utm_medium', hint: 'ex.: paid, cpc' },
    { key: 'utm_campaign', label: 'utm_campaign', hint: 'nome ou id da campanha' },
    { key: 'utm_content', label: 'utm_content', hint: 'id do anúncio ({{ad.id}})' },
    { key: 'utm_term', label: 'utm_term', hint: 'palavra-chave / público' },
    { key: 'fbclid', label: 'fbclid', hint: 'parâmetro do clique da Meta' },
    { key: 'meta_ad_id', label: 'ID do anúncio', hint: 'alternativa direta ao utm_content' },
] as const;

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

const tituloOrigem = computed(() => {
    if (props.origem.contato_nome && props.origem.empresa_nome) {
        return `${props.origem.contato_nome} · ${props.origem.empresa_nome}`;
    }

    return props.origem.contato_nome ?? props.origem.empresa_nome ?? null;
});

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
            proxima_tarefa: data.proxima_tarefa || null,
            proxima_tarefa_em: data.proxima_tarefa_em || null,
            proxima_tarefa_hora: data.proxima_tarefa_hora || null,
        }))
        .post(store.url({}), {
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
                Nova negociação
            </h1>
            <p
                v-if="tituloOrigem"
                class="m-0 text-[13px] text-muted-foreground"
            >
                Vinculada a {{ tituloOrigem }}
            </p>
        </div>

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
                        :disabled="origem.contato_id !== null"
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
                    <select
                        v-model="form.empresa_id"
                        :class="fieldClass"
                        :disabled="origem.empresa_id !== null"
                    >
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
                    <span
                        v-if="form.errors.empresa_id"
                        class="text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.empresa_id }}
                    </span>
                </label>
            </div>

            <label class="flex flex-col gap-1.5">
                <span :class="labelClass">Próxima tarefa</span>
                <input
                    v-model="form.proxima_tarefa"
                    type="text"
                    maxlength="255"
                    :class="fieldClass"
                />
            </label>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Data da tarefa</span>
                    <input
                        v-model="form.proxima_tarefa_em"
                        type="date"
                        :class="fieldClass"
                    />
                </label>

                <label class="flex flex-col gap-1.5">
                    <span :class="labelClass">Hora</span>
                    <input
                        v-model="form.proxima_tarefa_hora"
                        type="time"
                        :class="fieldClass"
                    />
                </label>
            </div>

            <div class="rounded-lg border border-border">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-3 py-2.5 text-left"
                    @click="origemExpandida = !origemExpandida"
                >
                    <span :class="labelClass">Origem / tráfego pago</span>
                    <span class="text-[12px] text-muted-foreground">
                        {{ origemExpandida ? '−' : '+' }}
                    </span>
                </button>
                <div
                    v-if="origemExpandida"
                    class="flex flex-col gap-3 border-t border-border px-3 py-3"
                >
                    <p class="m-0 text-[11.5px] text-muted-foreground">
                        Preencha ao lançar um lead que veio de anúncio. Cole os
                        parâmetros do link do anúncio — a atribuição de campanha
                        e o custo por lead usam esses campos.
                    </p>
                    <label
                        v-for="campo in camposOrigem"
                        :key="campo.key"
                        class="flex flex-col gap-1.5"
                    >
                        <span :class="labelClass">{{ campo.label }}</span>
                        <input
                            v-model="form[campo.key]"
                            type="text"
                            maxlength="512"
                            :placeholder="campo.hint"
                            :class="fieldClass"
                        />
                    </label>
                </div>
            </div>

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
                    Criar negociação
                </button>
            </div>
        </form>
    </div>
</template>
