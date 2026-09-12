<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Admin/EtapaFunilController';
import { edit as funilEdit } from '@/routes/admin/funis';

const props = defineProps<{
    funil: {
        id: number;
        nome: string;
    };
    etapa: {
        id: number;
        nome: string;
        sla: string;
        campos: string[];
        meta_evento: string | null;
        exige_motivo: boolean;
        ordem: number;
        cor_fundo: string;
        cor_texto: string;
        cor_suave: string;
    };
    etapas: Array<{
        id: number;
        nome: string;
        ordem: number;
    }>;
}>();

const form = useForm({
    nome: props.etapa.nome,
    sla: props.etapa.sla,
    campos: props.etapa.campos.join(', '),
    meta_evento: props.etapa.meta_evento ?? '',
    exige_motivo: props.etapa.exige_motivo,
    ordem: props.etapa.ordem,
    cor_fundo: props.etapa.cor_fundo,
    cor_texto: props.etapa.cor_texto,
    cor_suave: props.etapa.cor_suave,
});

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2.5 text-[13px] text-foreground outline-none focus:border-primary';

const submit = (): void => {
    form
        .transform((data) => ({
            ...data,
            campos: data.campos,
            exige_motivo: Boolean(data.exige_motivo),
        }))
        .patch(
            update.url({ funil: props.funil.id, etapa: props.etapa.id }),
            { preserveScroll: true },
        );
};
</script>

<template>
    <div class="mx-auto flex max-w-[640px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <Link
                :href="funilEdit.url({ funil: funil.id })"
                class="text-[12px] text-muted-foreground hover:text-foreground"
            >
                ← {{ funil.nome }}
            </Link>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium text-foreground"
            >
                Editar etapa
            </h1>
        </div>

        <form
            class="flex flex-col gap-3.5 rounded-[10px] border border-border bg-card px-5 py-5"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >Nome</label
                >
                <input v-model="form.nome" type="text" required :class="fieldClass" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >SLA</label
                >
                <input v-model="form.sla" type="text" required :class="fieldClass" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >Campos (separados por vírgula)</label
                >
                <input v-model="form.campos" type="text" :class="fieldClass" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >Evento da Meta ao entrar nesta etapa</label
                >
                <select v-model="form.meta_evento" :class="fieldClass">
                    <option value="">Nenhum</option>
                    <option value="Contact">Contato</option>
                    <option value="Schedule">Agendamento</option>
                    <option value="SubmitApplication">Proposta enviada</option>
                    <option value="CompleteRegistration">Cadastro concluído</option>
                    <option value="Purchase">Compra / contrato fechado</option>
                </select>
                <span class="text-[11px] text-muted-foreground">
                    A API de Conversões recebe este evento quando uma negociação
                    avança para esta etapa. "Lead" é sempre enviado na criação.
                </span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >Cor de fundo</label
                    >
                    <input v-model="form.cor_fundo" type="color" :class="fieldClass" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label
                        class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >Cor do texto</label
                    >
                    <input v-model="form.cor_texto" type="color" :class="fieldClass" />
                </div>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >Cor suave (CSS)</label
                >
                <input v-model="form.cor_suave" type="text" :class="fieldClass" />
            </div>
            <label class="flex items-center gap-2 text-[13px] text-muted-foreground">
                <input v-model="form.exige_motivo" type="checkbox" />
                Exige motivo
            </label>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >Ordem</label
                >
                <select v-model.number="form.ordem" required :class="fieldClass">
                    <option
                        v-for="item in etapas"
                        :key="item.id"
                        :value="item.ordem"
                    >
                        {{ item.ordem }}
                    </option>
                </select>
            </div>
            <div class="flex gap-2 pt-1">
                <button
                    type="submit"
                    class="cursor-pointer rounded-[7px] border border-primary bg-primary px-[13px] py-[7px] text-[12.5px] text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Salvar
                </button>
                <Link
                    :href="funilEdit.url({ funil: funil.id })"
                    class="rounded-[7px] border border-border bg-card px-[13px] py-[7px] text-[12.5px] text-muted-foreground"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
