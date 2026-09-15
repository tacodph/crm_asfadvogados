<script setup lang="ts">
import { Link, useForm, useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/ContatoController';
import { edit as empresasEdit } from '@/actions/App/Http/Controllers/EmpresaController';
import { useContatoDuplicatas } from '@/composables/useContatoDuplicatas';
import { edit as contatosEdit, index as contatosIndex } from '@/routes/contatos';
import { index as municipiosIndex } from '@/routes/ibge/municipios';

type Opcao = { id: number; nome: string; slug?: string; descricao?: string | null };
type Estado = { id: number; sigla: string; nome: string };
type Municipio = { id: number; nome: string };

type ContatoConsentimento = {
    finalidade_consentimento_id: number;
    finalidade_nome: string;
    finalidade_slug: string;
    status_consentimento_id: number;
    concedido_em: string;
    revogado_em: string;
};

const props = defineProps<{
    defaults: {
        tipo_pessoa_id: number | null;
        canal_contato_id: number | null;
        status_consentimento_id: number | null;
        status_comercial_id: number | null;
        empresa_id: number | null;
        return_to_empresa: boolean;
        consentimentos: ContatoConsentimento[];
    };
    opcoes: {
        tiposPessoa: Opcao[];
        empresas: Opcao[];
        canais: Opcao[];
        statusConsentimentos: Opcao[];
        statusComerciais: Opcao[];
        estados: Estado[];
        municipios: Municipio[];
    };
}>();

const http = useHttp();
const municipios = ref<Municipio[]>([...props.opcoes.municipios]);
const carregandoMunicipios = ref(false);
const empresaTravada = computed(() => props.defaults.return_to_empresa);

const form = useForm({
    nome: '',
    cargo: '',
    email: '',
    telefone: '',
    cpf: '',
    cep: '',
    tipo_pessoa_id: props.defaults.tipo_pessoa_id,
    empresa_id: props.defaults.empresa_id,
    return_to_empresa: props.defaults.return_to_empresa,
    estado_id: null as number | null,
    municipio_id: null as number | null,
    canal_contato_id: props.defaults.canal_contato_id,
    status_consentimento_id: props.defaults.status_consentimento_id,
    status_comercial_id: props.defaults.status_comercial_id,
    consentimentos: props.defaults.consentimentos.map((item) => ({
        finalidade_consentimento_id: item.finalidade_consentimento_id,
        status_consentimento_id: item.status_consentimento_id,
        concedido_em: item.concedido_em,
        revogado_em: item.revogado_em,
    })),
});

const identifiers = computed(() => ({
    email: form.email,
    telefone: form.telefone,
    cpf: form.cpf,
}));

const statusComercialSelecionado = computed(
    () =>
        props.opcoes.statusComerciais.find(
            (status) => status.id === form.status_comercial_id,
        )?.descricao ?? null,
);

const { matches: duplicatas, checking: checandoDuplicatas } =
    useContatoDuplicatas(identifiers);

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2.5 text-[13px] text-foreground outline-none focus:border-primary';

const labelClass =
    'font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase';

const carregarMunicipios = async (estadoId: number | null): Promise<void> => {
    if (!estadoId) {
        municipios.value = [];
        return;
    }

    carregandoMunicipios.value = true;

    try {
        await http.get(municipiosIndex.url({ query: { estado_id: estadoId } }), {
            onSuccess: (response) => {
                const payload = response as { municipios?: Municipio[] };
                municipios.value = payload.municipios ?? [];
            },
            onError: () => {
                municipios.value = [];
            },
        });
    } finally {
        carregandoMunicipios.value = false;
    }
};

watch(
    () => form.estado_id,
    async (estadoId, anterior) => {
        if (estadoId === anterior) {
            return;
        }

        form.municipio_id = null;
        await carregarMunicipios(
            estadoId === null || estadoId === '' ? null : Number(estadoId),
        );
    },
);

const submit = (): void => {
    form
        .transform((data) => ({
            ...data,
            empresa_id:
                data.empresa_id === null ||
                data.empresa_id === '' ||
                data.empresa_id === 0
                    ? null
                    : Number(data.empresa_id),
            return_to_empresa: Boolean(data.return_to_empresa),
            municipio_id:
                data.municipio_id === null ||
                data.municipio_id === '' ||
                data.municipio_id === 0
                    ? null
                    : Number(data.municipio_id),
            tipo_pessoa_id: Number(data.tipo_pessoa_id),
            canal_contato_id: Number(data.canal_contato_id),
            status_consentimento_id: Number(data.status_consentimento_id),
            status_comercial_id: Number(data.status_comercial_id),
            consentimentos: data.consentimentos.map((item) => ({
                finalidade_consentimento_id: Number(
                    item.finalidade_consentimento_id,
                ),
                status_consentimento_id: Number(item.status_consentimento_id),
                concedido_em: item.concedido_em || null,
                revogado_em: item.revogado_em || null,
            })),
        }))
        .post(store.url(), {
            preserveScroll: true,
        });
};
</script>

<template>
    <div class="mx-auto flex max-w-[760px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <Link
                :href="
                    empresaTravada && form.empresa_id
                        ? empresasEdit.url(form.empresa_id)
                        : contatosIndex()
                "
                class="text-[12px] text-muted-foreground hover:text-foreground"
            >
                {{
                    empresaTravada
                        ? '← Voltar para a empresa'
                        : '← Voltar para contatos'
                }}
            </Link>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium tracking-[-0.01em] text-foreground"
            >
                Novo contato
            </h1>
            <p class="m-0 text-[13px] text-muted-foreground">
                Cadastre um contato e defina o consentimento por finalidade.
            </p>
        </div>

        <form
            class="flex flex-col gap-4 rounded-[10px] border border-border bg-card px-5 py-5"
            @submit.prevent="submit"
        >
            <section class="flex flex-col gap-3.5">
                <h2
                    class="m-0 font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-primary uppercase"
                >
                    Dados do contato
                </h2>

                <div class="flex flex-col gap-1.5">
                    <label for="nome" :class="labelClass">Nome</label>
                    <input
                        id="nome"
                        v-model="form.nome"
                        type="text"
                        required
                        :class="fieldClass"
                    />
                    <p v-if="form.errors.nome" class="m-0 text-[12px] text-[#9B3B2F]">
                        {{ form.errors.nome }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="cargo" :class="labelClass">Cargo</label>
                        <input
                            id="cargo"
                            v-model="form.cargo"
                            type="text"
                            :class="fieldClass"
                        />
                        <p
                            v-if="form.errors.cargo"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.cargo }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="cpf" :class="labelClass">CPF</label>
                        <input
                            id="cpf"
                            v-model="form.cpf"
                            type="text"
                            :class="fieldClass"
                        />
                        <p
                            v-if="form.errors.cpf"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.cpf }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="telefone" :class="labelClass">Telefone</label>
                        <input
                            id="telefone"
                            v-model="form.telefone"
                            type="text"
                            :class="fieldClass"
                        />
                        <p
                            v-if="form.errors.telefone"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.telefone }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="email" :class="labelClass">E-mail</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            :class="fieldClass"
                        />
                        <p
                            v-if="form.errors.email"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="estado_id" :class="labelClass">UF</label>
                        <select
                            id="estado_id"
                            v-model="form.estado_id"
                            :class="fieldClass"
                        >
                            <option :value="null">Não informado</option>
                            <option
                                v-for="estado in opcoes.estados"
                                :key="estado.id"
                                :value="estado.id"
                            >
                                {{ estado.sigla }} — {{ estado.nome }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="municipio_id" :class="labelClass"
                            >Município</label
                        >
                        <select
                            id="municipio_id"
                            v-model="form.municipio_id"
                            :disabled="!form.estado_id || carregandoMunicipios"
                            :class="fieldClass"
                        >
                            <option :value="null">
                                {{
                                    carregandoMunicipios
                                        ? 'Carregando…'
                                        : 'Não informado'
                                }}
                            </option>
                            <option
                                v-for="municipio in municipios"
                                :key="municipio.id"
                                :value="municipio.id"
                            >
                                {{ municipio.nome }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.municipio_id"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.municipio_id }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="cep" :class="labelClass">CEP</label>
                        <input
                            id="cep"
                            v-model="form.cep"
                            type="text"
                            maxlength="9"
                            placeholder="00000-000"
                            :class="fieldClass"
                        />
                        <p
                            v-if="form.errors.cep"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.cep }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="duplicatas.length > 0 || checandoDuplicatas"
                    class="rounded-[9px] border border-[#E8D9B8] bg-[#FBF7EF] px-3.5 py-3"
                >
                    <p
                        class="m-0 font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.1em] text-primary uppercase"
                    >
                        Possível duplicata
                    </p>
                    <p
                        v-if="checandoDuplicatas && duplicatas.length === 0"
                        class="mt-1.5 mb-0 text-[12.5px] text-muted-foreground"
                    >
                        Verificando identificadores…
                    </p>
                    <ul
                        v-if="duplicatas.length > 0"
                        class="mt-2 mb-0 flex list-none flex-col gap-2 p-0"
                    >
                        <li
                            v-for="item in duplicatas"
                            :key="`${item.campo}-${item.contato.id}`"
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span class="text-[12.5px] text-muted-foreground">
                                {{ item.mensagem }}
                            </span>
                            <Link
                                :href="contatosEdit.url(item.contato.id)"
                                class="text-[12px] text-primary underline-offset-2 hover:underline"
                            >
                                Abrir existente
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="flex flex-col gap-3.5 border-t border-[#F2EFE8] pt-4">
                <h2
                    class="m-0 font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-primary uppercase"
                >
                    Classificação e vínculos
                </h2>

                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="tipo_pessoa_id" :class="labelClass">
                            Tipo de pessoa
                        </label>
                        <select
                            id="tipo_pessoa_id"
                            v-model.number="form.tipo_pessoa_id"
                            required
                            :class="fieldClass"
                        >
                            <option
                                v-for="tipo in opcoes.tiposPessoa"
                                :key="tipo.id"
                                :value="tipo.id"
                            >
                                {{ tipo.nome }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.tipo_pessoa_id"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.tipo_pessoa_id }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="empresa_id" :class="labelClass">
                            Pertence a uma empresa?
                        </label>
                        <select
                            id="empresa_id"
                            v-model="form.empresa_id"
                            :disabled="empresaTravada"
                            :class="fieldClass"
                        >
                            <option :value="null">Não pertence a empresa</option>
                            <option
                                v-for="empresa in opcoes.empresas"
                                :key="empresa.id"
                                :value="empresa.id"
                            >
                                {{ empresa.nome }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.empresa_id"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.empresa_id }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="canal_contato_id" :class="labelClass">
                            Canal preferido
                        </label>
                        <select
                            id="canal_contato_id"
                            v-model.number="form.canal_contato_id"
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
                        <p
                            v-if="form.errors.canal_contato_id"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.canal_contato_id }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="status_comercial_id" :class="labelClass">
                            Status comercial
                        </label>
                        <select
                            id="status_comercial_id"
                            v-model.number="form.status_comercial_id"
                            required
                            :class="fieldClass"
                        >
                            <option
                                v-for="status in opcoes.statusComerciais"
                                :key="status.id"
                                :value="status.id"
                            >
                                {{ status.nome }}
                            </option>
                        </select>
                        <p
                            v-if="statusComercialSelecionado"
                            class="m-0 text-[12px] text-muted-foreground"
                        >
                            {{ statusComercialSelecionado }}
                        </p>
                        <p
                            v-if="form.errors.status_comercial_id"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.status_comercial_id }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="status_consentimento_id" :class="labelClass">
                            Status de consentimento
                        </label>
                        <select
                            id="status_consentimento_id"
                            v-model.number="form.status_consentimento_id"
                            required
                            :class="fieldClass"
                        >
                            <option
                                v-for="status in opcoes.statusConsentimentos"
                                :key="status.id"
                                :value="status.id"
                            >
                                {{ status.nome }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.status_consentimento_id"
                            class="m-0 text-[12px] text-[#9B3B2F]"
                        >
                            {{ form.errors.status_consentimento_id }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-3.5 border-t border-[#F2EFE8] pt-4">
                <div class="flex flex-col gap-1">
                    <h2
                        class="m-0 font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-primary uppercase"
                    >
                        Finalidades de consentimento
                    </h2>
                    <p class="m-0 text-[12.5px] text-muted-foreground">
                        Defina o status LGPD de cada finalidade cadastrada para
                        este contato.
                    </p>
                </div>

                <div
                    v-for="(item, index) in defaults.consentimentos"
                    :key="item.finalidade_consentimento_id"
                    class="flex flex-col gap-3 rounded-[9px] border border-border bg-card px-3.5 py-3.5"
                >
                    <span class="text-[13px] font-medium text-foreground">
                        {{ item.finalidade_nome }}
                    </span>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="flex flex-col gap-1.5 sm:col-span-1">
                            <label
                                :for="`consent-status-${index}`"
                                :class="labelClass"
                            >
                                Status
                            </label>
                            <select
                                :id="`consent-status-${index}`"
                                v-model.number="
                                    form.consentimentos[index]
                                        .status_consentimento_id
                                "
                                :class="fieldClass"
                            >
                                <option
                                    v-for="status in opcoes.statusConsentimentos"
                                    :key="status.id"
                                    :value="status.id"
                                >
                                    {{ status.nome }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                :for="`consent-concedido-${index}`"
                                :class="labelClass"
                            >
                                Concedido em
                            </label>
                            <input
                                :id="`consent-concedido-${index}`"
                                v-model="
                                    form.consentimentos[index].concedido_em
                                "
                                type="date"
                                :class="fieldClass"
                            />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                :for="`consent-revogado-${index}`"
                                :class="labelClass"
                            >
                                Revogado em
                            </label>
                            <input
                                :id="`consent-revogado-${index}`"
                                v-model="form.consentimentos[index].revogado_em"
                                type="date"
                                :class="fieldClass"
                            />
                        </div>
                    </div>
                </div>

                <p
                    v-if="form.errors.consentimentos"
                    class="m-0 text-[12px] text-[#9B3B2F]"
                >
                    {{ form.errors.consentimentos }}
                </p>
            </section>

            <div
                class="flex flex-wrap items-center gap-2.5 border-t border-[#F2EFE8] pt-4"
            >
                <button
                    type="submit"
                    class="cursor-pointer rounded-[7px] border border-primary bg-primary px-[15px] py-[8px] text-[12.5px] text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Salvando…' : 'Cadastrar contato' }}
                </button>
                <Link
                    :href="contatosIndex()"
                    class="cursor-pointer rounded-[7px] border border-border bg-card px-[15px] py-[8px] text-[12.5px] text-muted-foreground"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
