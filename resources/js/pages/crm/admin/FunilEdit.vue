<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    destroy as destroyEtapa,
    edit as editEtapa,
    store as storeEtapa,
} from '@/actions/App/Http/Controllers/Admin/EtapaFunilController';
import { update as updateFunil } from '@/actions/App/Http/Controllers/Admin/FunilController';
import { index as funisIndex } from '@/routes/admin/funis';
import { index as negociacoesIndex } from '@/routes/negociacoes';

type Etapa = {
    id: number;
    nome: string;
    sla: string;
    campos: string[];
    exige_motivo: boolean;
    ordem: number;
    cor_fundo: string;
    cor_texto: string;
    cor_suave: string;
};

const props = defineProps<{
    funil: {
        id: number;
        slug: string;
        nome: string;
        distribuicao: string;
        ordem: number;
    };
    etapas: Etapa[];
}>();

const funilForm = useForm({
    nome: props.funil.nome,
    slug: props.funil.slug,
    distribuicao: props.funil.distribuicao,
    ordem: props.funil.ordem,
});

const etapaForm = useForm({
    nome: '',
    sla: '1d',
    campos: '',
    exige_motivo: false,
    ordem: 0,
    cor_fundo: '#14574F',
    cor_texto: '#FBF9F4',
    cor_suave: 'rgba(251,249,244,0.9)',
});

const maxOrdemEtapa = computed((): number => props.etapas.length);

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2.5 text-[13px] text-foreground outline-none focus:border-primary';

const saveFunil = (): void => {
    funilForm.patch(updateFunil.url({ funil: props.funil.id }), {
        preserveScroll: true,
    });
};

const addEtapa = (): void => {
    etapaForm
        .transform((data) => ({
            ...data,
            campos: data.campos,
            exige_motivo: Boolean(data.exige_motivo),
        }))
        .post(storeEtapa.url({ funil: props.funil.id }), {
            preserveScroll: true,
            onSuccess: () => {
                etapaForm.reset('nome', 'campos');
                etapaForm.sla = '1d';
                etapaForm.exige_motivo = false;
                etapaForm.ordem = 0;
                etapaForm.cor_fundo = '#14574F';
                etapaForm.cor_texto = '#FBF9F4';
                etapaForm.cor_suave = 'rgba(251,249,244,0.9)';
            },
        });
};

