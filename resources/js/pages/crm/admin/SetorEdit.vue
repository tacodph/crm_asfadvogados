<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Admin/SetorController';
import { index as setoresIndex } from '@/routes/admin/setores';

const props = defineProps<{
    setor: {
        id: number;
        slug: string;
        nome: string;
        ordem: number;
    };
}>();

const form = useForm({
    nome: props.setor.nome,
    slug: props.setor.slug,
    ordem: props.setor.ordem,
});

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2.5 text-[13px] text-foreground outline-none focus:border-primary';

const submit = (): void => {
    form.patch(update.url({ setor: props.setor.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="mx-auto flex max-w-[640px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <Link
                :href="setoresIndex()"
                class="text-[12px] text-muted-foreground hover:text-foreground"
            >
                ← Setores
            </Link>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium text-foreground"
            >
                Editar setor
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
                <p v-if="form.errors.nome" class="m-0 text-[12px] text-[#9B3B2F]">
                    {{ form.errors.nome }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >Slug</label
                >
                <input v-model="form.slug" type="text" required :class="fieldClass" />
                <p v-if="form.errors.slug" class="m-0 text-[12px] text-[#9B3B2F]">
                    {{ form.errors.slug }}
                </p>
            </div>
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
                    :href="setoresIndex()"
                    class="rounded-[7px] border border-border bg-card px-[13px] py-[7px] text-[12.5px] text-muted-foreground"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
