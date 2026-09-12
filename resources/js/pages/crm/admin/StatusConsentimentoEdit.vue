<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Admin/StatusConsentimentoController';
import { index as statusIndex } from '@/routes/admin/status-consentimentos';

const props = defineProps<{
    status: {
        id: number;
        slug: string;
        nome: string;
        cor_fundo: string;
        cor_texto: string;
        visivel_cadastro: boolean;
        ordem: number;
    };
}>();

const form = useForm({
    nome: props.status.nome,
    slug: props.status.slug,
    cor_fundo: props.status.cor_fundo,
    cor_texto: props.status.cor_texto,
    visivel_cadastro: props.status.visivel_cadastro,
    ordem: props.status.ordem,
});

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2.5 text-[13px] text-foreground outline-none focus:border-primary';

const submit = (): void => {
    form
        .transform((data) => ({
            ...data,
            visivel_cadastro: Boolean(data.visivel_cadastro),
            ordem: Number(data.ordem),
        }))
        .patch(
            update.url({ status_consentimento: props.status.id }),
            { preserveScroll: true },
        );
};
</script>

<template>
    <div class="mx-auto flex max-w-[640px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <Link
                :href="statusIndex()"
                class="text-[12px] text-muted-foreground hover:text-foreground"
            >
                ← Status de consentimento
            </Link>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium text-foreground"
            >
                Editar status
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
                    >Slug</label
                >
                <input v-model="form.slug" type="text" required :class="fieldClass" />
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
            <label class="flex items-center gap-2 text-[13px] text-muted-foreground">
                <input v-model="form.visivel_cadastro" type="checkbox" />
                Visível no cadastro
            </label>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >Ordem</label
                >
                <input
                    v-model.number="form.ordem"
                    type="number"
                    min="0"
                    :class="fieldClass"
                />
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
                    :href="statusIndex()"
                    class="rounded-[7px] border border-border bg-card px-[13px] py-[7px] text-[12.5px] text-muted-foreground"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
