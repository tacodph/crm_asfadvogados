<script setup lang="ts">
import { Link, useForm, useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/EmpresaController';
import { index as empresasIndex } from '@/routes/empresas';
import { index as municipiosIndex } from '@/routes/ibge/municipios';

type Opcao = { id: number; nome: string; descricao?: string | null };
type Estado = { id: number; sigla: string; nome: string };
type Municipio = { id: number; nome: string };

const props = defineProps<{
    defaults: {
        setor_id: number | null;
        status_conflito_id: number | null;
        status_comercial_id: number | null;
        responsavel_user_id: number | null;
    };
    opcoes: {
        setores: Opcao[];
        statusConflitos: Opcao[];
        statusComerciais: Opcao[];
        responsaveis: Opcao[];
        estados: Estado[];
        municipios: Municipio[];
    };
}>();

const http = useHttp();
const municipios = ref<Municipio[]>([...props.opcoes.municipios]);
const carregandoMunicipios = ref(false);

const form = useForm({
    nome: '',
    cnpj: '',
    setor_id: props.defaults.setor_id,
    porte: '',
    estado_id: null as number | null,
    municipio_id: null as number | null,
    status_conflito_id: props.defaults.status_conflito_id,
    status_comercial_id: props.defaults.status_comercial_id,
    conflito_texto: '',
    responsavel_user_id: props.defaults.responsavel_user_id,
});

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2.5 text-[13px] text-foreground outline-none focus:border-primary';

const labelClass =
    'font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase';

const statusComercialSelecionado = computed(
    () =>
        props.opcoes.statusComerciais.find(
            (status) => status.id === form.status_comercial_id,
        )?.descricao ?? null,
);

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
            nome: data.nome,
            cnpj: data.cnpj,
            setor_id: Number(data.setor_id),
            porte: data.porte,
            municipio_id: Number(data.municipio_id),
            status_conflito_id: Number(data.status_conflito_id),
            status_comercial_id: Number(data.status_comercial_id),
            conflito_texto: data.conflito_texto || null,
            responsavel_user_id:
                data.responsavel_user_id === null ||
                data.responsavel_user_id === '' ||
                data.responsavel_user_id === 0
                    ? null
                    : Number(data.responsavel_user_id),
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
                :href="empresasIndex()"
                class="text-[12px] text-muted-foreground hover:text-foreground"
            >
                ← Voltar para empresas
            </Link>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium tracking-[-0.01em] text-foreground"
            >
                Nova empresa
            </h1>
            <p class="m-0 text-[13px] text-muted-foreground">
                Cadastre a empresa e a localização IBGE.
            </p>
        </div>

        <form
            class="flex flex-col gap-4 rounded-[10px] border border-border bg-card px-5 py-5"
            @submit.prevent="submit"
        >
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label :class="labelClass">Nome</label>
                    <input
                        v-model="form.nome"
                        type="text"
                        required
                        :class="fieldClass"
                    />
                    <p
                        v-if="form.errors.nome"
                        class="m-0 text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.nome }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label :class="labelClass">CNPJ</label>
                    <input
                        v-model="form.cnpj"
                        type="text"
                        required
                        :class="fieldClass"
                    />
                    <p
                        v-if="form.errors.cnpj"
                        class="m-0 text-[12px] text-[#9B3B2F]"
                    >
                        {{ form.errors.cnpj }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label :class="labelClass">Porte</label>
                    <input
                        v-model="form.porte"
                        type="text"
                        required
                        :class="fieldClass"
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label :class="labelClass">Setor</label>
                    <select
                        v-model="form.setor_id"
                        required
                        :class="fieldClass"
                    >
                        <option
                            v-for="setor in opcoes.setores"
                            :key="setor.id"
                            :value="setor.id"
                        >
                            {{ setor.nome }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label :class="labelClass">Responsável</label>
                    <select
                        v-model="form.responsavel_user_id"
                        :class="fieldClass"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="user in opcoes.responsaveis"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.nome }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label :class="labelClass">UF</label>
                    <select
                        v-model="form.estado_id"
                        required
                        :class="fieldClass"
                    >
                        <option :value="null" disabled>Selecione a UF</option>
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
                    <label :class="labelClass">Município</label>
                    <select
                        v-model="form.municipio_id"
                        required
                        :disabled="!form.estado_id || carregandoMunicipios"
                        :class="fieldClass"
                    >
                        <option :value="null" disabled>
                            {{
                                carregandoMunicipios
                                    ? 'Carregando…'
                                    : 'Selecione o município'
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

                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label :class="labelClass">Status comercial</label>
                    <select
                        v-model="form.status_comercial_id"
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
                </div>

                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label :class="labelClass">Status de conflito</label>
                    <select
                        v-model="form.status_conflito_id"
                        required
                        :class="fieldClass"
                    >
                        <option
                            v-for="status in opcoes.statusConflitos"
                            :key="status.id"
                            :value="status.id"
                        >
                            {{ status.nome }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5 sm:col-span-2">
                    <label :class="labelClass">Texto do conflito</label>
                    <textarea
                        v-model="form.conflito_texto"
                        rows="3"
                        :class="fieldClass"
                    />
                </div>
            </div>

            <div class="flex gap-2 pt-1">
                <button
                    type="submit"
                    class="cursor-pointer rounded-[7px] border border-primary bg-primary px-[13px] py-[7px] text-[12.5px] text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing || !form.municipio_id"
                >
                    {{ form.processing ? 'Salvando…' : 'Cadastrar empresa' }}
                </button>
                <Link
                    :href="empresasIndex()"
                    class="rounded-[7px] border border-border bg-card px-[13px] py-[7px] text-[12.5px] text-muted-foreground"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