const removeEtapa = (id: number): void => {
    if (!confirm('Excluir esta etapa?')) {
        return;
    }

    router.delete(
        destroyEtapa.url({ funil: props.funil.id, etapa: id }),
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="mx-auto flex max-w-[980px] flex-col gap-5">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="negociacoesIndex()"
                    class="text-[12px] text-muted-foreground hover:text-foreground"
                >
                    ← Funil
                </Link>
                <Link
                    :href="funisIndex()"
                    class="text-[12px] text-muted-foreground hover:text-foreground"
                >
                    Funis
                </Link>
            </div>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium text-foreground"
            >
                Editar funil
            </h1>
        </div>

        <form
            class="flex flex-col gap-3.5 rounded-[10px] border border-border bg-card px-5 py-5"
            @submit.prevent="saveFunil"
        >
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >Nome</label
                    >
                    <input
                        v-model="funilForm.nome"
                        type="text"
                        required
                        :class="fieldClass"
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >Slug</label
                    >
                    <input
                        v-model="funilForm.slug"
                        type="text"
                        required
                        :class="fieldClass"
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >Distribuição</label
                    >
                    <select
                        v-model="funilForm.distribuicao"
                        required
                        :class="fieldClass"
                    >
                        <option value="round robin simples">
                            round robin simples
                        </option>
                        <option value="round robin por especialidade">
                            round robin por especialidade
                        </option>
                        <option value="por carga de trabalho">
                            por carga de trabalho
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >Ordem</label
                    >
                    <input
                        v-model.number="funilForm.ordem"
                        type="number"
                        min="0"
                        :class="fieldClass"
                    />
                </div>
            </div>
            <button
                type="submit"
                class="w-fit cursor-pointer rounded-[7px] border border-primary bg-primary px-[13px] py-[7px] text-[12.5px] text-primary-foreground disabled:opacity-60"
                :disabled="funilForm.processing"
            >
                Salvar funil
            </button>
        </form>

        <div class="flex flex-col gap-3">
            <h2 class="m-0 text-[15px] font-medium text-foreground">Etapas</h2>

            <form
                class="grid grid-cols-1 gap-3 rounded-[10px] border border-border bg-card px-4 py-4 md:grid-cols-4"
                @submit.prevent="addEtapa"
            >
                <input
                    v-model="etapaForm.nome"
                    type="text"
                    required
                    placeholder="Nome da etapa"
                    :class="fieldClass"
                />
                <input
                    v-model="etapaForm.sla"
                    type="text"
                    required
                    placeholder="SLA"
                    :class="fieldClass"
                />
                <input
                    v-model="etapaForm.campos"
                    type="text"
                    placeholder="Campos (vírgula)"
                    :class="fieldClass"
                />
                <input
                    v-model.number="etapaForm.ordem"
                    type="number"
                    min="0"
                    :max="maxOrdemEtapa"
                    :title="`Ordem máxima: ${maxOrdemEtapa}`"
                    placeholder="Ordem"
                    :class="fieldClass"
                />
                <input
                    v-model="etapaForm.cor_fundo"
                    type="color"
                    title="Fundo"
                    :class="fieldClass"
                />
                <input
                    v-model="etapaForm.cor_texto"
                    type="color"
                    title="Texto"
                    :class="fieldClass"
                />
                <label class="flex items-center gap-2 text-[12.5px] text-muted-foreground">
                    <input v-model="etapaForm.exige_motivo" type="checkbox" />
                    Exige motivo
                </label>
                <button
                    type="submit"
                    class="cursor-pointer rounded-[7px] border border-primary bg-primary px-3 py-2 text-[12.5px] text-primary-foreground disabled:opacity-60"
                    :disabled="etapaForm.processing"
                >
                    Adicionar etapa
                </button>
                <div
                    v-if="Object.keys(etapaForm.errors).length > 0"
                    class="m-0 flex flex-col gap-1 text-[12px] text-[#9B3B2F] md:col-span-4"
                >
                    <p
                        v-for="(mensagem, campo) in etapaForm.errors"
                        :key="campo"
                        class="m-0"
                    >
                        {{ mensagem }}
                    </p>
                </div>
            </form>

            <div
                class="overflow-hidden rounded-[10px] border border-border bg-card"
            >
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-card">
                            <th
                                class="w-16 px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Ordem
                            </th>
                            <th
                                class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Etapa
                            </th>
                            <th
                                class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                SLA
                            </th>
                            <th
                                class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                            >
                                Campos
                            </th>
                            <th class="px-4 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in etapas"
                            :key="item.id"
                            class="border-t border-[#F2EFE8]"
                        >
                            <td
                                class="px-4 py-3 font-[family-name:var(--font-crm-mono)] text-[12px] text-muted-foreground"
                            >
                                {{ item.ordem }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px]"
                                    :style="{
                                        background: item.cor_fundo,
                                        color: item.cor_texto,
                                    }"
                                >
                                    {{ item.nome }}
                                </span>
                            </td>
                            <td
                                class="px-4 py-3 font-[family-name:var(--font-crm-mono)] text-[12px] text-muted-foreground"
                            >
                                {{ item.sla }}
                            </td>
                            <td class="px-4 py-3 text-[12px] text-muted-foreground">
                                {{ item.campos.join(', ') || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            editEtapa.url({
                                                funil: funil.id,
                                                etapa: item.id,
                                            })
                                        "
                                        class="text-[12px] text-primary hover:underline"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        class="cursor-pointer text-[12px] text-[#9B3B2F]"
                                        @click="removeEtapa(item.id)"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
