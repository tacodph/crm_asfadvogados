<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    destroy,
    edit,
    store,
} from '@/actions/App/Http/Controllers/Admin/StatusConsentimentoController';
import { index as adminIndex } from '@/routes/admin';

type StatusItem = {
    id: number;
    slug: string;
    nome: string;
    cor_fundo: string;
    cor_texto: string;
    visivel_cadastro: boolean;
    ordem: number;
};

defineProps<{
    status: StatusItem[];
}>();

const form = useForm({
    nome: '',
    slug: '',
    cor_fundo: 'var(--muted)',
    cor_texto: 'var(--muted-foreground)',
    visivel_cadastro: true,
    ordem: 0,
});

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-[13px] text-foreground outline-none focus:border-primary';

const submit = (): void => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('nome', 'slug');
            form.cor_fundo = 'var(--muted)';
            form.cor_texto = 'var(--muted-foreground)';
            form.visivel_cadastro = true;
            form.ordem = 0;
        },
    });
};

const remove = (id: number): void => {
    if (!confirm('Excluir este status?')) {
        return;
    }

    router.delete(destroy.url({ status_consentimento: id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="mx-auto flex max-w-[980px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <Link
                :href="adminIndex()"
                class="text-[12px] text-muted-foreground hover:text-foreground"
            >
                ← Administração
            </Link>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium text-foreground"
            >
                Status de consentimento
            </h1>
        </div>

        <form
            class="grid grid-cols-1 gap-3 rounded-[10px] border border-border bg-card px-4 py-4 md:grid-cols-6"
            @submit.prevent="submit"
        >
            <input
                v-model="form.nome"
                type="text"
                required
                placeholder="Nome"
                class="md:col-span-2"
                :class="fieldClass"
            />
            <input
                v-model="form.slug"
                type="text"
                placeholder="Slug"
                :class="fieldClass"
            />
            <input v-model="form.cor_fundo" type="color" :class="fieldClass" />
            <input v-model="form.cor_texto" type="color" :class="fieldClass" />
            <label class="flex items-center gap-2 text-[12.5px] text-muted-foreground">
                <input v-model="form.visivel_cadastro" type="checkbox" />
                Visível no cadastro
            </label>
            <div class="flex gap-2 md:col-span-6">
                <input
                    v-model.number="form.ordem"
                    type="number"
                    min="0"
                    class="w-28"
                    :class="fieldClass"
                />
                <button
                    type="submit"
                    class="cursor-pointer rounded-[7px] border border-primary bg-primary px-3 py-2 text-[12.5px] text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Adicionar
                </button>
            </div>
            <p
                v-if="form.errors.nome || form.errors.slug"
                class="m-0 text-[12px] text-[#9B3B2F] md:col-span-6"
            >
                {{ form.errors.nome || form.errors.slug }}
            </p>
        </form>

        <div
            class="overflow-hidden rounded-[10px] border border-border bg-card"
        >
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-card">
                        <th
                            class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Status
                        </th>
                        <th
                            class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Slug
                        </th>
                        <th
                            class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Cadastro
                        </th>
                        <th class="px-4 py-2.5" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in status"
                        :key="item.id"
                        class="border-t border-[#F2EFE8]"
                    >
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
                            {{ item.slug }}
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-muted-foreground">
                            {{ item.visivel_cadastro ? 'Sim' : 'Não' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="
                                        edit.url({
                                            status_consentimento: item.id,
                                        })
                                    "
                                    class="text-[12px] text-primary hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="cursor-pointer text-[12px] text-[#9B3B2F]"
                                    @click="remove(item.id)"
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
</template>
